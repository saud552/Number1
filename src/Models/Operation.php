<?php

namespace Numbers1\Models;

use Numbers1\Core\Database;
use Numbers1\Core\Logger;

/**
 * Operation Model
 * 
 * Handles purchase operations
 */
class Operation
{
    private $db;
    private $logger;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Create new operation
     */
    public function create(array $data): int
    {
        try {
            $operationData = [
                'user_id' => $data['user_id'],
                'country_code' => $data['country_code'],
                'phone_number' => $data['phone_number'],
                'hash_code' => $data['hash_code'],
                'price' => $data['price'],
                'status' => 'pending'
            ];
            
            $id = $this->db->insert('operations', $operationData);
            
            $this->logger->info('Operation created', [
                'operation_id' => $id,
                'user_id' => $data['user_id']
            ]);
            
            return $id;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create operation', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Complete operation
     */
    public function complete(int $id, string $code, string $password): bool
    {
        try {
            $rowsAffected = $this->db->update(
                'operations',
                [
                    'code' => $code,
                    'password' => $password,
                    'status' => 'completed',
                    'completed_at' => date('Y-m-d H:i:s')
                ],
                'id = :id',
                ['id' => $id]
            );
            
            if ($rowsAffected > 0) {
                $this->logger->info('Operation completed', ['operation_id' => $id]);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to complete operation', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Mark operation as failed
     */
    public function fail(int $id): bool
    {
        try {
            $rowsAffected = $this->db->update(
                'operations',
                ['status' => 'failed'],
                'id = :id',
                ['id' => $id]
            );
            
            return $rowsAffected > 0;
        } catch (\Exception $e) {
            $this->logger->error('Failed to mark operation as failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Get operation by ID
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM operations WHERE id = :id LIMIT 1";
        return $this->db->fetch($sql, ['id' => $id]);
    }
    
    /**
     * Get user operations
     */
    public function getUserOperations(int $userId, int $limit = 10): array
    {
        $sql = "SELECT * FROM operations 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        return $this->db->fetchAll($sql, [
            'user_id' => $userId,
            'limit' => $limit
        ]);
    }
    
    /**
     * Get operation statistics
     */
    public function getStats(): array
    {
        $totalSql = "SELECT COUNT(*) as count FROM operations";
        $successSql = "SELECT COUNT(*) as count FROM operations WHERE status = 'completed'";
        $revenueSql = "SELECT SUM(price) as total FROM operations WHERE status = 'completed'";
        
        $total = $this->db->fetch($totalSql);
        $success = $this->db->fetch($successSql);
        $revenue = $this->db->fetch($revenueSql);
        
        return [
            'total_operations' => $total['count'] ?? 0,
            'successful_operations' => $success['count'] ?? 0,
            'total_revenue' => $revenue['total'] ?? 0.00
        ];
    }
}
