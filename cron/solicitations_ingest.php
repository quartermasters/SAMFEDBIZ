<?php
/**
 * Solicitations Ingestion Cron Job
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Runs 4x daily at :20 minutes (00:20, 06:20, 12:20, 18:20)
 * Timezone: Asia/Dubai
 */

require_once '../src/Bootstrap.php';

use SamFedBiz\Core\ProgramRegistry;

// Set timezone to Dubai
date_default_timezone_set('Asia/Dubai');

// Log start time
$startTime = new DateTime();
echo "Solicitations ingest started at: " . $startTime->format('Y-m-d H:i:s T') . "\n";

try {
    // Initialize program registry
    $programRegistry = new ProgramRegistry($pdo);
    $activePrograms = $programRegistry->getActivePrograms();
    
    $totalSolicitations = 0;
    $newSolicitations = 0;
    $updatedSolicitations = 0;
    $errors = [];
    
    foreach ($activePrograms as $program) {
        echo "Ingesting solicitations for program: {$program['code']}\n";
        
        try {
            // Get adapter for this program
            $adapterClass = "SamFedBiz\\Adapters\\{$program['adapter']}";
            
            if (!class_exists($adapterClass)) {
                throw new Exception("Adapter class {$adapterClass} not found");
            }
            
            $adapter = new $adapterClass();
            
            // Fetch solicitations from the adapter
            $solicitations = $adapter->fetchSolicitations();
            
            foreach ($solicitations as $solicitation) {
                // Normalize the solicitation data
                $normalized = $adapter->normalize($solicitation);
                
                // Check if solicitation already exists
                $stmt = $pdo->prepare("
                    SELECT id, title, status, close_date, updated_at 
                    FROM opportunities 
                    WHERE opp_no = ? AND program_code = ?
                ");
                $stmt->execute([$normalized['opp_no'], $program['code']]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    // Check if update is needed
                    $needsUpdate = false;
                    if ($existing['title'] !== $normalized['title'] ||
                        $existing['status'] !== $normalized['status'] ||
                        $existing['close_date'] !== $normalized['close_date']) {
                        $needsUpdate = true;
                    }
                    
                    if ($needsUpdate) {
                        // Update existing solicitation
                        $stmt = $pdo->prepare("
                            UPDATE opportunities 
                            SET title = ?, agency = ?, status = ?, close_date = ?, 
                                url = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $normalized['title'],
                            $normalized['agency'],
                            $normalized['status'],
                            $normalized['close_date'],
                            $normalized['url'],
                            $existing['id']
                        ]);
                        
                        // Update metadata if available
                        $extraFields = $adapter->extraFields();
                        if (!empty($extraFields) && !empty($normalized['extra'])) {
                            $stmt = $pdo->prepare("
                                INSERT INTO opportunity_meta (opportunity_id, meta_key, meta_value)
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)
                            ");
                            
                            foreach ($extraFields as $field) {
                                if (isset($normalized['extra'][$field])) {
                                    $stmt->execute([
                                        $existing['id'],
                                        $field,
                                        $normalized['extra'][$field]
                                    ]);
                                }
                            }
                        }
                        
                        $updatedSolicitations++;
                    }
                } else {
                    // Insert new solicitation
                    $stmt = $pdo->prepare("
                        INSERT INTO opportunities 
                        (opp_no, title, agency, status, close_date, url, program_code, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $normalized['opp_no'],
                        $normalized['title'],
                        $normalized['agency'],
                        $normalized['status'],
                        $normalized['close_date'],
                        $normalized['url'],
                        $program['code']
                    ]);
                    $opportunityId = $pdo->lastInsertId();
                    
                    // Insert metadata if available
                    $extraFields = $adapter->extraFields();
                    if (!empty($extraFields) && !empty($normalized['extra'])) {
                        $stmt = $pdo->prepare("
                            INSERT INTO opportunity_meta (opportunity_id, meta_key, meta_value)
                            VALUES (?, ?, ?)
                        ");
                        
                        foreach ($extraFields as $field) {
                            if (isset($normalized['extra'][$field])) {
                                $stmt->execute([
                                    $opportunityId,
                                    $field,
                                    $normalized['extra'][$field]
                                ]);
                            }
                        }
                    }
                    
                    $newSolicitations++;
                }
                
                $totalSolicitations++;
            }
            
        } catch (Exception $e) {
            $errors[] = "Error ingesting {$program['code']}: " . $e->getMessage();
            error_log("Solicitations ingest error for {$program['code']}: " . $e->getMessage());
        }
    }
    
    // Clean up old/expired solicitations
    $stmt = $pdo->prepare("
        UPDATE opportunities 
        SET status = 'expired' 
        WHERE close_date < CURDATE() AND status NOT IN ('expired', 'awarded', 'cancelled')
    ");
    $stmt->execute();
    $expiredCount = $stmt->rowCount();
    
    // Log ingestion results
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (entity_type, entity_id, activity_type, activity_data, created_by, created_at)
        VALUES ('system', 'solicitations_ingest', 'ingest_completed', ?, 1, NOW())
    ");
    $stmt->execute([json_encode([
        'total_processed' => $totalSolicitations,
        'new_solicitations' => $newSolicitations,
        'updated_solicitations' => $updatedSolicitations,
        'expired_solicitations' => $expiredCount,
        'errors' => $errors,
        'ingest_time' => $startTime->format('c'),
        'timezone' => 'Asia/Dubai'
    ])]);
    
    echo "Solicitations ingest completed:\n";
    echo "- New: {$newSolicitations}\n";
    echo "- Updated: {$updatedSolicitations}\n";
    echo "- Expired: {$expiredCount}\n";
    echo "- Total processed: {$totalSolicitations}\n";
    
    if (!empty($errors)) {
        echo "Errors encountered: " . implode('; ', $errors) . "\n";
    }
    
} catch (Exception $e) {
    error_log("Solicitations ingest failed: " . $e->getMessage());
    echo "Solicitations ingest failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Solicitations ingest process completed at: " . (new DateTime())->format('Y-m-d H:i:s T') . "\n";