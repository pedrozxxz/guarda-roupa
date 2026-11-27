<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

require_once APP_ROOT . '/app/config/app.php';
require_once APP_ROOT . '/app/config/database.php';
require_once APP_ROOT . '/app/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_name(APP_SESSION_NAME);

    $secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $cookiePath = app_base_path() !== '' ? app_base_path() : '/';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

require_once APP_ROOT . '/app/includes/csrf.php';
require_once APP_ROOT . '/app/includes/auth.php';
