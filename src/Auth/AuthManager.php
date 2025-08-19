<?php
/**
 * Authentication Manager - Handle user authentication and roles
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 */

namespace SamFedBiz\Auth;

use PDO;

class AuthManager
{
    private PDO $db;
    private array $currentUser = [];

    public const ROLE_ADMIN = 'admin';
    public const ROLE_OPS = 'ops';
    public const ROLE_VIEWER = 'viewer';

    public const ROLES = [
        self::ROLE_ADMIN => 'Administrator',
        self::ROLE_OPS => 'Operations',
        self::ROLE_VIEWER => 'Viewer'
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->startSession();
    }

    /**
     * Start secure session
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => true,
                'cookie_samesite' => 'Strict',
                'use_strict_mode' => true
            ]);
        }
    }

    /**
     * Authenticate user with email and password
     * @param string $email User email
     * @param string $password Plain text password
     * @return bool Success
     */
    public function authenticate(string $email, string $password): bool
    {
        $stmt = $this->db->prepare("
            SELECT id, name, email, role, pass_hash, is_active 
            FROM users 
            WHERE email = ? AND is_active = 1
        ");
        
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['pass_hash'])) {
            return false;
        }

        // Update last login
        $updateStmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];

        $this->currentUser = $user;

        return true;
    }

    /**
     * Log out current user
     */
    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        $this->currentUser = [];
    }

    /**
     * Check if user is authenticated
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get current user data
     * @return array|null
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        if (empty($this->currentUser)) {
            $stmt = $this->db->prepare("
                SELECT id, name, email, role, last_login, created_at 
                FROM users 
                WHERE id = ? AND is_active = 1
            ");
            
            $stmt->execute([$_SESSION['user_id']]);
            $this->currentUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        return $this->currentUser ?: null;
    }

    /**
     * Check if current user has specific role
     * @param string $role Required role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        return $_SESSION['user_role'] === $role;
    }

    /**
     * Check if current user has minimum role level
     * @param string $minRole Minimum required role
     * @return bool
     */
    public function hasMinRole(string $minRole): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        $roleHierarchy = [
            self::ROLE_VIEWER => 1,
            self::ROLE_OPS => 2,
            self::ROLE_ADMIN => 3
        ];

        $userLevel = $roleHierarchy[$_SESSION['user_role']] ?? 0;
        $minLevel = $roleHierarchy[$minRole] ?? 999;

        return $userLevel >= $minLevel;
    }

    /**
     * Create new user (admin only)
     * @param string $name User name
     * @param string $email User email
     * @param string $password Plain text password
     * @param string $role User role
     * @return bool Success
     */
    public function createUser(string $name, string $email, string $password, string $role = self::ROLE_VIEWER): bool
    {
        if (!$this->hasRole(self::ROLE_ADMIN)) {
            return false;
        }

        if (!in_array($role, array_keys(self::ROLES))) {
            return false;
        }

        $passHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, pass_hash, role, is_active) 
            VALUES (?, ?, ?, ?, 1)
        ");

        try {
            return $stmt->execute([$name, $email, $passHash, $role]);
        } catch (Exception $e) {
            error_log("User creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user password
     * @param int $userId User ID
     * @param string $newPassword New plain text password
     * @return bool Success
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        $currentUser = $this->getCurrentUser();
        
        // Users can update their own password, or admin can update any
        if (!$currentUser || ($currentUser['id'] != $userId && !$this->hasRole(self::ROLE_ADMIN))) {
            return false;
        }

        $passHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("UPDATE users SET pass_hash = ?, updated_at = NOW() WHERE id = ?");
        
        return $stmt->execute([$passHash, $userId]);
    }

    /**
     * Require authentication - redirect if not authenticated
     * @param string $redirectUrl Where to redirect if not authenticated
     */
    public function requireAuth(string $redirectUrl = '/login'): void
    {
        if (!$this->isAuthenticated()) {
            header("Location: $redirectUrl");
            exit;
        }
    }

    /**
     * Require specific role - return 403 if insufficient
     * @param string $requiredRole Required role
     */
    public function requireRole(string $requiredRole): void
    {
        $this->requireAuth();
        
        if (!$this->hasRole($requiredRole)) {
            http_response_code(403);
            echo "Access denied. Required role: " . (self::ROLES[$requiredRole] ?? $requiredRole);
            exit;
        }
    }

    /**
     * Require minimum role level
     * @param string $minRole Minimum required role
     */
    public function requireMinRole(string $minRole): void
    {
        $this->requireAuth();
        
        if (!$this->hasMinRole($minRole)) {
            http_response_code(403);
            echo "Access denied. Minimum role required: " . (self::ROLES[$minRole] ?? $minRole);
            exit;
        }
    }

    /**
     * Generate CSRF token
     * @return string
     */
    public function generateCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token
     * @param string $token Token to verify
     * @return bool Valid
     */
    public function verifyCsrfToken(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}