<?php
/**
 * Al-Falah Marketing — Backend Config
 * Hostinger-compatible (PHP 8+)
 */

// ---- SECRETS ----
// Never commit secrets. Put them in api/config.local.php (git-ignored, server only)
// or in environment variables. See api/config.local.example.php.
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}
defined('ADMIN_USER') || define('ADMIN_USER', getenv('ALFALAH_ADMIN_USER') ?: 'admin');
defined('ADMIN_PASS') || define('ADMIN_PASS', getenv('ALFALAH_ADMIN_PASS') ?: ''); // empty = no admin account is created

// ---- SITE SETTINGS ----
define('NOTIFY_EMAIL', 'info@al-falahmarketing.com'); // receives form alerts
define('SITE_NAME', 'Al-Falah Marketing');
define('SITE_URL', 'https://al-falahmarketing.com');
define('WHATSAPP_NUMBER', '224611351302'); // no + or spaces

// ---- AI Assistant (OpenAI) ----
// Paste your OpenAI API key below (https://platform.openai.com/api-keys)
defined('OPENAI_API_KEY') || define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: 'PASTE_YOUR_OPENAI_API_KEY_HERE');
define('OPENAI_MODEL', getenv('OPENAI_MODEL') ?: 'gpt-4o-mini');
define('OPENAI_BASE_URL', getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1/chat/completions');



// Database: SQLite (default, zero setup) or MySQL
defined('DB_DRIVER') || define('DB_DRIVER', 'sqlite'); // 'sqlite' or 'mysql'

// MySQL only (create DB in Hostinger hPanel → Databases)
defined('MYSQL_HOST') || define('MYSQL_HOST', 'localhost');
defined('MYSQL_NAME') || define('MYSQL_NAME', 'u123456789_alfalah');
defined('MYSQL_USER') || define('MYSQL_USER', 'u123456789_alfalah');
defined('MYSQL_PASS') || define('MYSQL_PASS', '');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('DATA_PATH', ROOT_PATH . '/data');
define('SQLITE_FILE', DATA_PATH . '/alfalah.db');

// CORS / security
header('X-Content-Type-Options: nosniff');

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    if (!is_dir(DATA_PATH)) {
        mkdir(DATA_PATH, 0755, true);
    }

    if (DB_DRIVER === 'mysql') {
        $dsn = 'mysql:host=' . MYSQL_HOST . ';dbname=' . MYSQL_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, MYSQL_USER, MYSQL_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } else {
        $pdo = new PDO('sqlite:' . SQLITE_FILE, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contacts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT,
        message TEXT,
        source TEXT DEFAULT 'contact',
        ip TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        status TEXT DEFAULT 'new'
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS subscribers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        lang TEXT DEFAULT 'en',
        ip TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        status TEXT DEFAULT 'active'
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        title_en TEXT NOT NULL,
        title_fr TEXT NOT NULL,
        excerpt_en TEXT DEFAULT '',
        excerpt_fr TEXT DEFAULT '',
        body_en TEXT DEFAULT '',
        body_fr TEXT DEFAULT '',
        category_en TEXT DEFAULT 'BLOG',
        category_fr TEXT DEFAULT 'BLOG',
        image_url TEXT DEFAULT '',
        published_at TEXT DEFAULT CURRENT_TIMESTAMP,
        status TEXT DEFAULT 'published',
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )");
    // Seed default admin from config if table is empty
    $count = (int) $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
    if ($count === 0 && ADMIN_PASS !== '') {
        $hash = password_hash(ADMIN_PASS, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([ADMIN_USER, $hash]);
    }
    // MySQL uses AUTO_INCREMENT differently — for MySQL recreate with proper syntax if needed
    if (DB_DRIVER === 'mysql') {
        // Tables created above work on SQLite; for MySQL Hostinger often needs:
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS contacts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(200) NOT NULL,
                email VARCHAR(200) NOT NULL,
                phone VARCHAR(50),
                message TEXT,
                source VARCHAR(50) DEFAULT 'contact',
                ip VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status VARCHAR(20) DEFAULT 'new'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("CREATE TABLE IF NOT EXISTS subscribers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(200) NOT NULL UNIQUE,
                lang VARCHAR(5) DEFAULT 'en',
                ip VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status VARCHAR(20) DEFAULT 'active'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {
            // ignore if already exists from first attempt
        }
    }
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function client_ip(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '';
}

function sanitize(string $v): string {
    return trim(strip_tags($v));
}

function legacy_admin_pass_matches(string $pass): bool {
    return ADMIN_PASS !== '' && hash_equals(ADMIN_PASS, $pass);
}

function send_notify(string $subject, string $body): void {
    $to = NOTIFY_EMAIL;
    $headers = "From: noreply@" . parse_url(SITE_URL, PHP_URL_HOST) . "\r\n";
    $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
    @mail($to, $subject, $body, $headers);
}


function verify_admin_login(string $user, string $pass): bool {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE username = ? LIMIT 1");
    $stmt->execute([$user]);
    $row = $stmt->fetch();
    if (!$row) {
        // Fallback to config constants (first deploy / empty DB edge case)
        return hash_equals(ADMIN_USER, $user) && legacy_admin_pass_matches($pass);
    }
    return password_verify($pass, $row['password_hash']);
}

function change_admin_password(string $current, string $new): array {
    if (strlen($new) < 8) {
        return ['ok' => false, 'error' => 'New password must be at least 8 characters.'];
    }
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, password_hash FROM admin_users WHERE username = ? LIMIT 1");
    $stmt->execute([ADMIN_USER]);
    $row = $stmt->fetch();
    if (!$row) {
        // Create row from config if missing
        if (!legacy_admin_pass_matches($current)) {
            // try plain config match
            if (!legacy_admin_pass_matches($current)) {
                return ['ok' => false, 'error' => 'Current password is incorrect.'];
            }
        } elseif (!legacy_admin_pass_matches($current)) {
            return ['ok' => false, 'error' => 'Current password is incorrect.'];
        }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?)")->execute([ADMIN_USER, $hash]);
        return ['ok' => true];
    }
    if (!password_verify($current, $row['password_hash'])) {
        // Also accept legacy config password once (migration)
        if (!legacy_admin_pass_matches($current)) {
            return ['ok' => false, 'error' => 'Current password is incorrect.'];
        }
    }
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $upd = $pdo->prepare("UPDATE admin_users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $upd->execute([$hash, $row['id']]);
    return ['ok' => true];
}

function require_admin(): void {
    session_start();
    if (empty($_SESSION['alfalah_admin'])) {
        header('Location: login.php');
        exit;
    }
}
