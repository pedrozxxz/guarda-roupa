<?php
declare(strict_types=1);

// Os padrões abaixo servem para o XAMPP local. Em produção, use variáveis
// de ambiente com nomes VESTI_DB_HOST, VESTI_DB_PORT, VESTI_DB_NAME,
// VESTI_DB_USER e VESTI_DB_PASS.
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('VESTI_DB_HOST') ?: '127.0.0.1');
}

if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('VESTI_DB_PORT') ?: '3306');
}

if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('VESTI_DB_NAME') ?: 'guarda_roupa_estilista');
}

if (!defined('DB_USER')) {
    define('DB_USER', getenv('VESTI_DB_USER') ?: 'root');
}

if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('VESTI_DB_PASS') ?: '');
}

if (!function_exists('db')) {
    function db(): PDO
    {
        static $connection = null;

        if ($connection instanceof PDO) {
            return $connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        try {
            $connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            error_log('[Vestí] Falha na conexão PDO: ' . $exception->getMessage());
            throw $exception;
        }

        return $connection;
    }
}
