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

$name    = sanitize($input['name'] ?? '');
$email   = sanitize($input['email'] ?? '');
$phone   = sanitize($input['phone'] ?? '');
$message = sanitize($input['message'] ?? '');
$source  = sanitize($input['source'] ?? 'contact');

if ($name === '' || $email === '') {
    json_response(['ok' => false, 'error' => 'Name and email are required'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'error' => 'Invalid email'], 422);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO contacts (name, email, phone, message, source, ip) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $phone, $message, $source, client_ip()]);

    $body = "New contact from " . SITE_NAME . "\n\n"
          . "Name: $name\nEmail: $email\nPhone: $phone\nSource: $source\n\nMessage:\n$message\n";
    send_notify('[Al-Falah] New contact: ' . $name, $body);

    // WhatsApp deep-link for frontend (optional redirect)
    $waText = rawurlencode("Hello Al-Falah, my name is $name.\nEmail: $email\nPhone: $phone\n\n$message");
    $waUrl = 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . $waText;

    json_response([
        'ok' => true,
        'message' => 'Thank you! We will contact you shortly.',
        'whatsapp_url' => $waUrl,
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Server error. Please try WhatsApp.'], 500);
}
