<?php

namespace Numbers1\Core;

use PDO;
use PDOException;

/**
 * Database Manager
 * 
 * Handles all database operations using SQLite with PDO
 */
class Database
{
    private static $instance = null;
    private $pdo;
    private $config;
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->config = \Config::getInstance();
        $this->connect();
        $this->migrate();
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
     * Connect to database
     */
    private function connect(): void
    {
        try {
            $dbPath = __DIR__ . '/../../' . $this->config->get('DB_PATH', 'database/bot.db');
            $dbDir = dirname($dbPath);
            
            // Create database directory if not exists
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Enable foreign keys
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            
        } catch (PDOException $e) {
            Logger::getInstance()->error('Database connection failed: ' . $e->getMessage());
            throw new \Exception('Database connection failed');
        }
    }
    
    /**
     * Run migrations
     */
    private function migrate(): void
    {
        try {
            $migrationFile = __DIR__ . '/../../database/migrations/001_initial_schema.sql';
            
            if (file_exists($migrationFile)) {
                $sql = file_get_contents($migrationFile);
                $this->pdo->exec($sql);
            }
        } catch (PDOException $e) {
            Logger::getInstance()->error('Migration failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute query
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            Logger::getInstance()->error('Query failed: ' . $e->getMessage() . ' SQL: ' . $sql);
            throw $e;
        }
    }
    
    /**
     * Fetch single row
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Insert record
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return (int) $this->pdo->lastInsertId();
    }
    
    /**
     * Update record
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "{$column} = :{$column}";
        }
        $setString = implode(', ', $set);
        
        $sql = "UPDATE {$table} SET {$setString}, updated_at = CURRENT_TIMESTAMP WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Delete record
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }
    
    /**
     * Get PDO instance
     */
    public function getPDO(): PDO
    {
        return $this->pdo;
    }
}
