# 📚 دليل التطبيق (Implementation Guide)

دليل شامل لتطبيق ونشر Numbers1 Bot v2.0

---

## 🎯 نظرة عامة

هذا الدليل يساعدك على:
1. فهم البنية الجديدة
2. هجرة البيانات من النسخة القديمة
3. تكوين البوت
4. اختبار ونشر النظام

---

## 📋 قائمة المهام

### قبل البدء
- [ ] نسخ احتياطي لجميع الملفات القديمة
- [ ] التأكد من توفر PHP 7.4+
- [ ] التأكد من تفعيل SQLite3
- [ ] التأكد من تفعيل curl
- [ ] الحصول على Bot Token من BotFather

### خطوات التنفيذ

#### المرحلة 1: الإعداد (15 دقيقة)

**1. نسخ الملفات**
```bash
# انتقل إلى مجلد البوت
cd /var/www/html/bot

# قم بعمل نسخة احتياطية
cp -r . ../bot_backup_$(date +%Y%m%d)

# تأكد من الصلاحيات
chmod 755 .
chmod 644 *.php
```

**2. إنشاء ملف .env**
```bash
# انسخ النموذج
cp .env.example .env

# عدّل الملف
nano .env
```

املأ البيانات:
```env
# Telegram Bot
BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz
BOT_USERNAME=YourBot
ADMIN_ID=123456789

# Channels (استخدم أرقام سالبة)
CHANNEL_PURCHASES=-1001234567890
CHANNEL_SUCCESS=-1001234567890
CHANNEL_ACTIVATIONS=-1001234567890
CHANNEL_ACTIVATIONS_USERNAME=@your_activations_channel
CHANNEL_MANDATORY=-1001234567890
CHANNEL_MANDATORY_LINK=@your_mandatory_channel

# API
API_KEY=your_spider_api_key_here

# Settings
INVITE_POINTS=0.50
DEFAULT_LANGUAGE=ar

# Support
SUPPORT_USERNAME=@your_support
RECHARGE_USERNAME=@your_recharge
```

**3. ضبط الصلاحيات**
```bash
# إنشاء المجلدات المطلوبة
mkdir -p database logs cache

# ضبط الصلاحيات
chmod 755 database/ logs/ cache/
chmod 600 .env
```

#### المرحلة 2: الهجرة (10 دقائق)

**1. اختبار سكريبت الهجرة**
```bash
php migration.php
```

يجب أن ترى:
```
===========================================
    Numbers1 Bot - Migration Tool v2.0    
===========================================

✅ All required files found.

📂 Loading old data...
   - Users: 150
   - Countries: 45
   - Languages: 150
   - Bans: 5
   - Operations: 320

👥 Migrating users...
   ✅ Migrated 150 users

🌍 Migrating countries...
   ✅ Migrated 45 countries

💼 Migrating operations...
   ✅ Migrated 320 operations

✨ Migration completed successfully!
```

**2. التحقق من البيانات**
```bash
# افتح قاعدة البيانات
sqlite3 database/bot.db

# تحقق من البيانات
.tables
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM countries;
SELECT COUNT(*) FROM operations;

.quit
```

#### المرحلة 3: الاختبار (20 دقيقة)

**1. اختبار الاتصال**
```bash
php -r "require 'autoload.php'; echo 'Autoloader works!';"
```

**2. اختبار قاعدة البيانات**
```bash
php -r "
require 'autoload.php';
\$db = Numbers1\Core\Database::getInstance();
echo 'Database connected!';
"
```

**3. اختبار البوت**
```bash
# أنشئ ملف test.php
cat > test.php << 'EOF'
<?php
require 'autoload.php';

$bot = new Numbers1\Core\Bot();
$config = Config::getInstance();

// Test bot connection
$result = $bot->apiCall('getMe');
if ($result && $result->ok) {
    echo "✅ Bot connected: @" . $result->result->username . "\n";
} else {
    echo "❌ Bot connection failed!\n";
    exit(1);
}

// Test database
$userModel = new Numbers1\Models\User();
echo "✅ User model works\n";

// Test translation
$trans = Numbers1\Services\TranslationService::getInstance();
$trans->setLanguage('ar');
echo "✅ Translation: " . $trans->trans('welcome_message', ['id' => 123, 'balance' => '0.00']) . "\n";

echo "\n🎉 All tests passed!\n";
EOF

php test.php
```

**4. اختبار الترجمات**
```bash
php -r "
require 'autoload.php';
\$trans = Numbers1\Services\TranslationService::getInstance();

// Test all languages
\$languages = ['ar', 'en', 'ru', 'fa', 'zh-CN', 'zh-TW', 'tr'];
foreach (\$languages as \$lang) {
    \$trans->setLanguage(\$lang);
    echo \$lang . ': ' . \$trans->trans('menu_buy') . PHP_EOL;
}
"
```

#### المرحلة 4: النشر (10 دقائق)

**1. تحديث الـ Webhook**
```bash
# أنشئ ملف webhook.php
cat > webhook.php << 'EOF'
<?php
require 'autoload.php';

$bot = new Numbers1\Core\Bot();
$config = Config::getInstance();

$url = 'https://' . $_SERVER['HTTP_HOST'] . '/index_new.php';
$result = $bot->setWebhook($url);

if ($result && $result->ok) {
    echo "✅ Webhook set to: $url\n";
    echo "Description: " . ($result->description ?? '') . "\n";
} else {
    echo "❌ Failed to set webhook\n";
    print_r($result);
}
EOF

php webhook.php
```

