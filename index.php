<?php

declare(strict_types=1);

use Numbers\Language\LanguageManager;
use Numbers\Storage\JsonStorage;
use Numbers\Telegram\TelegramClient;

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/vals.php';
require_once __DIR__ . '/contries.php';
require_once __DIR__ . '/api.php';

$languageManager = new LanguageManager(require BASE_PATH . '/lang/translations.php');
$storage = new JsonStorage([
    'points' => BASE_PATH . '/points.json',
    'stats' => BASE_PATH . '/stats.json',
    'operations' => BASE_PATH . '/operations.json',
    'invites' => BASE_PATH . '/invites.json',
    'bans' => BASE_PATH . '/bans.json',
    'info' => BASE_PATH . '/info.json',
    'contries' => BASE_PATH . '/contries.json',
    'langs' => BASE_PATH . '/langs.json',
]);

$points = $storage->load('points', []);
$stats = $storage->load('stats', []);
$op = $storage->load('operations', []);
$invite = $storage->load('invites', []);
$bans = $storage->load('bans', []);
$info = $storage->load('info', []);
$contries = $storage->load('contries', []);
$langs = $storage->load('langs', []);

$telegramClient = new TelegramClient($token);
define('API_KEY', $token);

function bot(string $method, array $datas = [])
{
    global $telegramClient;

    try {
        $response = $telegramClient->call($method, $datas);
        if ($response === null) {
            return null;
        }
        return json_decode(json_encode($response), false);
    } catch (Throwable $e) {
        return null;
    }
}

$link = "https://" . $_SERVER["SERVER_NAME"] . $_SERVER["PHP_SELF"];
if (isset($_GET['setup_webhook'])) {
    $response = bot('setWebhook', ['url' => $link]);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function check_member($id, $chat)
{
    $response = bot('getChatMember', ["chat_id" => $chat, "user_id" => $id]);
    $status = $response->result->status ?? null;
    if ($status === 'left' || $status === 'kicked' || $status === null) {
        return false;
    }
    return true;
}

function mkBtn($btn)
{
    $res = array();
    foreach ($btn as $d) {
        $r = array();
        foreach ($d as $k => $v) {
            $r[] = ['text' => $k, 'callback_data' => $v];
        }
        $res[] = $r;
    }
    return $res;
}

function send($text, $btn = null, $idValue = null)
{
    if ($idValue == null) {
        global $id;
        $idValue = $id;
    }
    $data = array();
    $data['chat_id'] = $idValue;
    $data['text'] = $text;
    $data['parse_mode'] = 'html';
    if ($btn != null) {
        $data['reply_markup'] = json_encode([
            'inline_keyboard' => $btn
        ]);
    }
    return bot('sendMessage', $data);
}

function edit($text, $btn = null)
{
    $data = array();
    global $id;
    global $message_id;
    $data['chat_id'] = $id;
    $data['text'] = $text;
    $data['parse_mode'] = 'html';
    $data['message_id'] = $message_id;
    if ($btn != null) {
        $data['reply_markup'] = json_encode([
            'inline_keyboard' => $btn
        ]);
    }
    return bot('editMessageText', $data);
}

function savePoint()
{
    global $points, $storage;
    $storage->persist('points', $points);
}

function saveStats()
{
    global $stats, $storage;
    $storage->persist('stats', $stats);
}

function saveOp()
{
    global $op, $storage;
    $storage->persist('operations', $op);
}

function saveInvite()
{
    global $invite, $storage;
    $storage->persist('invites', $invite);
}

function saveBans()
{
    global $bans, $storage;
    $storage->persist('bans', $bans);
}

function saveInfo()
{
    global $info, $storage;
    $storage->persist('info', $info);
}

function saveContries()
{
    global $contries, $storage;
    $storage->persist('contries', $contries);
}

$back = mkBtn(array(
    array(
        "رجوع" => "back"
    )
));

$payload = file_get_contents('php://input');
if (!$payload) {
    exit;
}
$update = json_decode($payload);
if (!$update) {
    exit;
}

if (isset($update->message)) {
    $message = $update->message;
    $chat_id = $message->chat->id ?? null;
    $text = $message->text ?? '';
    $ex = explode(" ", $text ?? '');
    $first_name = $message->from->first_name ?? '';
    $username = $message->from->username ?? '';
    $id = $message->from->id ?? 0;
    $message_id = $message->message_id ?? 0;
    $entities = $message->entities ?? [];
    $language_code = $message->from->language_code ?? 'ar';
    $tc = $message->chat->type ?? 'private';
    $re_message = $message->reply_to_message ?? null;
    $re_text = $re_message->text ?? null;
    $data = null;
    $exData = [];
} elseif (isset($update->callback_query)) {
    $chat_id = $update->callback_query->message->chat->id ?? null;
    $id = $update->callback_query->from->id ?? 0;
    $first_name = $update->callback_query->from->first_name ?? '';
    $message_id = $update->callback_query->message->message_id ?? 0;
    $data = $update->callback_query->data ?? '';
    $exData = explode("#", $data);
    $text = null;
    $ex = [];
    $username = $update->callback_query->from->username ?? '';
    $entities = [];
    $language_code = $update->callback_query->from->language_code ?? 'ar';
    $tc = $update->callback_query->message->chat->type ?? 'private';
    $re_message = null;
    $re_text = null;
} else {
    exit;
}

$point = $points[$id] ?? 0;

$api = new Api($api_key);
if ($id == $admin) {
    $balance = $api->getBalance() ?? 0;
    require "admin.php";
} else {
    require "member.php";
}