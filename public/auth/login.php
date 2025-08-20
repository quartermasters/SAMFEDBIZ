<?php
/**
 * Login Page
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 */

require_once __DIR__ . '/../../src/Bootstrap.php';

use SamFedBiz\Auth\AuthManager;

$authManager = new AuthManager($pdo);

// Redirect if already logged in
if ($authManager->isAuthenticated()) {
    header('Location: /');
    exit;
}

$error_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } else {
        if ($authManager->login($email, $password, $remember)) {
            $redirect = $_GET['redirect'] ?? '/';
            header('Location: ' . $redirect);
            exit;
        } else {
            $error_message = 'Invalid email or password.';
        }
    }
}

$page_title = "Sign In";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> | samfedbiz.com</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link rel="stylesheet" href="/styles/main.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
            padding: 2rem;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: saturate(180%) blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 16px;
            padding: 3rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-title {
            font-size: 2rem;
            font-weight: 600;
            color: var(--color-text-primary);
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
            color: var(--color-text-secondary);
            font-size: 0.875rem;
        }
        
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--color-text-primary);
            font-size: 0.875rem;
        }
        
        .form-input {
            padding: 0.875rem 1rem;
            border: 2px solid var(--color-border);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: white;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.1);
        }
        
        .form-checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-checkbox {
            width: 1rem;
            height: 1rem;
        }
        
        .error-message {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #DC2626;
            padding: 0.875rem;
            border-radius: 8px;
            font-size: 0.875rem;
            text-align: center;
        }
        
        .login-button {
            background: var(--color-primary);
            color: white;
            border: none;
            padding: 0.875rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .login-button:hover {
            background: var(--color-primary-dark);
        }
        
        .login-button:focus {
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--color-border);
            color: var(--color-text-secondary);
            font-size: 0.75rem;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1 class="login-title">Welcome Back</h1>
                <p class="login-subtitle">Sign in to access the Federal BD Platform</p>
            </div>
            
            <form class="login-form" method="POST">
                <?php if ($error_message): ?>
                <div class="error-message" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-input" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                           required
                           autocomplete="email">
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input" 
                           required
                           autocomplete="current-password">
                </div>
                
                <div class="form-checkbox-group">
                    <input type="checkbox" 
                           id="remember" 
                           name="remember" 
                           class="form-checkbox">
                    <label for="remember" class="form-label">Keep me signed in</label>
                </div>
                
                <button type="submit" class="login-button">Sign In</button>
            </form>
            
            <div class="login-footer">
                <p>&copy; 2025 samfedbiz.com | Owner: Quartermasters FZC | All rights are reserved to AXIVAI.COM. The Project is Developed by AXIVAI.COM.</p>
            </div>
        </div>
    </div>
</body>
</html>