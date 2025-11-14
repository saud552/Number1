<?php
/**
 * Numbers1 Bot - Main Entry Point
 * 
 * Refactored version with OOP, SQLite, and multilingual support
 * Version: 2.0.0
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load autoloader
require_once __DIR__ . '/autoload.php';

// Load legacy files temporarily for country names
require_once __DIR__ . '/contries.php';

use Numbers1\Core\Bot;
use Numbers1\Core\Database;
use Numbers1\Core\Logger;
use Numbers1\Services\TranslationService;
use Numbers1\Services\ApiService;
use Numbers1\Models\User;
use Numbers1\Models\Country as CountryModel;
use Numbers1\Models\Operation;

try {
    // Initialize core components
    $config = Config::getInstance();
    $logger = Logger::getInstance();
    $db = Database::getInstance();
    $bot = new Bot();
    $translator = TranslationService::getInstance();
    $userModel = new User();
    $countryModel = new CountryModel();
    $operationModel = new Operation();
    $apiService = new ApiService($config->get('API_KEY'));
    
    // Get update from Telegram
    $update = json_decode(file_get_contents('php://input'));
    
    if (!$update) {
        exit;
    }
    
    // Parse update
    $message = $update->message ?? null;
    $callbackQuery = $update->callback_query ?? null;
    
    if ($message) {
        $chatId = $message->chat->id;
        $userId = $message->from->id;
        $username = $message->from->username ?? null;
        $firstName = $message->from->first_name ?? '';
        $text = $message->text ?? '';
        $messageId = $message->message_id;
    } elseif ($callbackQuery) {
        $chatId = $callbackQuery->message->chat->id;
        $userId = $callbackQuery->from->id;
        $username = $callbackQuery->from->username ?? null;
        $firstName = $callbackQuery->from->first_name ?? '';
        $messageId = $callbackQuery->message->message_id;
        $data = $callbackQuery->data;
    } else {
        exit;
    }
    
    // Get or create user
    $user = $userModel->getOrCreate([
        'telegram_id' => $userId,
        'username' => $username,
        'first_name' => $firstName
    ]);
    
    // Set user language
    $translator->setLanguage($user['language']);
    
    // Check if user is banned
    if ($user['is_banned']) {
        $bot->sendMessage($chatId, $translator->trans('banned_message'));
        exit;
    }
    
    // Check mandatory channel membership
    $mandatoryChannel = $config->get('CHANNEL_MANDATORY');
    if (!$bot->checkMembership($userId, $mandatoryChannel)) {
        $keyboard = [[
            $bot->button(
                $translator->trans('verify_button'),
                null,
                $config->get('CHANNEL_MANDATORY_LINK')
            )
        ]];
        
        $text = $translator->trans('force_join_message', [
            'channel' => $config->get('CHANNEL_MANDATORY_LINK')
        ]);
        
        $bot->sendMessage($chatId, $text, $keyboard);
        exit;
    }
    
    // Handle /start command
    if ($message && (strpos($text, '/start') === 0)) {
        $parts = explode(' ', $text);
        
        // Handle referral
        if (count($parts) > 1 && $parts[1] != $userId) {
            $inviterId = (int) $parts[1];
            $inviter = $userModel->findByTelegramId($inviterId);
            
            if ($inviter && !$user['id']) { // New user
                $invitePoints = (float) $config->get('INVITE_POINTS', 0);
                if ($invitePoints > 0) {
                    $userModel->addPoints($inviterId, $invitePoints);
                    
                    // Notify inviter
                    $inviterLang = $userModel->getLanguage($inviterId);
                    $notifyText = $translator->trans('invite_earned', [
                        'points' => $invitePoints
                    ], $inviterLang);
                    
                    $bot->sendMessage($inviterId, $notifyText);
                }
            }
        }
        
        // Show main menu
        $keyboard = $bot->keyboard([
            [$translator->trans('menu_buy') => 'buy'],
            [
                $translator->trans('menu_recharge') => $config->get('RECHARGE_USERNAME'),
                $translator->trans('menu_support') => $config->get('SUPPORT_USERNAME')
            ],
            [
                $translator->trans('menu_agents') => 'agents',
                $translator->trans('menu_activations') => $config->get('CHANNEL_ACTIVATIONS_USERNAME')
            ],
            [$translator->trans('menu_free_balance') => 'invite'],
            [$translator->trans('change_language') => 'change_language']
        ]);
        
        $welcomeText = $translator->trans('welcome_message', [
            'id' => $userId,
            'balance' => $user['points']
        ]);
        
        $bot->sendMessage($chatId, $welcomeText, $keyboard);
        exit;
    }
    
    // Handle callback queries
    if ($callbackQuery) {
        // Language selection
        if ($data === 'change_language') {
            $languages = $translator->getAvailableLanguages();
            $keyboard = [];
            
            foreach ($languages as $code => $name) {
                $keyboard[] = [$name => "set_lang_{$code}"];
            }
            
            $keyboard[] = [$translator->trans('back') => 'back_menu'];
            
            $text = "Please select your language:\nالرجاء اختيار لغتك:\nПожалуйста, выберите язык:";
            $bot->editMessage($chatId, $messageId, $text, $bot->keyboard($keyboard));
            exit;
        }
        
        // Set language
        if (strpos($data, 'set_lang_') === 0) {
            $lang = str_replace('set_lang_', '', $data);
            $userModel->setLanguage($userId, $lang);
            $translator->setLanguage($lang);
            
            $bot->answerCallback($callbackQuery->id, "✅", false);
            
            // Show main menu in new language
            $keyboard = $bot->keyboard([
                [$translator->trans('menu_buy') => 'buy'],
                [
                    $translator->trans('menu_recharge') => $config->get('RECHARGE_USERNAME'),
                    $translator->trans('menu_support') => $config->get('SUPPORT_USERNAME')
                ],
                [
                    $translator->trans('menu_agents') => 'agents',
                    $translator->trans('menu_activations') => $config->get('CHANNEL_ACTIVATIONS_USERNAME')
                ],
                [$translator->trans('menu_free_balance') => 'invite'],
                [$translator->trans('change_language') => 'change_language']
            ]);
            
            $welcomeText = $translator->trans('welcome_message', [
                'id' => $userId,
                'balance' => $user['points']
            ]);
            
            $bot->editMessage($chatId, $messageId, $welcomeText, $keyboard);
            exit;
        }
        
        // Buy numbers - show countries
        if ($data === 'buy') {
            $countries = $countryModel->getActiveCountries();
            
            if (empty($countries)) {
                $bot->answerCallback($callbackQuery->id, $translator->trans('no_numbers_available'), true);
                exit;
            }
            
            $keyboard = [];
            foreach ($countries as $country) {
                $countryName = $tnames[$user['language']][$country['code']] ?? $country['code'];
                $keyboard[] = [
                    $bot->button(
                        "{$countryName} | {$country['price']}$",
                        "country_{$country['code']}"
                    )
                ];
            }
            
            $keyboard[] = [$bot->button($translator->trans('back'), 'back_menu')];
            
            $text = $translator->trans('choose_country');
            $bot->editMessage($chatId, $messageId, $text, $keyboard);
            exit;
        }
    }
    
    $logger->info('Update processed', ['user_id' => $userId]);
    
} catch (\Exception $e) {
    if (isset($logger)) {
        $logger->error('Fatal error: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    
    // Send error message to admin
    if (isset($bot) && isset($config)) {
        $adminId = $config->get('ADMIN_ID');
        if ($adminId) {
            $bot->sendMessage(
                $adminId,
                "⚠️ Error occurred:\n\n" . $e->getMessage()
            );
        }
    }
}
