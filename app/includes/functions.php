<?php
declare(strict_types=1);

function e(mixed $value): string
{
    if ($value === null || (!is_string($value) && !is_numeric($value))) {
        $value = '';
    }

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function app_base_path(): string
{
    $scriptName = str_replace(
        '\\',
        '/',
        (string) ($_SERVER['SCRIPT_NAME'] ?? '/')
    );
    $publicMarker = '/public/';
    $publicPosition = strpos($scriptName, $publicMarker);

    if ($publicPosition !== false) {
        return substr($scriptName, 0, $publicPosition) . '/public';
    }

    // Também permite configurar o DocumentRoot diretamente na pasta public.
    $directory = str_replace('\\', '/', dirname($scriptName));

    if ($directory === '.' || $directory === '/' || $directory === '\\') {
        return '';
    }

    return rtrim($directory, '/');
}

function app_url(string $path = ''): string
{
    $basePath = app_base_path();
    $cleanPath = ltrim($path, '/');

    if ($cleanPath === '') {
        return $basePath === '' ? '/' : $basePath . '/';
    }

    return $basePath . '/' . $cleanPath;
}

function text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}

function set_flash(string $type, string $message): void
{
    if (!in_array($type, ['success', 'error', 'info'], true)) {
        $type = 'info';
    }

    $_SESSION['_flash_message'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function take_flash(): ?array
{
    $flash = $_SESSION['_flash_message'] ?? null;
    unset($_SESSION['_flash_message']);

    if (!is_array($flash) || !isset($flash['type'], $flash['message'])) {
        return null;
    }

    return [
        'type' => in_array($flash['type'], ['success', 'error', 'info'], true)
            ? $flash['type']
            : 'info',
        'message' => (string) $flash['message'],
    ];
}

function redirect_to(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}
