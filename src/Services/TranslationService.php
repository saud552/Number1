<?php

namespace Numbers1\Services;

use Numbers1\Core\Cache;

/**
 * Translation Service
 * 
 * Handles all translation and localization
 */
class TranslationService
{
    private static $instance = null;
    private $cache;
    private $translations = [];
    private $currentLanguage = 'ar';
    private $fallbackLanguage = 'en';
    
    private function __construct()
    {
        $this->cache = Cache::getInstance();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load translations for a language
     */
    private function loadLanguage(string $lang): bool
    {
        // Check cache first
        $cacheKey = "translations_{$lang}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            $this->translations[$lang] = $cached;
            return true;
        }
        
        // Load from file
        $filePath = __DIR__ . '/../../languages/' . $lang . '.json';
        
        if (!file_exists($filePath)) {
            return false;
        }
        
        $content = file_get_contents($filePath);
        $translations = json_decode($content, true);
        
        if ($translations === null) {
            return false;
        }
        
        $this->translations[$lang] = $translations;
        
        // Cache for 1 hour
        $this->cache->set($cacheKey, $translations, 3600);
        
        return true;
    }
    
    /**
     * Set current language
     */
    public function setLanguage(string $lang): void
    {
        $this->currentLanguage = $lang;
        
        // Load language if not already loaded
        if (!isset($this->translations[$lang])) {
            $this->loadLanguage($lang);
        }
    }
    
    /**
     * Get current language
     */
    public function getLanguage(): string
    {
        return $this->currentLanguage;
    }
    
    /**
     * Translate a key
     */
    public function trans(string $key, array $replacements = [], ?string $lang = null): string
    {
        $lang = $lang ?? $this->currentLanguage;
        
        // Load language if not loaded
        if (!isset($this->translations[$lang])) {
            if (!$this->loadLanguage($lang)) {
                // Try fallback language
                if ($lang !== $this->fallbackLanguage) {
                    return $this->trans($key, $replacements, $this->fallbackLanguage);
                }
                return $key;
            }
        }
        
        // Get translation
        $translation = $this->translations[$lang][$key] ?? null;
        
        // Fallback to English if not found
        if ($translation === null && $lang !== $this->fallbackLanguage) {
            return $this->trans($key, $replacements, $this->fallbackLanguage);
        }
        
        // If still not found, return key
        if ($translation === null) {
            return $key;
        }
        
        // Replace placeholders
        foreach ($replacements as $placeholder => $value) {
            $translation = str_replace('{' . $placeholder . '}', $value, $translation);
        }
        
        return $translation;
    }
    
    /**
     * Alias for trans()
     */
    public function t(string $key, array $replacements = [], ?string $lang = null): string
    {
        return $this->trans($key, $replacements, $lang);
    }
    
    /**
     * Check if language is RTL
     */
    public function isRTL(?string $lang = null): bool
    {
        $lang = $lang ?? $this->currentLanguage;
        $rtlLanguages = ['ar', 'fa'];
        return in_array($lang, $rtlLanguages);
    }
    
    /**
     * Get all available languages
     */
    public function getAvailableLanguages(): array
    {
        $config = \Config::getInstance();
        return $config->get('SUPPORTED_LANGUAGES', []);
    }
    
    /**
     * Get language name
     */
    public function getLanguageName(string $lang): string
    {
        $languages = $this->getAvailableLanguages();
        return $languages[$lang] ?? $lang;
    }
}
