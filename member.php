<?php

use Numbers\Language\LanguageManager;

/** @var LanguageManager $languageManager */

$link = "https://t.me/$botUser?start=$id";
$languageOptions = $languageManager->options();
$forcedSubscription = $settings['forced_subscription'] ?? [];
$subscriptionChannelId = $forcedSubscription['channel_id'] ?? $ch5;
$subscriptionLink = $forcedSubscription['channel_link'] ?? $ch6;
$subscriptionEnabled = $forcedSubscription['enabled'] ?? true;

const LANGUAGE_PROMPT = "
قم باختيار اللغة.
Please choose a language.
Пожалуйста, выберите язык.
 لطفاً زبان را انتخاب کنید.
 請選擇語言。
 请选擇语言。
";

function saveLangs(): void
{
	global $langs, $storage;
	$storage->persist('langs', $langs);
}

function buildReplacements(int $userId, $balance, string $refLink): array
{
	global $invitePoint, $requestLink, $supportLink, $settings, $ch6;
	$channelLink = $settings['forced_subscription']['channel_link'] ?? $ch6;

	return [
		'{{channel_link}}' => $channelLink,
		'{{invite_point}}' => $invitePoint,
		'{{ref_link}}' => $refLink,
		'{{charge_link}}' => $requestLink,
		'{{support_link}}' => $supportLink,
		'{{user_id}}' => (string)$userId,
		'{{balance}}' => (string)$balance,
	];
}

function prepareStrings(string $lang, array $replacements): array
{
	global $languageManager;

	$strings = $languageManager->strings($lang);
	foreach ($strings as $key => $value) {
		if (is_string($value)) {
			$strings[$key] = str_replace(array_keys($replacements), array_values($replacements), $value);
		}
	}

	return $strings;
}

function ensureLanguageCode(?string $code): string
{
	global $languageManager;
	if ($code && $languageManager->has($code)) {
		return $code;
	}
	return 'ar';
}

function showLanguagePrompt(string $prompt, array $languageOptions, ?string $currentLang, $text): void
{
	global $languageManager;

	$buttons = [];
	foreach ($languageOptions as $code => $label) {
		$buttons[] = [$label => "lang#$code"];
	}

	if ($currentLang) {
		$buttons[] = [
			$languageManager->label($currentLang, 'back', 'Back') => 'back'
		];
	}

	$keyboard = mkBtn($buttons);
	if ($text) {
		send($prompt, $keyboard);
	} else {
		edit($prompt, $keyboard);
	}
	exit;
}

