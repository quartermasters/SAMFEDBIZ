<?php
/**
 * Environment Variables Manager - Secure handling of configuration
 * samfedbiz.com - Federal BD Platform
 * Owner: Quartermasters FZC
 * Stakeholder: AXIVAI.COM
 */

namespace SamFedBiz\Config;

class EnvManager
{
    private static array $config = [];
    private static bool $loaded = false;

    /**
     * Required environment variables
     */
    private const REQUIRED_VARS = [
        'APP_ENV',
        'TIMEZONE',
        'OPENAI_API_KEY',
        'GEMINI_API_KEY',
        'GOOGLE_CLIENT_ID',
        'GOOGLE_CLIENT_SECRET',
        'GOOGLE_REDIRECT_URI',
        'SMTP_HOST',
        'SMTP_USER',
        'SMTP_PASS'
    ];

    /**
     * Sensitive variables that should never be exposed client-side
     */
    private const SENSITIVE_VARS = [
        'OPENAI_API_KEY',
        'GEMINI_API_KEY',
        'GOOGLE_CLIENT_SECRET',
        'SMTP_PASS',
        'DB_PASSWORD'
    ];

    /**
     * Load environment variables
     * @param string $envFile Path to .env file
     * @return bool Success
     */
    public static function load(string $envFile = '.env'): bool
    {
        if (self::$loaded) {
            return true;
        }

        // Load from server environment first (Hostinger hPanel)
        self::loadFromEnvironment();

        // Load from .env file if exists (development)
        if (file_exists($envFile)) {
            self::loadFromFile($envFile);
        }

        self::$loaded = true;
        return self::validate();
    }

    /**
     * Load from server environment variables
     */
    private static function loadFromEnvironment(): void
    {
        foreach (self::REQUIRED_VARS as $var) {
            $value = getenv($var);
            if ($value !== false) {
                self::$config[$var] = $value;
            }
        }

        // Additional optional vars
        $optionalVars = [
            'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD',
            'TELEGRAM_BOT_TOKEN', 'RATE_LIMIT_PER_HOUR'
        ];

        foreach ($optionalVars as $var) {
            $value = getenv($var);
            if ($value !== false) {
                self::$config[$var] = $value;
            }
        }
    }

    /**
     * Load from .env file
     * @param string $envFile
     */
    private static function loadFromFile(string $envFile): void
    {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) {
                continue; // Skip comments
            }

            if (strpos($line, '=') === false) {
                continue; // Skip invalid lines
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\''); // Remove quotes

            if (!isset(self::$config[$key])) {
                self::$config[$key] = $value;
            }
        }
    }

    /**
     * Validate required environment variables
     * @return bool All required vars present
     */
    private static function validate(): bool
    {
        $missing = [];
        
        foreach (self::REQUIRED_VARS as $var) {
            if (!isset(self::$config[$var]) || empty(self::$config[$var])) {
                $missing[] = $var;
            }
        }

        if (!empty($missing)) {
            error_log("Missing required environment variables: " . implode(', ', $missing));
            return false;
        }

        return true;
    }

    /**
     * Get environment variable value
     * @param string $key Variable name
     * @param mixed $default Default value if not found
     * @return mixed Value
     */
    public static function get(string $key, $default = null)
    {
        if (!self::$loaded) {
            self::load();
        }

        return self::$config[$key] ?? $default;
    }

    /**
     * Check if variable exists
     * @param string $key Variable name
     * @return bool Exists
     */
    public static function has(string $key): bool
    {
        if (!self::$loaded) {
            self::load();
        }

        return isset(self::$config[$key]);
    }

    /**
     * Get masked value for display (never show sensitive data)
     * @param string $key Variable name
     * @return string Masked value or placeholder
     */
    public static function getMasked(string $key): string
    {
        if (!self::has($key)) {
            return '[NOT SET]';
        }

        if (in_array($key, self::SENSITIVE_VARS)) {
            $value = self::get($key);
            if (strlen($value) <= 8) {
                return str_repeat('*', strlen($value));
            }
            return substr($value, 0, 4) . str_repeat('*', strlen($value) - 8) . substr($value, -4);
        }

        return self::get($key);
    }

    /**
     * Get all configuration for display (with masking)
     * @return array Masked configuration
     */
    public static function getAllMasked(): array
    {
        if (!self::$loaded) {
            self::load();
        }

        $masked = [];
        foreach (self::$config as $key => $value) {
            $masked[$key] = self::getMasked($key);
        }

        return $masked;
    }

    /**
     * Check if current environment is production
     * @return bool Is production
     */
    public static function isProduction(): bool
    {
        return self::get('APP_ENV') === 'production';
    }

    /**
     * Check if current environment is development
     * @return bool Is development
     */
    public static function isDevelopment(): bool
    {
        return self::get('APP_ENV') === 'development';
    }

    /**
     * Get timezone setting
     * @return string Timezone
     */
    public static function getTimezone(): string
    {
        return self::get('TIMEZONE', 'Asia/Dubai');
    }

    /**
     * Prevent sensitive data from being exposed in client-side code
     * @param string $key Variable name
     * @return bool Is safe for client-side
     */
    public static function isSafeForClient(string $key): bool
    {
        return !in_array($key, self::SENSITIVE_VARS);
    }

    /**
     * Get client-safe configuration (excludes sensitive vars)
     * @return array Client-safe config
     */
    public static function getClientSafe(): array
    {
        if (!self::$loaded) {
            self::load();
        }

        $clientSafe = [];
        foreach (self::$config as $key => $value) {
            if (self::isSafeForClient($key)) {
                $clientSafe[$key] = $value;
            }
        }

        return $clientSafe;
    }
}