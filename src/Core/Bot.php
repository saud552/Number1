<?php

namespace Numbers1\Core;

use Numbers1\Services\TranslationService;
use Numbers1\Models\User;

/**
 * Bot Core
 * 
 * Main bot class handling Telegram interactions
 */
class Bot
{
    private $config;
    private $token;
    private $apiUrl;
    private $logger;
    private $translator;
    private $userModel;
    
    public function __construct()
    {
        $this->config = \Config::getInstance();
        $this->token = $this->config->get('BOT_TOKEN');
        $this->apiUrl = 'https://api.telegram.org/bot' . $this->token . '/';
        $this->logger = Logger::getInstance();
        $this->translator = TranslationService::getInstance();
        $this->userModel = new User();
    }
    
    /**
     * Make API call to Telegram
     */
    public function apiCall(string $method, array $params = []): ?object
    {
        $url = $this->apiUrl . $method;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $this->logger->error('Telegram API error', [
                'method' => $method,
                'error' => curl_error($ch)
            ]);
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        $result = json_decode($response);
        
        if (!$result || !$result->ok) {
            $this->logger->error('Telegram API returned error', [
                'method' => $method,
                'response' => $response
            ]);
        }
        
        return $result;
    }
    
    /**
     * Send message
     */
    public function sendMessage(int $chatId, string $text, ?array $keyboard = null, string $parseMode = 'HTML'): ?object
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode
        ];
        
        if ($keyboard !== null) {
            $params['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
        }
        
        return $this->apiCall('sendMessage', $params);
    }
    
    /**
     * Edit message
     */
    public function editMessage(int $chatId, int $messageId, string $text, ?array $keyboard = null, string $parseMode = 'HTML'): ?object
    {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $parseMode
        ];
        
        if ($keyboard !== null) {
            $params['reply_markup'] = json_encode(['inline_keyboard' => $keyboard]);
        }
        
        return $this->apiCall('editMessageText', $params);
    }
    
    /**
     * Answer callback query
     */
    public function answerCallback(string $callbackQueryId, string $text = '', bool $showAlert = false): ?object
    {
        return $this->apiCall('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert
        ]);
    }
    
    /**
     * Check if user is member of channel
     */
    public function checkMembership(int $userId, $channelId): bool
    {
        $result = $this->apiCall('getChatMember', [
            'chat_id' => $channelId,
            'user_id' => $userId
        ]);
        
        if (!$result || !$result->ok) {
            return false;
        }
        
        $status = $result->result->status;
        return !in_array($status, ['left', 'kicked']);
    }
    
    /**
     * Create inline keyboard button
     */
    public function button(string $text, ?string $callback = null, ?string $url = null): array
    {
        $button = ['text' => $text];
        
        if ($callback !== null) {
            $button['callback_data'] = $callback;
        } elseif ($url !== null) {
            $button['url'] = $url;
        }
        
        return $button;
    }
    
    /**
     * Create inline keyboard from array
     */
    public function keyboard(array $buttons): array
    {
        $keyboard = [];
        
        foreach ($buttons as $row) {
            $keyboardRow = [];
            
            foreach ($row as $text => $callbackOrUrl) {
                if (strpos($callbackOrUrl, 'http') === 0) {
                    $keyboardRow[] = $this->button($text, null, $callbackOrUrl);
                } else {
                    $keyboardRow[] = $this->button($text, $callbackOrUrl);
                }
            }
            
            $keyboard[] = $keyboardRow;
        }
        
        return $keyboard;
    }
    
    /**
     * Set webhook
     */
    public function setWebhook(string $url): ?object
    {
        return $this->apiCall('setWebhook', ['url' => $url]);
    }
}
