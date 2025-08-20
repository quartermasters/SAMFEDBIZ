<?php
/**
 * CSV Import Management
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 * 
 * Handles bulk CSV imports for holders, opportunities, research docs, etc.
 */

require_once __DIR__ . '/../../src/Bootstrap.php';

use SamFedBiz\Auth\AuthManager;
use SamFedBiz\Core\ProgramRegistry;

// Initialize managers
$authManager = new AuthManager($pdo);
$programRegistry = new ProgramRegistry($pdo);

// Check authentication and admin role
if (!$authManager->isAuthenticated()) {
    header('Location: /auth/login.php');
    exit;
}

$user = $authManager->getCurrentUser();

if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo "Access denied. CSV import requires admin role.";
    exit;
}

// Handle form submission
$import_result = null;
$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $import_type = $_POST['import_type'] ?? '';
    $program_code = $_POST['program_code'] ?? '';
    
    if (empty($import_type)) {
        $error_message = "Please select an import type.";
    } elseif (empty($_FILES['csv_file']['tmp_name'])) {
        $error_message = "Please select a CSV file to upload.";
    } else {
        try {
            $import_result = handleCsvImport($_FILES['csv_file'], $import_type, $program_code, $pdo, $user['id']);
        } catch (Exception $e) {
            $error_message = "Import failed: " . $e->getMessage();
            error_log("CSV import error: " . $e->getMessage());
        }
    }
}

// Get available programs (display codes => names)
$available_programs = [];
foreach ($programRegistry->getAvailablePrograms() as $code) {
    $display = \SamFedBiz\Core\ProgramRegistry::getDisplayCode($code);
    $adapter = $programRegistry->getAdapter($code);
    if ($adapter) {
        $available_programs[$display] = $adapter->name();
    }
}

// Page metadata
$page_title = "CSV Import Management";
$meta_description = "Bulk import data for holders, opportunities, and research documents.";

/**
 * Handle CSV import based on type
 */
function handleCsvImport($file, $import_type, $program_code, $pdo, $user_id) {
    $file_path = $file['tmp_name'];
    $file_size = $file['size'];
    
    // Validate file size (max 10MB)
    if ($file_size > 10 * 1024 * 1024) {
        throw new Exception("File too large. Maximum size is 10MB.");
    }
    
    // Validate file type
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($file_info, $file_path);
    finfo_close($file_info);
    
    if (!in_array($mime_type, ['text/csv', 'text/plain', 'application/csv'])) {
        throw new Exception("Invalid file type. Please upload a CSV file.");
    }
    
    // Parse CSV
    $csv_data = [];
    if (($handle = fopen($file_path, 'r')) !== false) {
        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new Exception("Invalid CSV format. No headers found.");
        }
        
        $row_count = 0;
        while (($data = fgetcsv($handle)) !== false && $row_count < 1000) { // Limit to 1000 rows
            if (count($data) === count($headers)) {
                $csv_data[] = array_combine($headers, $data);
            }
            $row_count++;
        }
        fclose($handle);
    } else {
        throw new Exception("Unable to read CSV file.");
    }
    
    if (empty($csv_data)) {
        throw new Exception("No valid data rows found in CSV.");
    }
    
    // Process import based on type
    switch ($import_type) {
        case 'holders':
            return importHolders($csv_data, $program_code, $pdo, $user_id);
            
        case 'opportunities':
            return importOpportunities($csv_data, $program_code, $pdo, $user_id);
            
        case 'research_docs':
            return importResearchDocs($csv_data, $pdo, $user_id);
            
        case 'subscribers':
            return importSubscribers($csv_data, $pdo, $user_id);
            
        default:
            throw new Exception("Invalid import type: " . $import_type);
    }
}

/**
 * Import holders/primes
 */
