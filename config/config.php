<?php
/**
 * Configuration Manager
 * 
 * Loads and manages all bot configurations
 */

class Config
{
    private static $instance = null;
    private $config = [];
    
    private function __construct()
    {
        $this->loadEnv();
        $this->loadDefaults();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load environment variables from .env file
     */
    private function loadEnv(): void
    {
        $envFile = __DIR__ . '/../.env';
        
        if (!file_exists($envFile)) {
            throw new Exception('.env file not found. Please copy .env.example to .env and configure it.');
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE
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
                
                $this->config[$key] = $value;
            }
        }
    }
    
    /**
     * Load default configurations
     */
    private function loadDefaults(): void
    {
        $this->config['APP_NAME'] = 'Numbers1 Bot';
        $this->config['APP_VERSION'] = '2.0.0';
        
        // Supported languages
        $this->config['SUPPORTED_LANGUAGES'] = [
            'ar' => 'العربية 🇸🇦',
            'en' => 'English 🇺🇸',
            'ru' => 'Русский 🇷🇺',
            'fa' => 'فارسى 🇮🇷',
            'zh-CN' => '简体中文 🇨🇳',
            'zh-TW' => '繁體中文 🇹🇼',
            'tr' => 'Türkçe 🇹🇷'
        ];
        
        // RTL Languages
        $this->config['RTL_LANGUAGES'] = ['ar', 'fa'];
    }
    
    /**
     * Get configuration value
     */
    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Set configuration value
     */
    public function set(string $key, $value): void
    {
        $this->config[$key] = $value;
    }
    
    /**
     * Check if key exists
     */
    public function has(string $key): bool
    {
        return isset($this->config[$key]);
    }
    
    /**
     * Get all configurations
     */
    public function all(): array
    {
        return $this->config;
    }
}
