<?php
/**
 * Google Drive Sync Cron Job
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Runs 4x daily (01:00, 07:00, 13:00, 19:00 Dubai time)
 * Timezone: Asia/Dubai
 */

require_once '../src/Bootstrap.php';

use SamFedBiz\Services\GoogleDriveService;
use SamFedBiz\Config\EnvManager;

// Set timezone to Dubai
date_default_timezone_set('Asia/Dubai');

// Log start time
$startTime = new DateTime();
echo "Drive sync started at: " . $startTime->format('Y-m-d H:i:s T') . "\n";

try {
    // Initialize services
    $envManager = new EnvManager();
    $driveService = new GoogleDriveService($envManager);
    
    // Get OAuth tokens for Drive access (use admin user ID = 1)
    $stmt = $pdo->prepare("
        SELECT access_token, refresh_token, expires_at 
        FROM oauth_tokens 
        WHERE service = 'google_drive' AND user_id = 1 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tokenData) {
        echo "No OAuth tokens found for Google Drive\n";
        exit(0);
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
            WHERE service = 'google_drive' AND user_id = 1
        ");
        $stmt->execute([$newAccessToken, 1]);
        
        $tokenData['access_token'] = $newAccessToken;
    }
    
    $driveService->setTokens($tokenData['access_token'], $tokenData['refresh_token']);
    
    // Start sync process
    $syncStartTime = date('Y-m-d H:i:s');
    $documentsProcessed = 0;
    $newDocuments = 0;
    $updatedDocuments = 0;
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
                    SELECT id, title, updated_at FROM research_docs 
                    WHERE drive_file_id = ? OR (title = ? AND source_url = ?)
                ");
                $stmt->execute([$file['id'], $file['title'], $file['web_view_link']]);
                $existingDoc = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingDoc) {
                    // Check if update is needed
                    $needsUpdate = false;
                    if ($existingDoc['title'] !== $file['title'] ||
                        strtotime($existingDoc['updated_at']) < strtotime($file['modified_time'])) {
                        $needsUpdate = true;
                    }
                    
                    if ($needsUpdate) {
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
                        $updatedDocuments++;
                    }
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
                    
                    // Add topic tags
                    if (strpos($title_lower, 'cyber') !== false || strpos($title_lower, 'security') !== false) {
                        $tags[] = 'cybersecurity';
                    }
                    if (strpos($title_lower, 'logistics') !== false || strpos($title_lower, 'supply') !== false) {
                        $tags[] = 'logistics';
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
        VALUES ('system', 'drive_sync', 'sync_completed', ?, 1, NOW())
    ");
    $stmt->execute([json_encode([
        'documents_processed' => $documentsProcessed,
        'new_documents' => $newDocuments,
        'updated_documents' => $updatedDocuments,
        'errors_count' => count($errors),
        'sync_status' => $syncStatus,
        'sync_time' => $syncStartTime,
        'timezone' => 'Asia/Dubai'
    ])]);
    
    echo "Drive sync completed:\n";
    echo "- Documents processed: {$documentsProcessed}\n";
    echo "- New documents: {$newDocuments}\n";
    echo "- Updated documents: {$updatedDocuments}\n";
    echo "- Status: {$syncStatus}\n";
    
    if (!empty($errors)) {
        echo "Errors encountered: " . implode('; ', array_slice($errors, 0, 3)) . "\n";
    }
    
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
    
    echo "Drive sync failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Drive sync process completed at: " . (new DateTime())->format('Y-m-d H:i:s T') . "\n";