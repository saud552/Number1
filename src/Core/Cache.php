<?php

namespace Numbers1\Core;

/**
 * Cache Manager
 * 
 * Simple file-based caching system
 */
class Cache
{
    private static $instance = null;
    private $cacheDir;
    private $enabled;
    private $ttl;
    
    private function __construct()
    {
        $config = \Config::getInstance();
        $this->cacheDir = __DIR__ . '/../../cache/';
        $this->enabled = $config->get('CACHE_ENABLED', 'true') === 'true';
        $this->ttl = (int) $config->get('CACHE_TTL', 3600);
        
        // Create cache directory if not exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get cache file path
     */
    private function getFilePath(string $key): string
    {
        return $this->cacheDir . md5($key) . '.cache';
    }
    
    /**
     * Get cached value
     */
    public function get(string $key, $default = null)
    {
        if (!$this->enabled) {
            return $default;
        }
        
        $filePath = $this->getFilePath($key);
        
        if (!file_exists($filePath)) {
            return $default;
        }
        
        $data = unserialize(file_get_contents($filePath));
        
        // Check if expired
        if ($data['expires_at'] < time()) {
            $this->delete($key);
            return $default;
        }
        
        return $data['value'];
    }
    
    /**
     * Set cached value
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        if (!$this->enabled) {
            return false;
        }
        
        $ttl = $ttl ?? $this->ttl;
        $filePath = $this->getFilePath($key);
        
        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl
        ];
        
        return file_put_contents($filePath, serialize($data)) !== false;
    }
    
    /**
     * Delete cached value
     */
    public function delete(string $key): bool
    {
        $filePath = $this->getFilePath($key);
        
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        
        return false;
    }
    
    /**
     * Clear all cache
     */
    public function clear(): bool
    {
        $files = glob($this->cacheDir . '*.cache');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        return true;
    }
    
    /**
     * Check if key exists and is valid
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
    
    /**
     * Remember: Get from cache or execute callback and cache result
     */
    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
}
