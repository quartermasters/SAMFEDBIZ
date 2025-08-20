<?php
/**
 * Google Drive Service
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Handles readonly Google Drive integration for document sync
 */

namespace SamFedBiz\Services;

use SamFedBiz\Config\EnvManager;
use Exception;

class GoogleDriveService
{
    private $envManager;
    private $accessToken;
    private $refreshToken;
    private $clientId;
    private $clientSecret;
    private $redirectUri;

    public function __construct(EnvManager $envManager)
    {
        $this->envManager = $envManager;
        $this->clientId = $envManager->get('GOOGLE_CLIENT_ID');
        $this->clientSecret = $envManager->get('GOOGLE_CLIENT_SECRET');
        $this->redirectUri = $envManager->get('GOOGLE_REDIRECT_URI');
    }

    /**
     * Set OAuth tokens for API access
     */
    public function setTokens($accessToken, $refreshToken = null)
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
    }

    /**
     * List documents from Google Drive with metadata
     * @param array $options Query options (pageSize, query, etc.)
     * @return array Array of document metadata
     */
    public function listDocuments($options = [])
    {
        if (!$this->accessToken) {
            throw new Exception('Access token not set');
        }

        $query = $options['query'] ?? "mimeType contains 'document' or mimeType='application/pdf'";
        $pageSize = min($options['pageSize'] ?? 100, 1000); // Max 1000 per Google's limit
        $pageToken = $options['pageToken'] ?? null;

        $params = [
            'q' => $query,
            'pageSize' => $pageSize,
            'fields' => 'nextPageToken, files(id, name, mimeType, size, modifiedTime, webViewLink, webContentLink, description, parents)',
            'orderBy' => 'modifiedTime desc'
        ];

        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }

        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query($params);
        
        $response = $this->makeApiRequest($url);
        
        if (!$response) {
            throw new Exception('Failed to fetch documents from Google Drive');
        }

        return [
            'files' => $this->normalizeDocuments($response['files'] ?? []),
            'nextPageToken' => $response['nextPageToken'] ?? null
        ];
    }

    /**
     * Get document content for AI processing
     * @param string $fileId Google Drive file ID
     * @return string Document content
     */
    public function getDocumentContent($fileId)
    {
        if (!$this->accessToken) {
            throw new Exception('Access token not set');
        }

        // First, get file metadata to determine export format
        $metadataUrl = "https://www.googleapis.com/drive/v3/files/{$fileId}?fields=mimeType,name";
        $metadata = $this->makeApiRequest($metadataUrl);
        
        if (!$metadata) {
            throw new Exception('Failed to fetch document metadata');
        }

        $mimeType = $metadata['mimeType'];
        $content = '';

        // Export based on MIME type
        if (strpos($mimeType, 'google-apps.document') !== false) {
            // Google Docs - export as plain text
            $exportUrl = "https://www.googleapis.com/drive/v3/files/{$fileId}/export?mimeType=text/plain";
            $content = $this->makeApiRequest($exportUrl, true);
        } elseif (strpos($mimeType, 'google-apps.presentation') !== false) {
            // Google Slides - export as plain text
            $exportUrl = "https://www.googleapis.com/drive/v3/files/{$fileId}/export?mimeType=text/plain";
            $content = $this->makeApiRequest($exportUrl, true);
        } elseif (strpos($mimeType, 'google-apps.spreadsheet') !== false) {
            // Google Sheets - export as CSV
            $exportUrl = "https://www.googleapis.com/drive/v3/files/{$fileId}/export?mimeType=text/csv";
            $content = $this->makeApiRequest($exportUrl, true);
        } elseif ($mimeType === 'application/pdf') {
            // PDF files - just get metadata for now (content extraction requires additional processing)
            $content = "PDF document: {$metadata['name']}";
        } else {
            // Other document types - try to download directly
            $downloadUrl = "https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media";
            $content = $this->makeApiRequest($downloadUrl, true);
        }

        return $content ?: '';
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken()
    {
        if (!$this->refreshToken) {
            throw new Exception('Refresh token not available');
        }

        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Failed to refresh access token');
        }

        $tokenData = json_decode($response, true);
        
        if (!$tokenData || !isset($tokenData['access_token'])) {
            throw new Exception('Invalid token response');
        }

        $this->accessToken = $tokenData['access_token'];
        
        return $tokenData['access_token'];
    }

    /**
     * Normalize document data to standard format
     */
    private function normalizeDocuments($files)
    {
        $normalized = [];

        foreach ($files as $file) {
            $normalized[] = [
                'id' => $file['id'],
                'title' => $file['name'],
                'mime_type' => $file['mimeType'],
                'size' => $file['size'] ?? null,
                'modified_time' => $file['modifiedTime'],
                'web_view_link' => $file['webViewLink'],
                'web_content_link' => $file['webContentLink'] ?? null,
                'description' => $file['description'] ?? '',
                'parents' => $file['parents'] ?? [],
                'doc_type' => $this->inferDocumentType($file['mimeType'], $file['name']),
                'source' => 'google_drive'
            ];
        }

        return $normalized;
    }

    /**
     * Infer document type from MIME type and filename
     */
    private function inferDocumentType($mimeType, $filename)
    {
        // Map MIME types to document types
        $typeMap = [
            'application/vnd.google-apps.document' => 'Google Doc',
            'application/vnd.google-apps.presentation' => 'Google Slides',
            'application/vnd.google-apps.spreadsheet' => 'Google Sheets',
            'application/pdf' => 'PDF',
            'application/msword' => 'Word Document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word Document',
            'application/vnd.ms-powerpoint' => 'PowerPoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PowerPoint',
            'application/vnd.ms-excel' => 'Excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel',
            'text/plain' => 'Text File',
            'text/csv' => 'CSV File'
        ];

        if (isset($typeMap[$mimeType])) {
            return $typeMap[$mimeType];
        }

        // Fallback to filename extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $extensionMap = [
            'pdf' => 'PDF',
            'doc' => 'Word Document',
            'docx' => 'Word Document',
            'ppt' => 'PowerPoint',
            'pptx' => 'PowerPoint',
            'xls' => 'Excel',
            'xlsx' => 'Excel',
            'txt' => 'Text File',
            'csv' => 'CSV File',
            'md' => 'Markdown',
            'rtf' => 'Rich Text'
        ];

        return $extensionMap[$extension] ?? 'Unknown';
    }

    /**
     * Make API request to Google Drive
     */
    private function makeApiRequest($url, $returnRaw = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 401 && $this->refreshToken) {
            // Token expired, try to refresh
            try {
                $this->refreshAccessToken();
                return $this->makeApiRequest($url, $returnRaw); // Retry with new token
            } catch (Exception $e) {
                throw new Exception('Authentication failed: ' . $e->getMessage());
            }
        }

        if ($httpCode !== 200) {
            error_log("Google Drive API error: HTTP {$httpCode} for URL {$url}");
            return false;
        }

        if ($returnRaw) {
            return $response;
        }

        return json_decode($response, true);
    }

    /**
     * Check if service is properly configured
     */
    public function isConfigured()
    {
        return !empty($this->clientId) && 
               !empty($this->clientSecret) && 
               !empty($this->redirectUri);
    }

    /**
     * Get OAuth authorization URL
     */
    public function getAuthUrl($state = null)
    {
        if (!$this->isConfigured()) {
            throw new Exception('Google Drive service not configured');
        }

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'https://www.googleapis.com/auth/drive.readonly',
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens
     */
    public function exchangeAuthCode($authCode)
    {
        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
            'code' => $authCode
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Failed to exchange authorization code');
        }

        $tokenData = json_decode($response, true);
        
        if (!$tokenData || !isset($tokenData['access_token'])) {
            throw new Exception('Invalid token response');
        }

        return $tokenData;
    }
}