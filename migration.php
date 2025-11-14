<?php
/**
 * Migration Script
 * 
 * Migrates data from old JSON files to new SQLite database
 */

require_once __DIR__ . '/autoload.php';

use Numbers1\Models\User;
use Numbers1\Models\Country;
use Numbers1\Models\Operation;
use Numbers1\Core\Database;
use Numbers1\Core\Logger;

echo "===========================================\n";
echo "    Numbers1 Bot - Migration Tool v2.0    \n";
echo "===========================================\n\n";

$logger = Logger::getInstance();
$db = Database::getInstance();

// Check if old files exist
$requiredFiles = ['points.json', 'contries.json'];
foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        echo "❌ Error: {$file} not found!\n";
        echo "Please make sure all JSON files exist before running migration.\n";
        exit(1);
    }
}

echo "✅ All required files found.\n\n";

// Load old data
echo "📂 Loading old data...\n";
$oldPoints = json_decode(file_get_contents('points.json'), true) ?? [];
$oldCountries = json_decode(file_get_contents('contries.json'), true) ?? [];
$oldLangs = json_decode(file_get_contents('langs.json'), true) ?? [];
$oldBans = json_decode(file_get_contents('bans.json'), true) ?? [];
$oldOperations = json_decode(file_get_contents('operations.json'), true) ?? [];

echo "   - Users: " . count($oldPoints) . "\n";
echo "   - Countries: " . count($oldCountries) . "\n";
echo "   - Languages: " . count($oldLangs) . "\n";
echo "   - Bans: " . count($oldBans) . "\n";
echo "   - Operations: " . count($oldOperations) . "\n\n";

// Initialize models
$userModel = new User();
$countryModel = new Country();
$operationModel = new Operation();

// Start transaction
$db->beginTransaction();

try {
    // Migrate users
    echo "👥 Migrating users...\n";
    $userCount = 0;
    
    foreach ($oldPoints as $telegramId => $points) {
        $language = $oldLangs[$telegramId] ?? 'ar';
        $isBanned = isset($oldBans[$telegramId]) ? 1 : 0;
        
        try {
            $user = $userModel->findByTelegramId($telegramId);
            
            if (!$user) {
                $userModel->create([
                    'telegram_id' => $telegramId,
                    'points' => $points,
                    'language' => $language
                ]);
                
                if ($isBanned) {
                    $userModel->ban($telegramId, 'Migrated from old system');
                }
                
                $userCount++;
            }
        } catch (Exception $e) {
            echo "   ⚠️  Warning: Failed to migrate user {$telegramId}: {$e->getMessage()}\n";
        }
    }
    
    echo "   ✅ Migrated {$userCount} users\n\n";
    
    // Migrate countries
    echo "🌍 Migrating countries...\n";
    $countryCount = 0;
    
    foreach ($oldCountries as $code => $price) {
        try {
            $existing = $countryModel->findByCode($code);
            
            if (!$existing) {
                $countryModel->add($code, $price);
                $countryCount++;
            }
        } catch (Exception $e) {
            echo "   ⚠️  Warning: Failed to migrate country {$code}: {$e->getMessage()}\n";
        }
    }
    
    echo "   ✅ Migrated {$countryCount} countries\n\n";
    
    // Migrate operations (if exist)
    if (!empty($oldOperations)) {
        echo "💼 Migrating operations...\n";
        $opCount = 0;
        
        foreach ($oldOperations as $opId => $operation) {
            try {
                if (!is_array($operation)) continue;
                
                $user = $userModel->findByTelegramId($operation['buyer_id'] ?? 0);
                if (!$user) continue;
                
                $operationModel->create([
                    'user_id' => $user['id'],
                    'country_code' => $operation['county'] ?? 'XX',
                    'phone_number' => $operation['number'] ?? '',
                    'hash_code' => $operation['hash_code'] ?? '',
                    'price' => $operation['price'] ?? 0,
                    'status' => 'completed'
                ]);
                
                $opCount++;
            } catch (Exception $e) {
                echo "   ⚠️  Warning: Failed to migrate operation: {$e->getMessage()}\n";
            }
        }
        
        echo "   ✅ Migrated {$opCount} operations\n\n";
    }
    
    // Commit transaction
    $db->commit();
    
    echo "\n✨ Migration completed successfully!\n\n";
    echo "📊 Summary:\n";
    echo "   - Users migrated: {$userCount}\n";
    echo "   - Countries migrated: {$countryCount}\n";
    echo "   - Operations migrated: " . ($opCount ?? 0) . "\n\n";
    
    // Backup old files
    echo "💾 Creating backup of old files...\n";
    $backupDir = 'backup_' . date('Y-m-d_His');
    mkdir($backupDir, 0755, true);
    
    $filesToBackup = [
        'points.json',
        'contries.json',
        'langs.json',
        'bans.json',
        'operations.json',
        'stats.json',
        'invites.json',
        'info.json'
    ];
    
    foreach ($filesToBackup as $file) {
        if (file_exists($file)) {
            copy($file, $backupDir . '/' . $file);
        }
    }
    
    echo "   ✅ Backup created in: {$backupDir}/\n\n";
    
    echo "⚠️  IMPORTANT NOTES:\n";
    echo "1. Old JSON files have been backed up to {$backupDir}/\n";
    echo "2. Please test the new system thoroughly before deleting old files\n";
    echo "3. Update your index.php to use index_new.php\n";
    echo "4. Configure your .env file with correct settings\n\n";
    
    echo "🎉 You can now use the new system!\n";
    
} catch (Exception $e) {
    $db->rollback();
    echo "\n❌ Migration failed: {$e->getMessage()}\n";
    echo "Transaction rolled back. No data was changed.\n";
    $logger->error('Migration failed', ['error' => $e->getMessage()]);
    exit(1);
}
