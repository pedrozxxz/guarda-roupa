<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

if (!csrf_is_valid($_POST['_csrf'] ?? null)) {
    http_response_code(419);
    exit('Sua sessão expirou. Volte à página anterior e tente novamente.');
}

$_SESSION = [];
$cookieParameters = session_get_cookie_params();

if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookieParameters['path'],
        'domain' => $cookieParameters['domain'],
        'secure' => $cookieParameters['secure'],
        'httponly' => $cookieParameters['httponly'],
        'samesite' => $cookieParameters['samesite'] ?? 'Lax',
    ]);
}

session_destroy();
header('Location: ' . app_url('login.php?logged_out=1'));
exit;
