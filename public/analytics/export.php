<?php
/**
 * Analytics CSV Export
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Exports analytics data to CSV format
 */

require_once __DIR__ . '/../../src/Bootstrap.php';

use SamFedBiz\Auth\AuthManager;
use SamFedBiz\Core\ProgramRegistry;

// Initialize managers
$authManager = new AuthManager($pdo);

// Check authentication
if (!$authManager->isAuthenticated()) {
    http_response_code(401);
    echo "Authentication required";
    exit;
}

$user = $authManager->getCurrentUser();

// Check admin/ops permissions for analytics export
if (!in_array($user['role'], ['admin', 'ops'])) {
    http_response_code(403);
    echo "Access denied. Analytics export requires admin or ops role.";
    exit;
}

// Get parameters
$start_date = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end'] ?? date('Y-m-d');
$program_filter_raw = $_GET['program'] ?? '';
$program_filter_norm = ProgramRegistry::normalizeCode($program_filter_raw);
$program_filter = $program_filter_raw === '' ? '' : ProgramRegistry::getDisplayCode($program_filter_norm);
$export_type = $_GET['type'] ?? 'summary';

// Validate dates
$start_datetime = DateTime::createFromFormat('Y-m-d', $start_date);
$end_datetime = DateTime::createFromFormat('Y-m-d', $end_date);

if (!$start_datetime || !$end_datetime || $start_datetime > $end_datetime) {
    http_response_code(400);
    echo "Invalid date range";
    exit;
}

// Prepare filename
$filename = "samfedbiz_analytics_{$export_type}_{$start_date}_to_{$end_date}";
if ($program_filter) {
    $filename .= "_" . str_replace(['+', ' '], ['plus', '_'], $program_filter);
}
$filename .= ".csv";

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Open output stream
$output = fopen('php://output', 'w');

try {
    switch ($export_type) {
        case 'engagement':
            exportEngagementData($pdo, $output, $start_date, $end_date, $program_filter);
            break;
            
        case 'conversion':
            exportConversionData($pdo, $output, $start_date, $end_date, $program_filter);
            break;
            
        case 'content':
            exportContentData($pdo, $output, $start_date, $end_date);
            break;
            
        case 'reliability':
            exportReliabilityData($pdo, $output, $start_date, $end_date, $program_filter);
            break;
            
        case 'summary':
        default:
            exportSummaryData($pdo, $output, $start_date, $end_date, $program_filter);
            break;
    }
} catch (Exception $e) {
    error_log("Analytics export error: " . $e->getMessage());
    
    // Clear any output and send error
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo "Export failed. Please try again.";
    exit;
}

fclose($output);

/**
 * Export engagement data
 */
function exportEngagementData($pdo, $output, $start_date, $end_date, $program_filter) {
    // Write headers
    fputcsv($output, [
        'Date',
        'Total Interactions',
        'Unique Users',
        'Avg Message Length',
        'Program Context'
    ]);
    
    $query = "
        SELECT 
            DATE(cm.created_at) as date,
            COUNT(*) as total_interactions,
            COUNT(DISTINCT cm.user_id) as unique_users,
            ROUND(AVG(LENGTH(cm.message)), 2) as avg_message_length,
            COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cm.context, '$.program')), 'Unknown') as program_context
        FROM chat_messages cm
        WHERE cm.created_at BETWEEN ? AND ?
    ";
    
    $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    
    if ($program_filter) {
        $query .= " AND JSON_EXTRACT(cm.context, '$.program') = ?";
        $params[] = $program_filter;
    }
    
    $query .= " GROUP BY DATE(cm.created_at), JSON_EXTRACT(cm.context, '$.program') ORDER BY date, program_context";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['date'],
            $row['total_interactions'],
            $row['unique_users'],
            $row['avg_message_length'],
            $row['program_context']
        ]);
    }
}

/**
 * Export conversion data
 */
function exportConversionData($pdo, $output, $start_date, $end_date, $program_filter) {
    // Write headers
    fputcsv($output, [
        'Date',
        'Total Outreach',
        'Successful Sends',
        'Responses Received',
        'Response Rate %',
        'Program'
    ]);
    
    $query = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_outreach,
            COUNT(CASE WHEN status = 'sent' THEN 1 END) as successful_sends,
            COUNT(CASE WHEN response_received = 1 THEN 1 END) as responses_received,
            ROUND(COUNT(CASE WHEN response_received = 1 THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as response_rate,
            program_code
        FROM outreach 
        WHERE created_at BETWEEN ? AND ?
    ";
    
    $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    
    if ($program_filter) {
        $query .= " AND program_code = ?";
        $params[] = $program_filter;
    }
    
    $query .= " GROUP BY DATE(created_at), program_code ORDER BY date, program_code";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['date'],
            $row['total_outreach'],
            $row['successful_sends'],
            $row['responses_received'],
            $row['response_rate'],
            $row['program_code']
        ]);
    }
}

/**
 * Export content data
 */