function importHolders($csv_data, $program_code, $pdo, $user_id) {
    $required_fields = ['name', 'full_name'];
    $imported = 0;
    $errors = [];
    
    foreach ($csv_data as $index => $row) {
        $row_num = $index + 2; // Account for header row
        
        // Validate required fields
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (empty($row[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            $errors[] = "Row {$row_num}: Missing required fields: " . implode(', ', $missing_fields);
            continue;
        }
        
        try {
            // Check if holder already exists
            $stmt = $pdo->prepare("SELECT id FROM holders WHERE name = ? AND program_code = ?");
            $stmt->execute([$row['name'], $program_code]);
            
            if ($stmt->fetchColumn()) {
                $errors[] = "Row {$row_num}: Holder '{$row['name']}' already exists for program {$program_code}";
                continue;
            }
            
            // Insert holder
            $stmt = $pdo->prepare("
                INSERT INTO holders (
                    name, full_name, program_code, location, status, capabilities, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $capabilities = !empty($row['capabilities']) ? explode('|', $row['capabilities']) : [];
            
            $stmt->execute([
                $row['name'],
                $row['full_name'],
                $program_code,
                $row['location'] ?? null,
                $row['status'] ?? 'active',
                json_encode($capabilities),
                $user_id
            ]);
            
            $holder_id = $pdo->lastInsertId();
            
            // Insert holder metadata if program-specific fields exist
            $meta_fields = [];
            switch ($program_code) {
                case 'sewp':
                    if (!empty($row['sewp_group'])) $meta_fields['sewp_group'] = $row['sewp_group'];
                    if (!empty($row['contract_number'])) $meta_fields['contract_number'] = $row['contract_number'];
                    if (!empty($row['naics_codes'])) $meta_fields['naics_codes'] = json_encode(explode('|', $row['naics_codes']));
                    if (!empty($row['psc_codes'])) $meta_fields['psc_codes'] = json_encode(explode('|', $row['psc_codes']));
                    if (!empty($row['oem_authorizations'])) $meta_fields['oem_authorizations'] = json_encode(explode('|', $row['oem_authorizations']));
                    break;
                    
                case 'oasis+':
                    if (!empty($row['pool'])) $meta_fields['pool'] = $row['pool'];
                    if (!empty($row['domains'])) $meta_fields['domains'] = json_encode(explode('|', $row['domains']));
                    if (!empty($row['contract_number'])) $meta_fields['contract_number'] = $row['contract_number'];
                    if (!empty($row['naics_codes'])) $meta_fields['naics_codes'] = json_encode(explode('|', $row['naics_codes']));
                    break;
            }
            
            if (!empty($meta_fields)) {
                $meta_keys = array_keys($meta_fields);
                $meta_values = array_values($meta_fields);
                $placeholders = str_repeat('?,', count($meta_fields) - 1) . '?';
                
                $stmt = $pdo->prepare("
                    INSERT INTO holder_meta (holder_id, " . implode(', ', $meta_keys) . ")
                    VALUES ({$holder_id}, {$placeholders})
                ");
                $stmt->execute($meta_values);
            }
            
            $imported++;
            
        } catch (Exception $e) {
            $errors[] = "Row {$row_num}: " . $e->getMessage();
        }
    }
    
    return [
        'type' => 'holders',
        'imported' => $imported,
        'total_rows' => count($csv_data),
        'errors' => $errors
    ];
}

/**
 * Import opportunities
 */
function importOpportunities($csv_data, $program_code, $pdo, $user_id) {
    $required_fields = ['opp_no', 'title', 'agency'];
    $imported = 0;
    $errors = [];
    
    foreach ($csv_data as $index => $row) {
        $row_num = $index + 2;
        
        // Validate required fields
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (empty($row[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            $errors[] = "Row {$row_num}: Missing required fields: " . implode(', ', $missing_fields);
            continue;
        }
        
        try {
            // Check if opportunity already exists
            $stmt = $pdo->prepare("SELECT id FROM opportunities WHERE opp_no = ?");
            $stmt->execute([$row['opp_no']]);
            
            if ($stmt->fetchColumn()) {
                $errors[] = "Row {$row_num}: Opportunity '{$row['opp_no']}' already exists";
                continue;
            }
            
            // Parse close date
            $close_date = null;
            if (!empty($row['close_date'])) {
                $close_date = date('Y-m-d H:i:s', strtotime($row['close_date']));
                if (!$close_date) {
                    $errors[] = "Row {$row_num}: Invalid close_date format";
                    continue;
                }
            }
            
            // Insert opportunity
            $stmt = $pdo->prepare("
                INSERT INTO opportunities (
                    opp_no, title, agency, description, status, type, close_date, url, program_code, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $row['opp_no'],
                $row['title'],
                $row['agency'],
                $row['description'] ?? null,
                $row['status'] ?? 'open',
                $row['type'] ?? 'solicitation',
                $close_date,
                $row['url'] ?? null,
                $program_code,
                $user_id
            ]);
            
            $imported++;
            
        } catch (Exception $e) {
            $errors[] = "Row {$row_num}: " . $e->getMessage();
        }
    }
    
    return [
        'type' => 'opportunities',
        'imported' => $imported,
        'total_rows' => count($csv_data),
        'errors' => $errors
    ];
}

/**
 * Import research documents
 */
function importResearchDocs($csv_data, $pdo, $user_id) {
    $required_fields = ['title', 'source_url'];
    $imported = 0;
    $errors = [];
    
    foreach ($csv_data as $index => $row) {
        $row_num = $index + 2;
        
        // Validate required fields
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (empty($row[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            $errors[] = "Row {$row_num}: Missing required fields: " . implode(', ', $missing_fields);
            continue;
        }
        
        try {
            // Check if document already exists
            $stmt = $pdo->prepare("SELECT id FROM research_docs WHERE source_url = ?");
            $stmt->execute([$row['source_url']]);
            
            if ($stmt->fetchColumn()) {
                $errors[] = "Row {$row_num}: Document with URL '{$row['source_url']}' already exists";
                continue;
            }
            
            // Parse tags
            $tags = [];
            if (!empty($row['tags'])) {
                $tags = explode('|', $row['tags']);
            }
            
            // Insert research document
            $stmt = $pdo->prepare("
                INSERT INTO research_docs (
                    title, doc_type, content, source_url, tags, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $row['title'],
                $row['doc_type'] ?? 'document',
                $row['content'] ?? null,
                $row['source_url'],
                json_encode($tags),
                $user_id
            ]);
            
            $imported++;
            
        } catch (Exception $e) {
            $errors[] = "Row {$row_num}: " . $e->getMessage();
        }
    }
    
    return [
        'type' => 'research_docs',
        'imported' => $imported,
        'total_rows' => count($csv_data),
        'errors' => $errors
    ];
}

/**
 * Import subscribers
 */
function importSubscribers($csv_data, $pdo, $user_id) {
    $required_fields = ['email', 'name'];
    $imported = 0;
    $errors = [];
    
    foreach ($csv_data as $index => $row) {
        $row_num = $index + 2;
        
        // Validate required fields
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (empty($row[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            $errors[] = "Row {$row_num}: Missing required fields: " . implode(', ', $missing_fields);
            continue;
        }
        
        // Validate email
        if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Row {$row_num}: Invalid email format";
            continue;
        }
        
        try {
            // Check if subscriber already exists
            $stmt = $pdo->prepare("SELECT id FROM subscribers WHERE email = ?");
            $stmt->execute([$row['email']]);
            
            if ($stmt->fetchColumn()) {
                $errors[] = "Row {$row_num}: Subscriber '{$row['email']}' already exists";
                continue;
            }
            
            // Insert subscriber
            $stmt = $pdo->prepare("
                INSERT INTO subscribers (
                    email, name, status, subscribed_programs, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $programs = !empty($row['programs']) ? explode('|', $row['programs']) : ['tls', 'oasis+', 'sewp'];
            
            $stmt->execute([
                $row['email'],
                $row['name'],
                $row['status'] ?? 'confirmed',
                json_encode($programs)
            ]);
            
            $imported++;
            
        } catch (Exception $e) {
            $errors[] = "Row {$row_num}: " . $e->getMessage();
        }
    }
    
    return [
        'type' => 'subscribers',
        'imported' => $imported,
        'total_rows' => count($csv_data),
        'errors' => $errors
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <title><?php echo htmlspecialchars($page_title); ?> | samfedbiz.com</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link rel="stylesheet" href="/styles/main.css">
    <link rel="stylesheet" href="/styles/accessibility.css">
    
    <!-- External Libraries -->
    <script src="https://unpkg.com/gsap@3.12.2/dist/gsap.min.js"></script>
    <script src="https://unpkg.com/gsap@3.12.2/dist/ScrollTrigger.min.js"></script>
    <script src="https://unpkg.com/@studio-freight/lenis@1.0.27/dist/lenis.min.js"></script>
</head>
<body class="import-management">
    <!-- Skip Link -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Navigation -->
    <nav class="nav-main" role="navigation" aria-label="Main navigation">
        <div class="nav-container">
            <div class="nav-brand">
                <a href="/" class="nav-brand-link" aria-label="samfedbiz.com home">
                    <span class="nav-brand-text">samfedbiz</span>
                </a>
            </div>
            
            <div class="nav-links">
                <a href="/" class="nav-link">Dashboard</a>
                <a href="/programs/tls" class="nav-link">TLS</a>
                <a href="/programs/oasis+" class="nav-link">OASIS+</a>
                <a href="/programs/sewp" class="nav-link">SEWP</a>
                <a href="/briefs" class="nav-link">Briefs</a>
                <a href="/research" class="nav-link">Research</a>
                <a href="/analytics" class="nav-link">Analytics</a>
                <a href="/settings" class="nav-link active">Settings</a>
            </div>
            
            <div class="nav-user">
                <span class="nav-user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                <span class="nav-user-role"><?php echo htmlspecialchars($user['role']); ?></span>
                <a href="/auth/logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main id="main-content" class="import-main">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <!-- Breadcrumb -->
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <ol class="breadcrumb-list">
                        <li><a href="/">Dashboard</a></li>
                        <li><a href="/settings">Settings</a></li>
                        <li>CSV Import</li>
                    </ol>
                </nav>

                <div class="page-header-content">
                    <div class="page-meta">
                        <div class="page-icon" aria-hidden="true">
                            <!-- Upload icon -->
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10,9 9,9 8,9"/>
                            </svg>
                        </div>
                        <div class="page-info">
                            <h1 class="page-title">CSV Import Management</h1>
                            <p class="page-description">Bulk import data for holders, opportunities, and research documents</p>
                        </div>
                    </div>
                    
                    <div class="page-actions">
                        <a href="/admin/templates" class="btn-secondary">
                            <!-- Download icon -->
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-15a2 2 0 0 1 2-2h4"/>
                                <polyline points="7,10 12,15 17,10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Download Templates
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Import Results -->
        <?php if ($import_result): ?>
        <section class="import-results">
            <div class="container">
                <div class="result-card glassmorphism success">
                    <div class="result-header">
                        <h2 class="result-title">Import Completed</h2>
                        <div class="result-stats">
                            <span class="result-stat">
                                <strong><?php echo $import_result['imported']; ?></strong> of <?php echo $import_result['total_rows']; ?> rows imported
                            </span>
                        </div>
                    </div>
                    
                    <?php if (!empty($import_result['errors'])): ?>
                    <div class="result-errors">
                        <h3 class="errors-title">Errors (<?php echo count($import_result['errors']); ?>):</h3>
                        <ul class="errors-list">
                            <?php foreach (array_slice($import_result['errors'], 0, 10) as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                            <?php if (count($import_result['errors']) > 10): ?>
                            <li><em>... and <?php echo count($import_result['errors']) - 10; ?> more errors</em></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Import Error -->
        <?php if ($error_message): ?>
        <section class="import-results">
            <div class="container">
                <div class="result-card glassmorphism error">
                    <div class="result-header">
                        <h2 class="result-title">Import Failed</h2>
                    </div>
                    <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Import Form -->
        <section class="import-form-section">
            <div class="container">
                <form class="import-form glassmorphism" method="POST" enctype="multipart/form-data">
                    <div class="form-header">
                        <h2 class="form-title">Upload CSV File</h2>
                        <p class="form-description">Select the type of data to import and upload your CSV file</p>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="import_type" class="form-label">Import Type</label>
                            <select id="import_type" name="import_type" class="form-select" required>
                                <option value="">Select import type...</option>
                                <option value="holders">Contract Holders/Primes</option>
                                <option value="opportunities">Opportunities</option>
                                <option value="research_docs">Research Documents</option>
                                <option value="subscribers">Newsletter Subscribers</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="program-group" style="display: none;">
                            <label for="program_code" class="form-label">Program (for holders/opportunities)</label>
                            <select id="program_code" name="program_code" class="form-select">
                                <option value="">Select program...</option>
                                <?php foreach ($available_programs as $code => $name): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group file-upload-group">
                            <label for="csv_file" class="form-label">CSV File</label>
                            <div class="file-upload-container">
                                <input type="file" id="csv_file" name="csv_file" accept=".csv,.txt" class="file-input" required>
                                <label for="csv_file" class="file-upload-label">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14,2 14,8 20,8"/>
                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                    </svg>
                                    <span class="file-upload-text">Choose CSV file or drag here</span>
                                    <span class="file-upload-hint">Maximum file size: 10MB</span>
                                </label>
                                <span class="file-name" id="file-name"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <!-- Upload icon -->
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17,8 12,3 7,8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            Import CSV
                        </button>
                    </div>
                </form>
                
                <!-- Import Guidelines -->
                <div class="import-guidelines glassmorphism">
                    <h3 class="guidelines-title">Import Guidelines</h3>
                    <div class="guidelines-grid">
                        <div class="guideline-item">
                            <h4>Holders/Primes</h4>
                            <p>Required: name, full_name</p>
                            <p>Optional: location, status, capabilities (pipe-separated), contract_number, sewp_group, naics_codes, psc_codes, oem_authorizations</p>
                        </div>
                        
                        <div class="guideline-item">
                            <h4>Opportunities</h4>
                            <p>Required: opp_no, title, agency</p>
                            <p>Optional: description, status, type, close_date, url</p>
                        </div>
                        
                        <div class="guideline-item">
                            <h4>Research Documents</h4>
                            <p>Required: title, source_url</p>
                            <p>Optional: doc_type, content, tags (pipe-separated)</p>
                        </div>
                        
                        <div class="guideline-item">
                            <h4>Subscribers</h4>
                            <p>Required: email, name</p>
                            <p>Optional: status, programs (pipe-separated)</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 samfedbiz.com | Owner: Quartermasters FZC | All rights are reserved to AXIVAI.COM. The Project is Developed by AXIVAI.COM.</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="/js/animations.js"></script>
    <script src="/js/smooth-scroll.js"></script>
    <script src="/js/contrast-check.js"></script>
    <script src="/js/accessibility.js"></script>
    <script src="/js/accessibility-core.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const importTypeSelect = document.getElementById('import_type');
            const programGroup = document.getElementById('program-group');
            const fileInput = document.getElementById('csv_file');
            const fileName = document.getElementById('file-name');
            
            // Show/hide program selection based on import type
            importTypeSelect.addEventListener('change', function() {
                if (this.value === 'holders' || this.value === 'opportunities') {
                    programGroup.style.display = 'block';
                    document.getElementById('program_code').required = true;
                } else {
                    programGroup.style.display = 'none';
                    document.getElementById('program_code').required = false;
                }
            });
            
            // File upload handling
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    fileName.textContent = this.files[0].name;
                    fileName.style.display = 'inline';
                } else {
                    fileName.style.display = 'none';
                }
            });
            
            // Initialize animations
            if (typeof gsap !== 'undefined') {
                gsap.registerPlugin(ScrollTrigger);
                
                gsap.from('.reveal', {
                    y: 30,
                    opacity: 0,
                    duration: 0.6,
                    stagger: 0.08,
                    scrollTrigger: {
                        trigger: '.reveal',
                        start: 'top 85%',
                        toggleActions: 'play none none reverse'
                    }
                });
            }
        });
    </script>
</body>
</html>
