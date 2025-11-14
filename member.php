<?php

use Numbers\Language\LanguageManager;
use Numbers\Support\Conversation;

/** @var LanguageManager $languageManager */

$link = "https://t.me/$botUser?start=$id";

function displayMainMenu(array $txt, string $changeLanguageLabel, bool $asEdit = false): void
{
	global $requestLink, $supportLink, $ch4;

	$buttons = mkBtn([
		[$txt['menu_purchase_usd'] => 'buyUsd', $txt['menu_purchase_stars'] => 'buyStars'],
		[$txt['menu_recharge'] => $requestLink, $txt['menu_support'] => $supportLink],
		[$txt['menu_agents'] => 'wk', $txt['menu_bot_activations'] => $ch4],
		[$txt['menu_free_balance'] => 'inviteLink'],
		[$changeLanguageLabel => 'changeLange']
	]);

	$buttons[1][0]['url'] = $buttons[1][0]['callback_data'];
	unset($buttons[1][0]['callback_data']);
	$buttons[1][1]['url'] = $buttons[1][1]['callback_data'];
	unset($buttons[1][1]['callback_data']);
	$buttons[2][1]['url'] = $buttons[2][1]['callback_data'];
	unset($buttons[2][1]['callback_data']);

	if ($asEdit) {
		edit($txt['welcome'], $buttons);
	} else {
		send($txt['welcome'], $buttons);
	}
}

function listAgents(array $txt, array $back): void
{
	global $info;

	if (empty($info['bot']['wk'])) {
		edit($txt['no_agents'], $back);
		return;
	}

	$message = $txt['menu_agents'] . " \n\n";
	foreach ($info['bot']['wk'] as $index => $agent) {
		$lineNumber = $index + 1;
		$message .= "{$lineNumber} - {$agent['name']} | {$agent['user']} \n";
	}
	edit($message, $back);
}

function alertCallback(string $message): void
{
	global $update;
	if (!isset($update->callback_query->id)) {
		return;
	}

	bot('answerCallbackQuery', [
		'callback_query_id' => $update->callback_query->id,
		'show_alert' => true,
		'text' => $message,
	]);
}

function paginateCountries(string $action, array $txt, string $backLabel, array $exData, string $method): void
{
	global $contries, $tnames, $currentLang, $settings;

	$start = 0;
	$perPage = 30;

	if ($action === 'next') {
		$start = (int)($exData[2] ?? 0);
		if ($start > count($contries)) {
			alertCallback($txt['no_next_page']);
			return;
		}
	} elseif ($action === 'before') {
		$start = (int)($exData[2] ?? 0);
		if ($start >= $perPage) {
			$start -= $perPage;
		} elseif ($start > 0) {
			$start = 0;
		} else {
			alertCallback($txt['no_previous_page']);
			return;
		}
	}

	$end = $start + $perPage;
	$rows = [];
	$currentRow = [];
	$index = -1;

	foreach ($contries as $code => $price) {
		$index++;
		if ($index < $start) {
			continue;
		}
		if ($index >= $end) {
			break;
		}

		$name = $tnames[$currentLang][$code] ?? $tnames['en'][$code] ?? $code;
		$label = "{$name} | {$price}$";
		if ($method === 'stars') {
			$usdPerStar = (float)($settings['stars']['usd_per_star'] ?? 0);
			if ($usdPerStar > 0) {
				$stars = (int)ceil($price / $usdPerStar);
				$label = sprintf("%s | %s$ • %d⭐️", $name, $price, $stars);
			}
		}

		$currentRow[] = [
			'text' => $label,
			'callback_data' => "getNum#{$method}#{$code}"
		];
		if (count($currentRow) === 2) {
			$rows[] = $currentRow;
			$currentRow = [];
		}
	}

	if (!empty($currentRow)) {
		$rows[] = $currentRow;
	}

	$rows[] = [
		['text' => $txt['button_previous'], 'callback_data' => "before#{$method}#{$start}"],
		['text' => $txt['button_next'], 'callback_data' => "next#{$method}#{$end}"],
	];
	$rows[] = [
		['text' => $backLabel, 'callback_data' => 'back']
	];

	edit($txt['country_selection'], $rows);
}

function confirmPurchase(string $countryCode, array $txt, string $backLabel, string $method): void
{
	global $tnames, $currentLang, $names, $contries, $settings;

	$name = $tnames[$currentLang][$countryCode] ?? $tnames['en'][$countryCode] ?? ($names[$countryCode] ?? $countryCode);
	$price = $contries[$countryCode] ?? 0;
	if ($price <= 0) {
		alertCallback($txt['no_numbers']);
		return;
	}

	if ($method === 'stars') {
		$usdPerStar = (float)($settings['stars']['usd_per_star'] ?? 0);
		if ($usdPerStar <= 0) {
			alertCallback($txt['stars_disabled'] ?? 'خيار النجوم غير متاح حالياً.');
			return;
		}
		$stars = (int)ceil($price / $usdPerStar);
		$message = str_replace(
			["__c__", "__p__", "__s__"],
			[$name, $price, $stars],
			$txt['stars_purchase_disclaimer'] ?? $txt['disclaimer']
		);
		$confirmCallback = "getNumber#stars#{$countryCode}";
	} else {
		$message = $txt['disclaimer'] . "\n\n" . $name;
		$confirmCallback = "getNumber#usd#{$countryCode}";
	}

	$buttons = mkBtn([
		[
			$txt['confirm_purchase'] => $confirmCallback,
			$backLabel => 'back'
		]
	]);
	edit($message, $buttons);
}

