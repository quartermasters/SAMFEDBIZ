<?php
/**
 * Daily Brief Sender Cron Job
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Runs daily at 06:05 Dubai time to send the daily brief
 * Timezone: Asia/Dubai
 */

require_once '../src/Bootstrap.php';

use SamFedBiz\Config\EnvManager;

// Set timezone to Dubai
date_default_timezone_set('Asia/Dubai');

// Log start time
$startTime = new DateTime();
echo "Brief send started at: " . $startTime->format('Y-m-d H:i:s T') . "\n";

try {
    // Get today's brief
    $stmt = $pdo->prepare("
        SELECT id, title, content, sections, tags
        FROM daily_briefs 
        WHERE DATE(created_at) = CURDATE() AND sent_at IS NULL
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute();
    $brief = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$brief) {
        echo "No unsent brief found for today\n";
        exit(0);
    }
    
    // Get active subscribers
    $stmt = $pdo->prepare("
        SELECT email, name FROM subscribers 
        WHERE active = 1
    ");
    $stmt->execute();
    $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($subscribers)) {
        echo "No active subscribers found\n";
        exit(0);
    }
    
    // Initialize environment manager for SMTP settings
    $envManager = new EnvManager();
    
    // Send brief to subscribers
    $sentCount = 0;
    $failedCount = 0;
    $errors = [];
    
    foreach ($subscribers as $subscriber) {
        try {
            $emailSent = sendBriefEmail(
                $subscriber['email'],
                $subscriber['name'],
                $brief,
                $envManager
            );
            
            if ($emailSent) {
                $sentCount++;
            } else {
                $failedCount++;
            }
            
            // Small delay to avoid overwhelming SMTP server
            usleep(100000); // 0.1 second
            
        } catch (Exception $e) {
            $failedCount++;
            $errors[] = "Failed to send to {$subscriber['email']}: " . $e->getMessage();
            error_log("Email send error for {$subscriber['email']}: " . $e->getMessage());
        }
    }
    
    // Update brief with send status
    $stmt = $pdo->prepare("
        UPDATE daily_briefs 
        SET sent_at = NOW(), recipient_count = ?
        WHERE id = ?
    ");
    $stmt->execute([$sentCount, $brief['id']]);
    
    // Log send results
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (entity_type, entity_id, activity_type, activity_data, created_by, created_at)
        VALUES ('system', 'brief_send', 'send_completed', ?, 1, NOW())
    ");
    $stmt->execute([json_encode([
        'brief_id' => $brief['id'],
        'sent_count' => $sentCount,
        'failed_count' => $failedCount,
        'total_subscribers' => count($subscribers),
        'errors' => array_slice($errors, 0, 5), // Limit error log
        'send_time' => $startTime->format('c'),
        'timezone' => 'Asia/Dubai'
    ])]);
    
    echo "Brief send completed:\n";
    echo "- Brief ID: {$brief['id']}\n";
    echo "- Sent successfully: {$sentCount}\n";
    echo "- Failed: {$failedCount}\n";
    echo "- Total subscribers: " . count($subscribers) . "\n";
    
    if (!empty($errors)) {
        echo "Errors encountered: " . implode('; ', array_slice($errors, 0, 3)) . "\n";
    }
    
} catch (Exception $e) {
    error_log("Brief send failed: " . $e->getMessage());
    echo "Brief send failed: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Send brief email to subscriber
 */
function sendBriefEmail($email, $name, $brief, $envManager) {
    // Generate HTML email content
    $htmlContent = generateEmailHTML($brief, $name);
    $textContent = generateEmailText($brief, $name);
    
    // Email headers
    $subject = $brief['title'];
    $fromEmail = 'briefs@samfedbiz.com';
    $fromName = 'samfedbiz.com Federal BD Intelligence';
    
    // Use PHP's mail() function or configure SMTP
    $smtpHost = $envManager->get('SMTP_HOST');
    $smtpUser = $envManager->get('SMTP_USER');
    $smtpPass = $envManager->get('SMTP_PASS');
    
    if ($smtpHost && $smtpUser && $smtpPass) {
        return sendSMTPEmail($email, $name, $subject, $htmlContent, $textContent, $envManager);
    } else {
        return sendPHPMail($email, $name, $subject, $htmlContent, $textContent, $fromEmail, $fromName);
    }
}

/**
 * Send email via SMTP
 */
function sendSMTPEmail($to, $toName, $subject, $htmlContent, $textContent, $envManager) {
    $smtpHost = $envManager->get('SMTP_HOST');
    $smtpPort = $envManager->get('SMTP_PORT') ?: 587;
    $smtpUser = $envManager->get('SMTP_USER');
    $smtpPass = $envManager->get('SMTP_PASS');
    $smtpEncryption = $envManager->get('SMTP_ENCRYPTION') ?: 'tls';
    
    try {
        // Create SMTP connection
        $socket = fsockopen($smtpHost, $smtpPort, $errno, $errstr, 30);
        if (!$socket) {
            error_log("SMTP connection failed: {$errstr} ({$errno})");
            return false;
        }
        
        // Read server greeting
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) !== '220') {
            fclose($socket);
            return false;
        }
        
        // EHLO command
        fputs($socket, "EHLO {$smtpHost}\r\n");
        $response = fgets($socket, 512);
        
        // Start TLS if required
        if ($smtpEncryption === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 512);
            if (substr($response, 0, 3) === '220') {
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                fputs($socket, "EHLO {$smtpHost}\r\n");
                fgets($socket, 512);
            }
        }
        
        // Authentication
        if ($smtpUser && $smtpPass) {
            fputs($socket, "AUTH LOGIN\r\n");
            fgets($socket, 512);
            fputs($socket, base64_encode($smtpUser) . "\r\n");
            fgets($socket, 512);
            fputs($socket, base64_encode($smtpPass) . "\r\n");
            $response = fgets($socket, 512);
            if (substr($response, 0, 3) !== '235') {
                fclose($socket);
                return false;
            }
        }
        
        // Send email
        $fromEmail = 'briefs@samfedbiz.com';
        $fromName = 'samfedbiz.com Federal BD Intelligence';
        
        fputs($socket, "MAIL FROM: <{$fromEmail}>\r\n");
        fgets($socket, 512);
        fputs($socket, "RCPT TO: <{$to}>\r\n");
        fgets($socket, 512);
        fputs($socket, "DATA\r\n");
        fgets($socket, 512);
        
        // Email headers and content
        $boundary = uniqid('boundary_');
        $emailData = "From: {$fromName} <{$fromEmail}>\r\n";
        $emailData .= "To: {$toName} <{$to}>\r\n";
        $emailData .= "Subject: {$subject}\r\n";
        $emailData .= "MIME-Version: 1.0\r\n";
        $emailData .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $emailData .= "X-Mailer: samfedbiz.com Brief Sender\r\n\r\n";
        
        // Text part
        $emailData .= "--{$boundary}\r\n";
        $emailData .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $emailData .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $emailData .= $textContent . "\r\n\r\n";
        
        // HTML part
        $emailData .= "--{$boundary}\r\n";
        $emailData .= "Content-Type: text/html; charset=UTF-8\r\n";
        $emailData .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $emailData .= $htmlContent . "\r\n\r\n";
        $emailData .= "--{$boundary}--\r\n";
        $emailData .= ".\r\n";
        
        fputs($socket, $emailData);
        $response = fgets($socket, 512);
        
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return substr($response, 0, 3) === '250';
        
    } catch (Exception $e) {
        error_log("SMTP send error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email via PHP mail()
 */
function sendPHPMail($to, $toName, $subject, $htmlContent, $textContent, $fromEmail, $fromName) {
    $boundary = uniqid('boundary_');
    
    $headers = "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "X-Mailer: samfedbiz.com Brief Sender\r\n";
    
    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $textContent . "\r\n\r\n";
    
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $htmlContent . "\r\n\r\n";
    
    $message .= "--{$boundary}--\r\n";
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Generate HTML email content
 */
function generateEmailHTML($brief, $subscriberName) {
    $sections = json_decode($brief['sections'], true) ?: [];
    
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($brief['title']) . '</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; border-radius: 8px; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .header p { margin: 10px 0 0 0; opacity: 0.9; }
        .section { margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea; }
        .section h2 { color: #333; margin-top: 0; font-size: 20px; font-weight: 600; }
        .section h3 { color: #667eea; font-size: 16px; font-weight: 600; margin-top: 20px; margin-bottom: 10px; }
        .signals { background: #fff3cd; border-left-color: #ffc107; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .signals strong { color: #856404; }
        .confirmed { background: #d4edda; border-left: 4px solid #28a745; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .developing { background: #e2e3e5; border-left: 4px solid #6c757d; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .market-intelligence { background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .reliability-label { font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .footer { text-align: center; margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 8px; font-size: 12px; color: #6c757d; }
        .footer a { color: #667eea; text-decoration: none; }
        a { color: #667eea; }
        ul { padding-left: 20px; }
        li { margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . htmlspecialchars($brief['title']) . '</h1>
        <p>Your daily federal business development intelligence</p>
    </div>
    
    <p>Good morning ' . htmlspecialchars($subscriberName) . ',</p>
    <p>Here\'s your intelligence update for federal business development opportunities.</p>';

    foreach ($sections as $section) {
        if (!empty($section['content'])) {
            $html .= '<div class="section">';
            $html .= '<h2>' . htmlspecialchars($section['title']) . '</h2>';
            
            // Convert markdown-style content to HTML with reliability styling
            $content = $section['content'];
            
            // Apply reliability-based styling
            $content = preg_replace('/^### ✅ (.+)$/m', '<div class="confirmed"><span class="reliability-label">Confirmed</span><h3>$1</h3>', $content);
            $content = preg_replace('/^### 🔄 (.+)$/m', '</div><div class="developing"><span class="reliability-label">Developing</span><h3>$1</h3>', $content);
            $content = preg_replace('/^### 📊 (.+)$/m', '</div><div class="market-intelligence"><span class="reliability-label">Market Intelligence</span><h3>$1</h3>', $content);
            
            // Regular markdown conversion
            $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
            $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
            $content = preg_replace('/^\- (.+)$/m', '<li>$1</li>', $content);
            $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
            $content = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $content);
            $content = preg_replace('/\*\(Unverified\)\*/', '<em style="color: #856404; font-weight: bold;">(Unverified)</em>', $content);
            $content = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $content);
            $content = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $content);
            $content = nl2br($content);
            
            // Close any open divs
            if (strpos($content, '<div class="') !== false) {
                $content .= '</div>';
            }
            
            $html .= $content;
            
            // Add signals and rumors if present
            if (!empty($section['signals_and_rumors'])) {
                $html .= '<div class="signals">';
                $html .= '<strong>Signals & Rumors:</strong><br>';
                $html .= nl2br(htmlspecialchars($section['signals_and_rumors']));
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
    }

    $html .= '
    <div class="footer">
        <p><strong>About This Brief</strong></p>
        <p>This brief is generated daily at 06:00 Dubai time and covers TLS, OASIS+, SEWP, and general federal contracting opportunities. Information marked with 📊 represents signals and rumors that require verification.</p>
        <p><a href="https://samfedbiz.com/briefs">View Online</a> | <a href="https://samfedbiz.com/unsubscribe">Unsubscribe</a></p>
        <p style="margin-top: 20px; font-size: 11px;">
            © 2025 samfedbiz.com | Owner: Quartermasters FZC<br>
            All rights are reserved to AXIVAI.COM. The Project is Developed by AXIVAI.COM.
        </p>
    </div>
</body>
</html>';

    return $html;
}

/**
 * Generate plain text email content
 */
function generateEmailText($brief, $subscriberName) {
    $sections = json_decode($brief['sections'], true) ?: [];
    
    $text = $brief['title'] . "\n";
    $text .= str_repeat('=', strlen($brief['title'])) . "\n\n";
    $text .= "Good morning {$subscriberName},\n\n";
    $text .= "Here's your intelligence update for federal business development opportunities.\n\n";
    
    foreach ($sections as $section) {
        if (!empty($section['content'])) {
            $text .= strtoupper($section['title']) . "\n";
            $text .= str_repeat('-', strlen($section['title'])) . "\n\n";
            
            // Clean up markdown formatting for plain text
            $content = $section['content'];
            $content = preg_replace('/^### (.+)$/m', '$1:', $content);
            $content = preg_replace('/^## (.+)$/m', '$1:', $content);
            $content = preg_replace('/\*\*(.+?)\*\*/', '$1', $content);
            $content = preg_replace('/\[(.+?)\]\((.+?)\)/', '$1 ($2)', $content);
            
            $text .= $content . "\n\n";
            
            if (!empty($section['signals_and_rumors'])) {
                $text .= "SIGNALS & RUMORS:\n";
                $text .= $section['signals_and_rumors'] . "\n\n";
            }
        }
    }
    
    $text .= "---\n\n";
    $text .= "About This Brief:\n";
    $text .= "This brief is generated daily at 06:00 Dubai time and covers TLS, OASIS+, SEWP, and general federal contracting opportunities. Information marked with 📊 represents signals and rumors that require verification.\n\n";
    $text .= "View online: https://samfedbiz.com/briefs\n";
    $text .= "Unsubscribe: https://samfedbiz.com/unsubscribe\n\n";
    $text .= "© 2025 samfedbiz.com | Owner: Quartermasters FZC\n";
    $text .= "All rights are reserved to AXIVAI.COM. The Project is Developed by AXIVAI.COM.\n";
    
    return $text;
}

echo "Brief send process completed at: " . (new DateTime())->format('Y-m-d H:i:s T') . "\n";