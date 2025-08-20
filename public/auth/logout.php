<?php
/**
 * Logout Handler
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 */

require_once __DIR__ . '/../../src/Bootstrap.php';

use SamFedBiz\Auth\AuthManager;

$authManager = new AuthManager($pdo);

// Logout user
$authManager->logout();

// Redirect to home or login page
header('Location: /auth/login.php');
exit;