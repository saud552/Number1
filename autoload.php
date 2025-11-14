<?php
/**
 * Autoloader for Numbers1 Bot
 * 
 * PSR-4 compliant autoloader
 */

spl_autoload_register(function ($class) {
    // Base namespace
    $prefix = 'Numbers1\\';
    
    // Base directory
    $baseDir = __DIR__ . '/src/';
    
    // Check if class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // If it's the Config class (special case)
        if ($class === 'Config') {
            $file = __DIR__ . '/config/config.php';
            if (file_exists($file)) {
                require $file;
            }
            return;
        }
        return;
    }
    
    // Get relative class name
    $relativeClass = substr($class, $len);
    
    // Replace namespace separators with directory separators
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    // If file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
