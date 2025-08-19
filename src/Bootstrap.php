<?php
/**
 * Application Bootstrap - Initialize core services
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 */

namespace SamFedBiz;

use SamFedBiz\Config\EnvManager;
use SamFedBiz\Core\ProgramRegistry;
use SamFedBiz\Auth\AuthManager;
use PDO;
use PDOException;

class Bootstrap
{
    private static ?PDO $db = null;
    private static ?ProgramRegistry $programRegistry = null;
    private static ?AuthManager $authManager = null;

    /**
     * Initialize the application
     * @return bool Success
     */
    public static function init(): bool
    {
        try {
            // Load environment configuration
            if (!EnvManager::load()) {
                error_log("Failed to load environment configuration");
                return false;
            }

            // Set timezone
            date_default_timezone_set(EnvManager::getTimezone());

            // Initialize database connection
            self::initDatabase();

            // Initialize core services
            self::$programRegistry = new ProgramRegistry();
            self::$authManager = new AuthManager(self::$db);

            return true;
        } catch (Exception $e) {
            error_log("Bootstrap initialization failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize database connection
     */
    private static function initDatabase(): void
    {
        $host = EnvManager::get('DB_HOST', 'localhost');
        $dbname = EnvManager::get('DB_NAME', 'samfedbiz');
        $username = EnvManager::get('DB_USER', 'root');
        $password = EnvManager::get('DB_PASSWORD', '');

        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

        try {
            self::$db = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    /**
     * Get database connection
     * @return PDO
     */
    public static function getDB(): PDO
    {
        if (self::$db === null) {
            self::init();
        }
        return self::$db;
    }

    /**
     * Get program registry
     * @return ProgramRegistry
     */
    public static function getProgramRegistry(): ProgramRegistry
    {
        if (self::$programRegistry === null) {
            self::init();
        }
        return self::$programRegistry;
    }

    /**
     * Get auth manager
     * @return AuthManager
     */
    public static function getAuthManager(): AuthManager
    {
        if (self::$authManager === null) {
            self::init();
        }
        return self::$authManager;
    }

    /**
     * Check if application is properly initialized
     * @return bool
     */
    public static function isInitialized(): bool
    {
        return self::$db !== null && 
               self::$programRegistry !== null && 
               self::$authManager !== null;
    }
}