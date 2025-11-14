<?php

namespace Numbers1\Services;

use Numbers1\Core\Logger;

/**
 * API Service
 * 
 * Handles communication with external API
 */
class ApiService
{
    private $apiKey;
    private $apiUrl = 'https://api.spider-service.com';
    private $logger;
    
    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Make API request
     */
    private function request(array $params): ?array
    {
        $params['apiKay'] = $this->apiKey; // Note: API uses 'apiKay' (typo in original)
        
        $url = $this->apiUrl . '?' . http_build_query($params);
        
        try {
            $response = file_get_contents($url);
            
            if ($response === false) {
                $this->logger->error('API request failed', ['params' => $params]);
                return null;
            }
            
            $data = json_decode($response, true);
            
            if ($data === null) {
                $this->logger->error('Invalid API response', ['response' => $response]);
                return null;
            }
            
            return $data;
        } catch (\Exception $e) {
            $this->logger->error('API exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Get balance
     */
    public function getBalance(): ?float
    {
        $response = $this->request(['action' => 'getBalance']);
        
        if ($response && $response['error'] === 'INFORMATION_SUCCESS') {
            return (float) $response['result']['wallet'];
        }
        
        return null;
    }
    
    /**
     * Get phone number
     */
    public function getNumber(string $countryCode): ?array
    {
        $response = $this->request([
            'action' => 'getNumber',
            'country' => $countryCode
        ]);
        
        if ($response && $response['error'] === 'INFORMATION_SUCCESS') {
            return [
                'number' => $response['result']['phone'],
                'hash_code' => $response['result']['hash_code']
            ];
        }
        
        $this->logger->warning('Failed to get number', [
            'country' => $countryCode,
            'response' => $response
        ]);
        
        return null;
    }
    
    /**
     * Get verification code
     */
    public function getCode(string $hashCode): ?array
    {
        $response = $this->request([
            'action' => 'getCode',
            'hash_code' => $hashCode
        ]);
        
        if ($response && $response['error'] === 'INFORMATION_SUCCESS') {
            return [
                'code' => $response['result']['code'] ?? null,
                'password' => $response['result']['password'] ?? null
            ];
        }
        
        return null;
    }
    
    /**
     * Get available countries
     */
    public function getCountries(): ?array
    {
        $response = $this->request(['action' => 'getCountrys']); // Note: API uses 'getCountrys' (typo)
        
        if ($response && $response['error'] === 'INFORMATION_SUCCESS') {
            return $response['result']['countries'][1] ?? null;
        }
        
        return null;
    }
}