function handlePurchase(string $countryCode, array $txt, string $backLabel, string $method): void
{
	if ($method === 'stars') {
		initiateStarPurchase($countryCode, $txt, $backLabel);
		return;
	}

	global $contries, $point, $points, $id, $api, $names, $stats, $actionLocker;

	if (!$actionLocker->acquire($id, 'purchase')) {
		alertCallback($txt['purchase_in_progress']);
		return;
	}

	try {
		$price = $contries[$countryCode] ?? 0;
		if ($price <= 0) {
			alertCallback($txt['no_numbers']);
			return;
		}

		if ($point < $price) {
			alertCallback($txt['insufficient_balance']);
			return;
		}

		$numberData = $api->getNumber($countryCode);
		if (!is_array($numberData)) {
			alertCallback($txt['no_numbers']);
			return;
		}

		$number = $numberData['number'];
		$hashCode = $numberData['hash_code'];
		$countryName = $names[$countryCode] ?? $countryCode;

		$points[$id] -= $price;
		$point = $points[$id];
		savePoint();

		$stats['all']['trybuy'] = ($stats['all']['trybuy'] ?? 0) + 1;
		saveStats();

		respondWithPurchaseDetails($countryCode, $number, $price, $hashCode, $txt, true);
	} finally {
		$actionLocker->release($id, 'purchase');
	}
}

function initiateStarPurchase(string $countryCode, array $txt, string $backLabel): void
{
	global $contries, $settings, $names, $op, $id;

	$price = $contries[$countryCode] ?? 0;
	if ($price <= 0) {
		alertCallback($txt['no_numbers']);
		return;
	}

	$usdPerStar = (float)($settings['stars']['usd_per_star'] ?? 0);
	if ($usdPerStar <= 0) {
		alertCallback($txt['stars_disabled'] ?? 'خيار النجوم غير متاح حالياً.');
		return;
	}

	$stars = (int)ceil($price / $usdPerStar);
	if ($stars <= 0) {
		$stars = 1;
	}

	$payload = bin2hex(random_bytes(16));
	if (!isset($op[STAR_OPERATIONS_KEY])) {
		$op[STAR_OPERATIONS_KEY] = [];
	}
	$op[STAR_OPERATIONS_KEY][$payload] = [
		'payload' => $payload,
		'user_id' => $id,
		'country' => $countryCode,
		'price' => $price,
		'stars' => $stars,
		'created_at' => time(),
	];
	saveOp();

	$countryName = $names[$countryCode] ?? $countryCode;

	$description = str_replace(
		["__c__", "__p__", "__s__"],
		[$countryName, $price, $stars],
		$txt['stars_invoice_description'] ?? ''
	);

	$response = bot('createInvoiceLink', [
		'title' => $txt['stars_invoice_title'] ?? 'شراء حساب تيليجرام',
		'description' => $description,
		'payload' => $payload,
		'currency' => 'XTR',
		'prices' => json_encode([
			['label' => $countryName, 'amount' => $stars],
		]),
	]);

	$invoiceLink = $response->result ?? null;
	if (!$invoiceLink) {
		unset($op[STAR_OPERATIONS_KEY][$payload]);
		saveOp();
		alertCallback($txt['purchase_failed'] ?? 'حدث خطأ، حاول لاحقاً.');
		return;
	}

	$message = str_replace(
		["__c__", "__p__", "__s__"],
		[$countryName, $price, $stars],
		$txt['stars_invoice_message'] ?? ''
	);

	$buttons = [
		[
			['text' => $txt['stars_invoice_button'] ?? 'الدفع بالنجوم', 'url' => $invoiceLink],
		],
		[
			['text' => $backLabel, 'callback_data' => 'back'],
		],
	];
	edit($message, $buttons);
}

