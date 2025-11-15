<?php

namespace Numbers1\Models;

use Numbers1\Core\Database;
use Numbers1\Core\Cache;
use Numbers1\Core\Logger;

/**
 * Country Model
 * 
 * Handles country-related database operations
 */
class Country
{
    private $db;
    private $cache;
    private $logger;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->cache = Cache::getInstance();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Get all active countries
     */
    public function getActiveCountries(): array
    {
        return $this->cache->remember('active_countries', function() {
            $sql = "SELECT * FROM countries WHERE is_active = 1 ORDER BY code";
            return $this->db->fetchAll($sql);
        }, 1800); // Cache for 30 minutes
    }
    
    /**
     * Get country by code
     */
    public function findByCode(string $code): ?array
    {
        $sql = "SELECT * FROM countries WHERE code = :code LIMIT 1";
        return $this->db->fetch($sql, ['code' => $code]);
    }
    
    /**
     * Add country
     */
    public function add(string $code, float $price): int
    {
        try {
            $id = $this->db->insert('countries', [
                'code' => $code,
                'price' => $price,
                'is_active' => 1
            ]);
            
            // Clear cache
            $this->cache->delete('active_countries');
            
            $this->logger->info('Country added', ['code' => $code, 'price' => $price]);
            return $id;
        } catch (\Exception $e) {
            $this->logger->error('Failed to add country', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Update country price
     */
    public function updatePrice(string $code, float $price): bool
    {
        try {
            $rowsAffected = $this->db->update(
                'countries',
                ['price' => $price],
                'code = :code',
                ['code' => $code]
            );
            
            // Clear cache
            $this->cache->delete('active_countries');
            
            return $rowsAffected > 0;
        } catch (\Exception $e) {
            $this->logger->error('Failed to update country price', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Remove country (mark as inactive)
     */
    public function remove(string $code): bool
    {
        try {
            $rowsAffected = $this->db->update(
                'countries',
                ['is_active' => 0],
                'code = :code',
                ['code' => $code]
            );
            
            // Clear cache
            $this->cache->delete('active_countries');
            
            return $rowsAffected > 0;
        } catch (\Exception $e) {
            $this->logger->error('Failed to remove country', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Get country price
     */
    public function getPrice(string $code): ?float
    {
        $country = $this->findByCode($code);
        return $country && $country['is_active'] == 1 ? (float) $country['price'] : null;
    }
    
    /**
     * Check if country is available
     */
    public function isAvailable(string $code): bool
    {
        $country = $this->findByCode($code);
        return $country && $country['is_active'] == 1;
    }
}
