<?php
/**
 * Application bootstrap.
 * Loads .env, sets timezone, starts session, and includes DB helpers.
 */

// Load .env
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value, '"\'');
        putenv(trim($key) . '=' . trim($value, '"\''));
    }
}

// Timezone
date_default_timezone_set($_ENV['TIMEZONE'] ?? 'Asia/Jakarta');

// Start session
$sessionName = $_ENV['SESSION_NAME'] ?? 'abi_taman_session';
if (session_status() === PHP_SESSION_NONE) {
    session_name($sessionName);
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

// Include DB helpers
require_once __DIR__ . '/database.php';
