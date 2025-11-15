<?php

namespace Numbers1\Models;

use Numbers1\Core\Database;
use Numbers1\Core\Logger;

/**
 * User Model
 * 
 * Handles all user-related database operations
 */
class User
{
    private $db;
    private $logger;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Find user by Telegram ID
     */
    public function findByTelegramId(int $telegramId): ?array
    {
        $sql = "SELECT * FROM users WHERE telegram_id = :telegram_id LIMIT 1";
        return $this->db->fetch($sql, ['telegram_id' => $telegramId]);
    }
    
    /**
     * Create new user
     */
    public function create(array $data): int
    {
        $userData = [
            'telegram_id' => $data['telegram_id'],
            'username' => $data['username'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'language' => $data['language'] ?? 'ar',
            'points' => $data['points'] ?? 0.00
        ];
        
        try {
            $userId = $this->db->insert('users', $userData);
            $this->logger->info('User created', ['user_id' => $userId, 'telegram_id' => $data['telegram_id']]);
            return $userId;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create user', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Update user
     */
    public function update(int $id, array $data): bool
    {
        try {
            $rowsAffected = $this->db->update('users', $data, 'id = :id', ['id' => $id]);
            return $rowsAffected > 0;
        } catch (\Exception $e) {
            $this->logger->error('Failed to update user', ['user_id' => $id, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Get user points
     */
    public function getPoints(int $telegramId): float
    {
        $user = $this->findByTelegramId($telegramId);
        return $user ? (float) $user['points'] : 0.00;
    }
    
    /**
     * Add points
     */
    public function addPoints(int $telegramId, float $amount): bool
    {
        $sql = "UPDATE users SET points = points + :amount, updated_at = CURRENT_TIMESTAMP 
                WHERE telegram_id = :telegram_id";
        
        try {
            $this->db->query($sql, [
                'amount' => $amount,
                'telegram_id' => $telegramId
            ]);
            $this->logger->info('Points added', ['telegram_id' => $telegramId, 'amount' => $amount]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to add points', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Deduct points
     */
    public function deductPoints(int $telegramId, float $amount): bool
    {
        $sql = "UPDATE users SET points = points - :amount, updated_at = CURRENT_TIMESTAMP 
                WHERE telegram_id = :telegram_id AND points >= :amount";
        
        try {
            $stmt = $this->db->query($sql, [
                'amount' => $amount,
                'telegram_id' => $telegramId
            ]);
            
            if ($stmt->rowCount() > 0) {
                $this->logger->info('Points deducted', ['telegram_id' => $telegramId, 'amount' => $amount]);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to deduct points', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Ban user
     */
    public function ban(int $telegramId, string $reason = ''): bool
    {
        $user = $this->findByTelegramId($telegramId);
        if (!$user) return false;
        
        return $this->update($user['id'], [
            'is_banned' => 1,
            'ban_reason' => $reason
        ]);
    }
    
    /**
     * Unban user
     */
    public function unban(int $telegramId): bool
    {
        $user = $this->findByTelegramId($telegramId);
        if (!$user) return false;
        
        return $this->update($user['id'], [
            'is_banned' => 0,
            'ban_reason' => null
        ]);
    }
    
    /**
     * Check if user is banned
     */
    public function isBanned(int $telegramId): bool
    {
        $user = $this->findByTelegramId($telegramId);
        return $user && $user['is_banned'] == 1;
    }
    
    /**
     * Set user language
     */
    public function setLanguage(int $telegramId, string $language): bool
    {
        $user = $this->findByTelegramId($telegramId);
        if (!$user) return false;
        
        return $this->update($user['id'], ['language' => $language]);
    }
    
    /**
     * Get user language
     */
    public function getLanguage(int $telegramId): string
    {
        $user = $this->findByTelegramId($telegramId);
        return $user['language'] ?? 'ar';
    }
    
    /**
     * Get or create user
     */
    public function getOrCreate(array $userData): array
    {
        $user = $this->findByTelegramId($userData['telegram_id']);
        
        if ($user) {
            // Update username and first_name if changed
            $updates = [];
            if (isset($userData['username']) && $user['username'] !== $userData['username']) {
                $updates['username'] = $userData['username'];
            }
            if (isset($userData['first_name']) && $user['first_name'] !== $userData['first_name']) {
                $updates['first_name'] = $userData['first_name'];
            }
            
            if (!empty($updates)) {
                $this->update($user['id'], $updates);
                // Reload user data
                $user = $this->findByTelegramId($userData['telegram_id']);
            }
        } else {
            $userId = $this->create($userData);
            $user = $this->findByTelegramId($userData['telegram_id']);
        }
        
        return $user;
    }
    
    /**
     * Get total users count
     */
    public function getTotalUsers(): int
    {
        $result = $this->db->fetch("SELECT COUNT(*) as count FROM users");
        return $result ? (int) $result['count'] : 0;
    }
}
