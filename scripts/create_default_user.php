<?php
/**
 * Create Default User for samfedbiz Platform
 * Creates admin user: admin@samfedbiz.com / password123
 */

require_once __DIR__ . '/../src/Bootstrap.php';

use SamFedBiz\Auth\AuthManager;

echo "Creating default user for samfedbiz platform...\n";

try {
    // Create admin user directly in database
    $email = 'admin@samfedbiz.com';
    $password = 'password123';
    $name = 'Platform Administrator';
    $role = 'admin';
    
    $passHash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO users (name, email, pass_hash, role, is_active, created_at) 
        VALUES (?, ?, ?, ?, 1, NOW())
    ");
    
    $result = $stmt->execute([$name, $email, $passHash, $role]);
    
    if ($result) {
        echo "✅ Default admin user created successfully!\n";
        echo "   Email: $email\n";
        echo "   Password: $password\n";
        echo "   Role: $role\n";
        echo "\n";
        echo "⚠️  IMPORTANT: Please change this password after first login!\n";
    } else {
        echo "ℹ️  User may already exist or there was an error.\n";
    }
    
    // Verify the user exists
    $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "\n✅ User verification successful:\n";
        echo "   ID: {$user['id']}\n";
        echo "   Name: {$user['name']}\n";
        echo "   Email: {$user['email']}\n";
        echo "   Role: {$user['role']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error creating user: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🚀 You can now login at http://localhost:8090/auth/login.php\n";