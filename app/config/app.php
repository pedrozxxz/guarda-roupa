<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Vestí');
}

if (!defined('APP_SLOGAN')) {
    define('APP_SLOGAN', 'Seu guarda-roupa, do seu jeito.');
}

// Defina VESTI_APP_ENV=production no servidor publicado.
if (!defined('APP_ENV')) {
    define('APP_ENV', getenv('VESTI_APP_ENV') ?: 'local');
}

// O link de redefinição só aparece na tela durante a demonstração local.
if (!defined('APP_SHOW_RESET_LINK')) {
    define('APP_SHOW_RESET_LINK', APP_ENV === 'local');
}

if (!defined('APP_SESSION_NAME')) {
    define('APP_SESSION_NAME', 'vesti_session');
}

date_default_timezone_set('America/Fortaleza');
