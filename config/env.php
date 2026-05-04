<?php
/**
 * Environment Configuration Helper
 * Loads environment variables from .env file
 */

class EnvLoader {
    private static $loaded = false;
    private static $env = [];
    
    /**
     * Load environment variables from .env file
     */
    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }
        
        if ($path === null) {
            $path = dirname(__DIR__) . '/.env';
        }
        
        if (!file_exists($path)) {
            // If .env doesn't exist, create a default one
            self::createDefaultEnv($path);
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^"(.*)"$/', $value, $matches)) {
                    $value = $matches[1];
                } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                    $value = $matches[1];
                }
                
                self::$env[$key] = $value;
                
                // Also set as PHP environment variable
                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Get environment variable value
     */
    public static function get($key, $default = null) {
        self::load();
        
        // Check our loaded env first
        if (isset(self::$env[$key])) {
            return self::$env[$key];
        }
        
        // Check PHP environment
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        // Check $_ENV superglobal
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        return $default;
    }
    
    /**
     * Get OpenAI API Key
     */
    public static function getOpenAIKey() {
    $key = self::get('OPENAI_API_KEY');
    return $key ?: null;
    }
        

    
    /**
     * Check if OpenAI is configured
     */
    public static function isOpenAIConfigured() {
        $key = self::getOpenAIKey();
        return !empty($key) && $key !== 'YOUR_OPENAI_API_KEY_HERE';
    }
    
    /**
     * Create default .env file if it doesn't exist
     */
    private static function createDefaultEnv($path) {
        $defaultContent = '# OpenAI Configuration
OPENAI_API_KEY=YOUR_OPENAI_API_KEY_HERE

# Database Configuration
DB_HOST=localhost
DB_USERNAME=root
DB_PASSWORD=
DB_NAME=smart_meal_planner

# Application Settings
APP_NAME=Smart Meal Planner
APP_ENV=development
APP_DEBUG=true';
        
        file_put_contents($path, $defaultContent);
    }
}

// Auto-load environment variables when this file is included
EnvLoader::load();

/**
 * Helper function to get environment variables
 */
function env($key, $default = null) {
    return EnvLoader::get($key, $default);
}

/**
 * Helper function to get OpenAI API key
 */
function getOpenAIKey() {
    return EnvLoader::getOpenAIKey();
}
?>