<?php

namespace Numbers1\Core;

/**
 * Logger Class
 * 
 * Handles all logging operations
 */
class Logger
{
    private static $instance = null;
    private $logPath;
    private $logLevel;
    
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';
    
    private $levels = [
        self::DEBUG => 0,
        self::INFO => 1,
        self::NOTICE => 2,
        self::WARNING => 3,
        self::ERROR => 4,
        self::CRITICAL => 5,
        self::ALERT => 6,
        self::EMERGENCY => 7,
    ];
    
    private function __construct()
    {
        $config = \Config::getInstance();
        $this->logPath = __DIR__ . '/../../' . $config->get('LOG_PATH', 'logs/bot.log');
        $this->logLevel = $config->get('LOG_LEVEL', 'info');
        
        // Create log directory if not exists
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
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
     * Log a message
     */
    private function log(string $level, string $message, array $context = []): void
    {
        // Check if level should be logged
        if ($this->levels[$level] < $this->levels[$this->logLevel]) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextString = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        $logMessage = sprintf(
            "[%s] [%s] %s %s\n",
            $timestamp,
            strtoupper($level),
            $message,
            $contextString
        );
        
        file_put_contents($this->logPath, $logMessage, FILE_APPEND);
    }
    
    public function emergency(string $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }
    
    public function alert(string $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }
    
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }
    
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }
    
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }
    
    public function notice(string $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }
    
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }
    
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }
}
