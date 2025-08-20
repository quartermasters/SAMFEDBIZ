<?php
/**
 * Google Drive Sync API Endpoint
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Handles Google Drive document synchronization
 */

require_once __DIR__ . '/../../../src/Bootstrap.php';

use SamFedBiz\Auth\AuthManager;
use SamFedBiz\Services\GoogleDriveService;
use SamFedBiz\Config\EnvManager;

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Initialize managers
$envManager = new EnvManager();
$authManager = new AuthManager($pdo);
$driveService = new GoogleDriveService($envManager);

// Check authentication
if (!$authManager->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Check CSRF token
if (!$authManager->validateCSRFToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check user permissions
$user = $authManager->getCurrentUser();
if ($user['role'] === 'viewer') {
    http_response_code(403);
    echo json_encode(['error' => 'Insufficient permissions']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$forceRefresh = $input['force_refresh'] ?? false;

try {
    // Get stored OAuth tokens
    $stmt = $pdo->prepare("
        SELECT access_token, refresh_token, expires_at 
        FROM oauth_tokens 
        WHERE service = 'google_drive' AND user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenData) {
        // No tokens found - need OAuth setup
        echo json_encode([
            'status' => 'oauth_required',
            'message' => 'Google Drive OAuth setup required',
            'auth_url' => $driveService->getAuthUrl('drive_sync')
        ]);
        exit;
    }

    // Check if token needs refresh
    $needsRefresh = time() >= strtotime($tokenData['expires_at']);
    
    if ($needsRefresh && $tokenData['refresh_token']) {
        $driveService->setTokens($tokenData['access_token'], $tokenData['refresh_token']);
        $newAccessToken = $driveService->refreshAccessToken();
        
        // Update token in database
        $stmt = $pdo->prepare("
            UPDATE oauth_tokens 
            SET access_token = ?, expires_at = DATE_ADD(NOW(), INTERVAL 3600 SECOND)
            WHERE service = 'google_drive' AND user_id = ?
        ");
        $stmt->execute([$newAccessToken, $user['id']]);
        
        $tokenData['access_token'] = $newAccessToken;
    }

    $driveService->setTokens($tokenData['access_token'], $tokenData['refresh_token']);

    // Check last sync time
    $stmt = $pdo->prepare("
        SELECT last_sync, sync_status 
        FROM drive_sync_log 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $lastSync = $stmt->fetch(PDO::FETCH_ASSOC);

    // Don't sync if last sync was recent (unless forced)
    if (!$forceRefresh && $lastSync && 
        strtotime($lastSync['last_sync']) > (time() - 3600) && // 1 hour cooldown
        $lastSync['sync_status'] === 'success') {
        
        echo json_encode([
            'status' => 'skipped',
            'message' => 'Recent sync found, skipping',
            'last_sync' => $lastSync['last_sync']
        ]);
        exit;
    }

    // Start sync process
    $syncStartTime = date('Y-m-d H:i:s');
    $documentsProcessed = 0;
    $newDocuments = 0;
    $errors = [];

    // Create sync log entry
    $stmt = $pdo->prepare("
        INSERT INTO drive_sync_log (last_sync, sync_status, documents_synced, errors, created_at)
        VALUES (?, 'in_progress', 0, '', NOW())
    ");
    $stmt->execute([$syncStartTime]);
    $syncLogId = $pdo->lastInsertId();

    // Fetch documents from Google Drive
    $pageToken = null;
    do {
        $result = $driveService->listDocuments([
            'pageSize' => 100,
            'pageToken' => $pageToken,
            'query' => "mimeType contains 'document' or mimeType='application/pdf' or name contains '.pdf' or name contains '.docx'"
        ]);

        foreach ($result['files'] as $file) {
            try {
                // Check if document already exists
                $stmt = $pdo->prepare("
                    SELECT id FROM research_docs 
                    WHERE drive_file_id = ? OR (title = ? AND source_url = ?)
                ");
                $stmt->execute([$file['id'], $file['title'], $file['web_view_link']]);
                $existingDoc = $stmt->fetch();

                if ($existingDoc) {
                    // Update existing document
                    $stmt = $pdo->prepare("
                        UPDATE research_docs 
                        SET title = ?, doc_type = ?, source_url = ?, 
                            drive_file_id = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $file['title'],
                        $file['doc_type'],
                        $file['web_view_link'],
                        $file['id'],
                        $existingDoc['id']
                    ]);
                } else {
                    // Insert new document
                    $tags = [$file['doc_type']];
                    
                    // Infer program tags from title/content
                    $title_lower = strtolower($file['title']);
                    if (strpos($title_lower, 'tls') !== false || strpos($title_lower, 'tactical') !== false) {
                        $tags[] = 'tls';
                    }
                    if (strpos($title_lower, 'oasis') !== false) {
                        $tags[] = 'oasis+';
                    }
                    if (strpos($title_lower, 'sewp') !== false) {
                        $tags[] = 'sewp';
                    }

                    $stmt = $pdo->prepare("
                        INSERT INTO research_docs 
                        (title, doc_type, source_url, drive_file_id, description, tags, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $file['title'],
                        $file['doc_type'],
                        $file['web_view_link'],
                        $file['id'],
                        $file['description'],
                        json_encode($tags)
                    ]);
                    
                    $newDocuments++;
                }

                $documentsProcessed++;

            } catch (Exception $e) {
                $errors[] = "Error processing {$file['title']}: " . $e->getMessage();
                error_log("Drive sync error for file {$file['id']}: " . $e->getMessage());
            }
        }

        $pageToken = $result['nextPageToken'] ?? null;

    } while ($pageToken && $documentsProcessed < 1000); // Limit to prevent timeouts

    // Update sync log
    $syncStatus = empty($errors) ? 'success' : 'partial_success';
    $errorText = implode('; ', array_slice($errors, 0, 5)); // Limit error text

    $stmt = $pdo->prepare("
        UPDATE drive_sync_log 
        SET sync_status = ?, documents_synced = ?, new_documents = ?, errors = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $syncStatus,
        $documentsProcessed,
        $newDocuments,
        $errorText,
        $syncLogId
    ]);

    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (entity_type, entity_id, activity_type, activity_data, created_by, created_at)
        VALUES ('system', 'drive_sync', 'sync_completed', ?, ?, NOW())
    ");
    $stmt->execute([
        json_encode([
            'documents_processed' => $documentsProcessed,
            'new_documents' => $newDocuments,
            'errors_count' => count($errors),
            'sync_status' => $syncStatus
        ]),
        $user['id']
    ]);

    echo json_encode([
        'status' => $syncStatus,
        'documents_synced' => $documentsProcessed,
        'new_documents' => $newDocuments,
        'errors' => $errors,
        'sync_time' => $syncStartTime
    ]);

} catch (Exception $e) {
    error_log("Drive sync failed: " . $e->getMessage());
    
    // Update sync log with error
    if (isset($syncLogId)) {
        $stmt = $pdo->prepare("
            UPDATE drive_sync_log 
            SET sync_status = 'error', errors = ?
            WHERE id = ?
        ");
        $stmt->execute([$e->getMessage(), $syncLogId]);
    }

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Drive sync failed: ' . $e->getMessage()
    ]);
}