**2. التحقق من الـ Webhook**
```bash
curl "https://api.telegram.org/bot<YOUR_TOKEN>/getWebhookInfo"
```

يجب أن ترى:
```json
{
  "ok": true,
  "result": {
    "url": "https://yourdomain.com/index_new.php",
    "has_custom_certificate": false,
    "pending_update_count": 0
  }
}
```

**3. استبدال الملف الرئيسي**
```bash
# انقل القديم
mv index.php index_old.php

# انسخ الجديد
cp index_new.php index.php
```

**أو استخدم رابط مباشر:**
```bash
# اجعل index.php يستدعي index_new.php
cat > index.php << 'EOF'
<?php
require 'index_new.php';
EOF
```

#### المرحلة 5: المراقبة (مستمر)

**1. مراقبة السجلات**
```bash
# راقب السجلات بشكل مباشر
tail -f logs/bot.log
```

**2. مراقبة الأداء**
```bash
# أنشئ سكريبت مراقبة
cat > monitor.php << 'EOF'
<?php
require 'autoload.php';

$db = Numbers1\Core\Database::getInstance();

// Get stats
$stats = [
    'users' => $db->fetch("SELECT COUNT(*) as count FROM users")['count'],
    'operations_today' => $db->fetch("SELECT COUNT(*) as count FROM operations WHERE DATE(created_at) = DATE('now')")['count'],
    'revenue_today' => $db->fetch("SELECT SUM(price) as total FROM operations WHERE DATE(created_at) = DATE('now') AND status = 'completed'")['total'] ?? 0,
    'active_countries' => $db->fetch("SELECT COUNT(*) as count FROM countries WHERE is_active = 1")['count']
];

echo "📊 Bot Statistics\n";
echo "================\n";
echo "Total Users: " . $stats['users'] . "\n";
echo "Operations Today: " . $stats['operations_today'] . "\n";
echo "Revenue Today: $" . number_format($stats['revenue_today'], 2) . "\n";
echo "Active Countries: " . $stats['active_countries'] . "\n";
EOF

# شغّله
php monitor.php
```

---

## 🔍 استكشاف الأخطاء

### المشكلة: البوت لا يستجيب

**الحل:**
```bash
# 1. تحقق من الـ webhook
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"

# 2. راجع السجلات
tail -50 logs/bot.log

# 3. اختبر الاتصال
php -r "require 'autoload.php'; new Numbers1\Core\Bot();"

# 4. تحقق من صلاحيات الملفات
ls -la database/ logs/ cache/
```

### المشكلة: خطأ في قاعدة البيانات

**الحل:**
```bash
# 1. تحقق من وجود الملف
ls -la database/bot.db

# 2. تحقق من الصلاحيات
chmod 755 database/
chmod 644 database/bot.db

# 3. أعد تشغيل المهاجرة
rm database/bot.db
php migration.php
```

### المشكلة: الترجمات لا تعمل

**الحل:**
```bash
# 1. تحقق من ملفات اللغات
ls -la languages/

# 2. تحقق من صحة JSON
php -r "json_decode(file_get_contents('languages/ar.json')) or die('Invalid JSON');"

# 3. امسح الـ cache
rm -rf cache/*
```

---

## 📈 تحسينات الأداء

### تفعيل OpCache

أضف إلى `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

### استخدام Redis للـ Cache

عدّل `config/config.php`:
```php
$this->config['CACHE_DRIVER'] = 'redis';
$this->config['REDIS_HOST'] = '127.0.0.1';
$this->config['REDIS_PORT'] = 6379;
```

---

## 🔐 أمان إضافي

### حماية ملف .env

```bash
# في .htaccess
<Files .env>
    Order allow,deny
    Deny from all
</Files>
```

### تفعيل HTTPS

```bash
# أضف إلى index.php
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}
```

---

## ✅ قائمة التحقق النهائية

قبل النشر النهائي:
- [ ] تم اختبار جميع الوظائف
- [ ] الترجمات تعمل لجميع اللغات
- [ ] قاعدة البيانات تحتوي على جميع البيانات
- [ ] Webhook مفعّل وصحيح
- [ ] ملف .env محمي
- [ ] السجلات تعمل بشكل صحيح
- [ ] نسخة احتياطية للبيانات القديمة
- [ ] الصلاحيات صحيحة
- [ ] تم اختبار لوحة المدير
- [ ] تم اختبار عملية الشراء كاملة

---

## 🎉 بعد النشر

1. **راقب الأداء** لأول 24 ساعة
2. **اجمع الملاحظات** من المستخدمين
3. **تابع السجلات** بانتظام
4. **خذ نسخ احتياطية** يومية

---

## 📞 الدعم

إذا واجهت أي مشكلة:
1. راجع السجلات في `logs/bot.log`
2. تحقق من `ANALYSIS.md` للمشاكل الشائعة
3. تواصل معنا عبر @Y_O_Ul

---

<div dir="rtl" align="center">

**حظاً موفقاً! 🚀**

</div>
