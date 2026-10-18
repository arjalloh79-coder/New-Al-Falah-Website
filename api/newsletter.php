<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$email = sanitize($input['email'] ?? '');
$lang  = sanitize($input['lang'] ?? 'en');
if (!in_array($lang, ['en', 'fr'], true)) $lang = 'en';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'error' => 'Valid email required'], 422);
}

try {
    $pdo = db();
    // Upsert-like for SQLite
    $exists = $pdo->prepare('SELECT id FROM subscribers WHERE email = ?');
    $exists->execute([$email]);
    if ($exists->fetch()) {
        json_response(['ok' => true, 'message' => 'Already subscribed', 'already' => true]);
    }
    $stmt = $pdo->prepare('INSERT INTO subscribers (email, lang, ip) VALUES (?, ?, ?)');
    $stmt->execute([$email, $lang, client_ip()]);

    send_notify(
        '[Al-Falah] New newsletter subscriber',
        "Email: $email\nLang: $lang\n"
    );

    json_response([
        'ok' => true,
        'message' => $lang === 'fr'
            ? 'Merci ! Vous êtes inscrit à notre newsletter.'
            : 'Thank you! You are subscribed to our newsletter.',
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Could not subscribe'], 500);
}
