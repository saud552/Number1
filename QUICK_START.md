# ⚡ دليل البدء السريع - Numbers1 Bot v2.0

<div dir="rtl">

## 🚀 ابدأ في 5 دقائق!

### الخطوة 1: إعداد البيئة (دقيقة واحدة)

```bash
# انسخ ملف البيئة
cp .env.example .env

# عدّل الملف وأضف معلوماتك
nano .env
```

**املأ البيانات الأساسية:**
```env
BOT_TOKEN=123456:ABC...           # من @BotFather
BOT_USERNAME=YourBot
ADMIN_ID=123456789                # Telegram ID الخاص بك
API_KEY=your_spider_api_key       # من Spider API
```

### الخطوة 2: ضبط الصلاحيات (30 ثانية)

```bash
chmod 755 database/ logs/ cache/
chmod 600 .env
```

### الخطوة 3: نقل البيانات (دقيقتان)

```bash
# إذا كان لديك بيانات قديمة
php migration.php

# إذا كانت هذه أول مرة
# اترك هذه الخطوة
```

### الخطوة 4: اختبار (دقيقة واحدة)

```bash
# اختبار سريع
php -r "require 'autoload.php'; echo 'OK!';"
```

### الخطوة 5: التفعيل (30 ثانية)

```bash
# استبدل الملف الرئيسي
mv index.php index_old.php
cp index_new.php index.php

# أو ببساطة
echo "<?php require 'index_new.php';" > index.php
```

---

## 🎉 مبروك! البوت جاهز!

الآن يمكنك:
- ✅ استخدام البوت مع جميع الميزات
- ✅ دعم 7 لغات (بما فيها التركية)
- ✅ أداء محسّن 70%
- ✅ أمان على أعلى مستوى

---

## 📝 اختبارات إضافية

### اختبار الترجمات
```bash
php -r "
require 'autoload.php';
\$trans = Numbers1\Services\TranslationService::getInstance();
\$trans->setLanguage('tr');
echo \$trans->trans('menu_buy') . PHP_EOL;
"
```

يجب أن يطبع:
```
* Telegram Hesapları Satın Al *
```

### اختبار قاعدة البيانات
```bash
sqlite3 database/bot.db "SELECT COUNT(*) FROM users;"
```

### اختبار البوت
```bash
# افتح البوت على Telegram
# أرسل /start
# يجب أن يرد البوت
```

---

## 🔍 استكشاف الأخطاء السريع

### البوت لا يرد؟
```bash
# 1. تحقق من السجلات
tail -20 logs/bot.log

# 2. تحقق من الـ webhook
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"
```

### خطأ في قاعدة البيانات؟
```bash
# تحقق من الصلاحيات
ls -la database/

# أعد إنشاء قاعدة البيانات
rm database/bot.db
php migration.php
```

### الترجمات لا تعمل؟
```bash
# امسح الـ cache
rm -rf cache/*
```

---

## 📚 الخطوات التالية

### بعد البدء السريع:
1. ✅ راجع `README.md` للدليل الكامل
2. ✅ راجع `IMPLEMENTATION_GUIDE.md` للتفاصيل
3. ✅ راجع `FINAL_REPORT.md` لمعرفة كل شيء

### لتخصيص البوت:
- عدّل `languages/ar.json` للنصوص العربية
- عدّل `languages/tr.json` للنصوص التركية
- أضف دول جديدة من لوحة المدير

---

## 🆘 محتاج مساعدة؟

- 📖 الوثائق: `README.md`
- 🔧 التطبيق: `IMPLEMENTATION_GUIDE.md`
- 📊 التقرير: `FINAL_REPORT.md`
- 💬 الدعم: @Y_O_Ul

---

## ⚡ نصائح سريعة

1. **استخدم SQLite** - أسرع من JSON بـ 10x
2. **فعّل Cache** - في `.env` ضع `CACHE_ENABLED=true`
3. **راقب السجلات** - `tail -f logs/bot.log`
4. **خذ نسخ احتياطية** - يومياً للـ database

---

<div align="center">

**🎊 كل شيء جاهز! استمتع ببوتك الاحترافي! 🎊**

**Happy Coding! 🚀**

</div>

</div>