function deliverCode(array $exData, array $txt): void
{
	global $api, $stats, $contries, $names, $ch2, $ch3, $id;

	$hashCode = $exData[1] ?? '';
	$countryCode = $exData[2] ?? '';
	$number = $exData[3] ?? '';

	$response = $api->getCode($hashCode);
	if (!is_array($response)) {
		alertCallback($txt['code_pending']);
		return;
	}

	$code = $response['code'];
	$password = $response['password'];
	$countryName = $names[$countryCode] ?? $countryCode;
	$price = $contries[$countryCode] ?? 0;

	$stats['all']['buy'] = ($stats['all']['buy'] ?? 0) + 1;
	$stats[$id]['buy'] = ($stats[$id]['buy'] ?? 0) + 1;
	saveStats();

	$message = str_replace(
		["__num__", "__p__", "__c__", "__code__", "__pass__"],
		[$number, $price, $countryName, $code, $password],
		$txt['code_received']
	);
	edit($message);

	$logMessage = "
⚜️ تم وصول كود الرقم:

🌎 - الدولة: {$countryName}
☎️ - الرقم: <code>{$number}</code>
💰- السعر :  {$price}$
💬 - الكود : {$code} 🗯
🔑 - كلمة المرور: {$password}
👤- المشتري : <code>{$id}</code>
🎗 - الموقع : السيرفر الرئيسي
";
	send($logMessage, null, $ch2);

	$maskedUser = substr((string)$id, 0, -4) . "••••";
	$maskedNumber = substr($number, 0, -4) . "••••";

	$promo = "
✅- تم شراء رقم من البوت بنجاح -✅

🌎 - الدولة: {$countryName}
📱 - حسابات تيليجرام جاهزة 📲

☎️ - الرقم: <tg-spoiler>{$maskedNumber}</tg-spoiler> 📞
💰- السعر :  <tg-spoiler>{$price}$</tg-spoiler>  
💬 - الكود : {$code} 🗯
🆔- المشتري : <tg-spoiler>{$maskedUser}</tg-spoiler>  👨🏻‍💻

☑️ - الحالة : تم التفعيل بنجاح ☑️
";
	send($promo, [[["text" => "🤖 شراء رقم من البوت 🤖", "url" => "https://t.me/يوزر قناة التفعيلات"]]], $ch3);
}

if (($ex[0] ?? null) === "/start") {
	if (!isset($points[$id]) && isset($points[$ex[1]]) && $id !== $ex[1]) {
		$invite['whoInvitedMe'][$id] = $ex[1];
		saveInvite();
	}
}

$backLabel = $languageManager->label($currentLang, 'back', 'Back');
$backKeyboard = mkBtn([[$backLabel => 'back']]);

$changeLanguageLabel = $languageManager->label($currentLang, 'change_language', 'Change Language');
$maintenanceEnabled = $settings['maintenance']['enabled'] ?? false;
$maintenanceMessage = $settings['maintenance']['message'] ?? $txt['maintenance_message'];

if ($maintenanceEnabled && $id != $admin) {
	if (!empty($text)) {
		send($maintenanceMessage);
	} else {
		edit($maintenanceMessage);
	}
	return;
}

if (($bans[$id] ?? null) === $id) {
	send($txt['banned_message']);
	return;
}

if (($text ?? '') === "/start") {
	if (!isset($points[$id])) {
		$points[$id] = 0;
		if (isset($invite['whoInvitedMe'][$id])) {
			$inviter = $invite['whoInvitedMe'][$id];
			$points[$inviter] = ($points[$inviter] ?? 0) + $invitePoint;
			$invite['invited'][$inviter] = $id;
			saveInvite();

			$inviterLang = Conversation::ensureLanguageCode($languageManager, $langs[$inviter] ?? null);
			$inviterReplacements = Conversation::buildReplacements(
				$inviter,
				$points[$inviter] ?? 0,
				"https://t.me/$botUser?start=$inviter"
			);
			$inviterStrings = Conversation::prepareStrings($languageManager, $inviterLang, $inviterReplacements);
			send($inviterStrings['invite_reward'], null, $inviter);
		}
		savePoint();
	}
	displayMainMenu($txt, $changeLanguageLabel, false);
	return;
}

if (($data ?? null) === 'back') {
	displayMainMenu($txt, $changeLanguageLabel, true);
	return;
}

switch ($data ?? '') {
	case 'requestPoint':
		edit($txt['charge_info'], $backKeyboard);
		return;
	case 'support':
		edit($txt['support_info'], $backKeyboard);
		return;
	case 'changeLange':
		$prompt = Conversation::languagePrompt($languageManager, $currentLang, $backLabel);
		$keyboard = mkBtn($prompt['buttons']);
		if (!empty($text)) {
			send($prompt['text'], $keyboard);
		} else {
			edit($prompt['text'], $keyboard);
		}
		return;
	case 'wk':
		listAgents($txt, $backKeyboard);
		return;
	case 'inviteLink':
		edit($txt['invite_info'], $backKeyboard);
		return;
	case 'buyUsd':
		paginateCountries('buy', $txt, $backLabel, [], 'usd');
		return;
	case 'buyStars':
		paginateCountries('buy', $txt, $backLabel, [], 'stars');
		return;
}

if (!empty($exData)) {
	switch ($exData[0]) {
		case 'next':
		case 'before':
			$method = $exData[1] ?? 'usd';
			paginateCountries($exData[0], $txt, $backLabel, $exData, $method);
			return;
		case 'getNum':
			$method = $exData[1] ?? 'usd';
			confirmPurchase($exData[2] ?? '', $txt, $backLabel, $method);
			return;
		case 'getNumber':
			$method = $exData[1] ?? 'usd';
			handlePurchase($exData[2] ?? '', $txt, $backLabel, $method);
			return;
		case 'getCode':
			deliverCode($exData, $txt);
			return;
	}
}
