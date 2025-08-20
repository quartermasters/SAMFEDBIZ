<?php
/**
 * Database Maintenance Cron Job
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Runs daily at 03:00 Dubai time for database cleanup
 * Timezone: Asia/Dubai
 */

require_once '../src/Bootstrap.php';

// Set timezone to Dubai
date_default_timezone_set('Asia/Dubai');

// Log start time
$startTime = new DateTime();
echo "Database maintenance started at: " . $startTime->format('Y-m-d H:i:s T') . "\n";

try {
    $cleanupCount = 0;
    
    // Clean up old activity logs (keep 90 days)
    $stmt = $pdo->prepare("
        DELETE FROM activity_log 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->execute();
    $deletedActivityLogs = $stmt->rowCount();
    $cleanupCount += $deletedActivityLogs;
    echo "Deleted {$deletedActivityLogs} old activity log entries\n";
    
    // Clean up old drive sync logs (keep 30 days)
    $stmt = $pdo->prepare("
        DELETE FROM drive_sync_log 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $deletedSyncLogs = $stmt->rowCount();
    $cleanupCount += $deletedSyncLogs;
    echo "Deleted {$deletedSyncLogs} old drive sync log entries\n";
    
    // Clean up old news items (keep 180 days)
    $stmt = $pdo->prepare("
        DELETE FROM news_items 
        WHERE published_at < DATE_SUB(NOW(), INTERVAL 180 DAY)
    ");
    $stmt->execute();
    $deletedNewsItems = $stmt->rowCount();
    $cleanupCount += $deletedNewsItems;
    echo "Deleted {$deletedNewsItems} old news items\n";
    
    // Clean up expired opportunities (keep for 30 days after close date)
    $stmt = $pdo->prepare("
        DELETE FROM opportunities 
        WHERE close_date < DATE_SUB(NOW(), INTERVAL 30 DAY) 
        AND status IN ('expired', 'cancelled', 'awarded')
    ");
    $stmt->execute();
    $deletedOpportunities = $stmt->rowCount();
    $cleanupCount += $deletedOpportunities;
    echo "Deleted {$deletedOpportunities} old opportunities\n";
    
    // Clean up orphaned opportunity metadata
    $stmt = $pdo->prepare("
        DELETE om FROM opportunity_meta om
        LEFT JOIN opportunities o ON om.opportunity_id = o.id
        WHERE o.id IS NULL
    ");
    $stmt->execute();
    $deletedOrphanMeta = $stmt->rowCount();
    $cleanupCount += $deletedOrphanMeta;
    echo "Deleted {$deletedOrphanMeta} orphaned opportunity metadata entries\n";
    
    // Optimize tables
    $tables = ['activity_log', 'drive_sync_log', 'news_items', 'opportunities', 'opportunity_meta'];
    foreach ($tables as $table) {
        $pdo->exec("OPTIMIZE TABLE {$table}");
    }
    echo "Optimized " . count($tables) . " database tables\n";
    
    // Log maintenance results
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (entity_type, entity_id, activity_type, activity_data, created_by, created_at)
        VALUES ('system', 'db_maintenance', 'maintenance_completed', ?, 1, NOW())
    ");
    $stmt->execute([json_encode([
        'total_records_cleaned' => $cleanupCount,
        'activity_logs_deleted' => $deletedActivityLogs,
        'sync_logs_deleted' => $deletedSyncLogs,
        'news_items_deleted' => $deletedNewsItems,
        'opportunities_deleted' => $deletedOpportunities,
        'orphan_meta_deleted' => $deletedOrphanMeta,
        'tables_optimized' => count($tables),
        'maintenance_time' => $startTime->format('c'),
        'timezone' => 'Asia/Dubai'
    ])]);
    
    echo "Database maintenance completed successfully\n";
    echo "Total records cleaned: {$cleanupCount}\n";
    
} catch (Exception $e) {
    error_log("Database maintenance failed: " . $e->getMessage());
    echo "Database maintenance failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Database maintenance process completed at: " . (new DateTime())->format('Y-m-d H:i:s T') . "\n";