function displayMainMenu(array $txt, string $changeLanguageLabel, bool $asEdit = false): void
{
	global $requestLink, $supportLink, $ch4;

	$buttons = mkBtn([
		[$txt['menu_purchase'] => 'buy'],
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

	bot('answercallbackquery', [
		'callback_query_id' => $update->callback_query->id,
		'show_alert' => true,
		'text' => $message,
	]);
}

function paginateCountries(string $action, array $txt, string $backLabel, array $exData): void
{
	global $contries, $tnames, $currentLang;

	$start = 0;
	$perPage = 30;

	if ($action === 'next') {
		$start = (int)($exData[1] ?? 0);
		if ($start > count($contries)) {
			alertCallback($txt['no_next_page']);
			return;
		}
	} elseif ($action === 'before') {
		$start = (int)($exData[1] ?? 0);
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
		$currentRow[] = [
			'text' => "{$name} | $price",
			'callback_data' => "getNum#{$code}"
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
		['text' => $txt['button_previous'], 'callback_data' => "before#{$start}"],
		['text' => $txt['button_next'], 'callback_data' => "next#{$end}"],
	];
	$rows[] = [
		['text' => $backLabel, 'callback_data' => 'back']
	];

	edit($txt['country_selection'], $rows);
}

function confirmPurchase(string $countryCode, array $txt, string $backLabel): void
{
	global $tnames, $currentLang, $names;

	$name = $tnames[$currentLang][$countryCode] ?? $tnames['en'][$countryCode] ?? ($names[$countryCode] ?? $countryCode);
	$message = $txt['disclaimer'] . "\n\n" . $name;
	$buttons = mkBtn([
		[
			$txt['confirm_purchase'] => "getNumber#{$countryCode}",
			$backLabel => 'back'
		]
	]);
	edit($message, $buttons);
}

function handlePurchase(string $countryCode, array $txt): void
{
	global $contries, $point, $points, $id, $api, $names, $stats, $ch1, $actionLocker;

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

		$channelMessage = "
✅- تم شراء رقم من البوت بنجاح -✅

☎️ - الرقم: <code>{$number}</code>
🌎 - الدولة: {$countryName}
💢 - رمز الدولة: {$countryCode}
💵- السعر :  {$price}$
💰 - الرصيد: {$point}
🆔 - الايدي: <code>{$id}</code>
";
		send($channelMessage, null, $ch1);

		$userMessage = str_replace(
			["__c__", "__num__", "__p__"],
			[$countryName, $number, $price],
			$txt['purchase_success']
		);

		$buttons = mkBtn([
			[
				$txt['request_code'] => "getCode#{$hashCode}#{$countryCode}#{$number}"
			]
		]);
		edit($userMessage, $buttons);
	} finally {
		$actionLocker->release($id, 'purchase');
	}
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

if (!empty($exData) && $exData[0] === "lang") {
	$selectedLang = ensureLanguageCode($exData[1]);
	$langs[$id] = $selectedLang;
	saveLangs();
	$mainMenuLabel = $languageManager->label($selectedLang, 'main_menu', 'Main Menu');
	edit("⬇️⬇️⬇️⬇️⬇️⬇️⬇️⬇️⬇️⬇️", [[['text' => $mainMenuLabel, 'callback_data' => 'back']]]);
	return;
}

if (!isset($langs[$id])) {
	showLanguagePrompt(LANGUAGE_PROMPT, $languageOptions, null, $text ?? null);
}

$currentLang = ensureLanguageCode($langs[$id]);
if ($currentLang !== ($langs[$id] ?? null)) {
	$langs[$id] = $currentLang;
	saveLangs();
}

$backLabel = $languageManager->label($currentLang, 'back', 'Back');
$backKeyboard = mkBtn([[$backLabel => 'back']]);

$replacements = buildReplacements($id, $point, $link);
$txt = prepareStrings($currentLang, $replacements);
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

if ($subscriptionEnabled && !check_member($id, $subscriptionChannelId)) {
	$button = [[['text' => $txt['verify_button'], 'url' => $subscriptionLink]]];
	if (!empty($text)) {
		send($txt['verify_text'], $button);
	} else {
		edit($txt['verify_text'], $button);
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

			$inviterLang = ensureLanguageCode($langs[$inviter] ?? null);
			$inviterReplacements = buildReplacements(
				$inviter,
				$points[$inviter] ?? 0,
				"https://t.me/$botUser?start=$inviter"
			);
			$inviterStrings = prepareStrings($inviterLang, $inviterReplacements);
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
		showLanguagePrompt(LANGUAGE_PROMPT, $languageOptions, $currentLang, $text ?? null);
		return;
	case 'wk':
		listAgents($txt, $backKeyboard);
		return;
	case 'inviteLink':
		edit($txt['invite_info'], $backKeyboard);
		return;
	case 'buy':
		paginateCountries('buy', $txt, $backLabel, []);
		return;
}

if (!empty($exData)) {
	switch ($exData[0]) {
		case 'next':
		case 'before':
			paginateCountries($exData[0], $txt, $backLabel, $exData);
			return;
		case 'getNum':
			confirmPurchase($exData[1], $txt, $backLabel);
			return;
		case 'getNumber':
			handlePurchase($exData[1], $txt);
			return;
		case 'getCode':
			deliverCode($exData, $txt);
			return;
	}
}