function exportContentData($pdo, $output, $start_date, $end_date) {
    // Write headers
    fputcsv($output, [
        'Date',
        'Briefs Sent',
        'Avg Sections Per Brief',
        'Opened Briefs',
        'Open Rate %',
        'Subscriber Count'
    ]);
    
    $query = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as briefs_sent,
            ROUND(AVG(JSON_LENGTH(sections)), 2) as avg_sections_per_brief,
            COUNT(CASE WHEN JSON_EXTRACT(metrics, '$.open_rate') > 0 THEN 1 END) as opened_briefs,
            ROUND(AVG(CAST(JSON_EXTRACT(metrics, '$.open_rate') AS DECIMAL(5,2))), 2) as avg_open_rate,
            AVG(CAST(JSON_EXTRACT(metrics, '$.subscriber_count') AS UNSIGNED)) as avg_subscriber_count
        FROM daily_briefs 
        WHERE created_at BETWEEN ? AND ? AND status = 'sent'
        GROUP BY DATE(created_at) 
        ORDER BY date
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['date'],
            $row['briefs_sent'],
            $row['avg_sections_per_brief'],
            $row['opened_briefs'],
            $row['avg_open_rate'],
            round($row['avg_subscriber_count'])
        ]);
    }
}

/**
 * Export reliability data
 */
function exportReliabilityData($pdo, $output, $start_date, $end_date, $program_filter) {
    // Write headers
    fputcsv($output, [
        'Date',
        'Total News Items',
        'Confirmed Items (≥80%)',
        'Developing Items (50-79%)',
        'Signal Items (<50%)',
        'Avg Reliability Score',
        'Program'
    ]);
    
    $query = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_news_items,
            COUNT(CASE WHEN reliability_score >= 0.8 THEN 1 END) as confirmed_items,
            COUNT(CASE WHEN reliability_score BETWEEN 0.5 AND 0.79 THEN 1 END) as developing_items,
            COUNT(CASE WHEN reliability_score < 0.5 THEN 1 END) as signal_items,
            ROUND(AVG(reliability_score), 3) as avg_reliability,
            program_code
        FROM news_items 
        WHERE created_at BETWEEN ? AND ?
    ";
    
    $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    
    if ($program_filter) {
        $query .= " AND program_code = ?";
        $params[] = $program_filter;
    }
    
    $query .= " GROUP BY DATE(created_at), program_code ORDER BY date, program_code";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['date'],
            $row['total_news_items'],
            $row['confirmed_items'],
            $row['developing_items'],
            $row['signal_items'],
            $row['avg_reliability'],
            $row['program_code']
        ]);
    }
}

/**
 * Export summary data
 */
function exportSummaryData($pdo, $output, $start_date, $end_date, $program_filter) {
    // Write headers
    fputcsv($output, [
        'Metric Type',
        'Metric Name',
        'Value',
        'Unit',
        'Date Range',
        'Program Filter'
    ]);
    
    $date_range = "$start_date to $end_date";
    $program_display = $program_filter ?: 'All Programs';
    
    // Total users
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT id) FROM users WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $total_users = $stmt->fetchColumn();
    
    fputcsv($output, ['Engagement', 'Total Users', $total_users, 'Count', $date_range, $program_display]);
    
    // Total interactions
    $query = "SELECT COUNT(*) FROM chat_messages WHERE created_at BETWEEN ? AND ?";
    $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    
    if ($program_filter) {
        $query .= " AND JSON_EXTRACT(context, '$.program') = ?";
        $params[] = $program_filter;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $total_interactions = $stmt->fetchColumn();
    
    fputcsv($output, ['Engagement', 'Total Interactions', $total_interactions, 'Count', $date_range, $program_display]);
    
    // Outreach metrics
    $query = "
        SELECT 
            COUNT(*) as total_outreach,
            COUNT(CASE WHEN status = 'sent' THEN 1 END) as successful_sends,
            COUNT(CASE WHEN response_received = 1 THEN 1 END) as responses_received
        FROM outreach 
        WHERE created_at BETWEEN ? AND ?
    ";
    $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    
    if ($program_filter) {
        $query .= " AND program_code = ?";
        $params[] = $program_filter;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $outreach_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    fputcsv($output, ['Conversion', 'Total Outreach', $outreach_data['total_outreach'], 'Count', $date_range, $program_display]);
    fputcsv($output, ['Conversion', 'Successful Sends', $outreach_data['successful_sends'], 'Count', $date_range, $program_display]);
    fputcsv($output, ['Conversion', 'Responses Received', $outreach_data['responses_received'], 'Count', $date_range, $program_display]);
    
    if ($outreach_data['total_outreach'] > 0) {
        $response_rate = round(($outreach_data['responses_received'] / $outreach_data['total_outreach']) * 100, 2);
        fputcsv($output, ['Conversion', 'Response Rate', $response_rate, 'Percentage', $date_range, $program_display]);
    }
    
    // Content metrics
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM daily_briefs WHERE created_at BETWEEN ? AND ? AND status = 'sent'");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $total_briefs = $stmt->fetchColumn();
    
    fputcsv($output, ['Content', 'Briefs Sent', $total_briefs, 'Count', $date_range, $program_display]);
    
    // Reliability metrics
    $query = "
        SELECT 
            COUNT(*) as total_news,
            AVG(reliability_score) as avg_reliability
        FROM news_items 
        WHERE created_at BETWEEN ? AND ?
    ";
    $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    
    if ($program_filter) {
        $query .= " AND program_code = ?";
        $params[] = $program_filter;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reliability_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    fputcsv($output, ['Reliability', 'Total News Items', $reliability_data['total_news'], 'Count', $date_range, $program_display]);
    
    if ($reliability_data['avg_reliability']) {
        $avg_reliability = round($reliability_data['avg_reliability'], 3);
        fputcsv($output, ['Reliability', 'Average Reliability Score', $avg_reliability, 'Score (0-1)', $date_range, $program_display]);
    }
}
?>
