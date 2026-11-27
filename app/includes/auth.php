<?php
declare(strict_types=1);

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;

    if (
        !is_array($user) ||
        !isset($user['id'], $user['full_name'], $user['email'], $user['role'])
    ) {
        return null;
    }

    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (is_logged_in()) {
        return;
    }

    set_flash('info', 'Entre na sua conta para continuar.');
    redirect_to('login.php');
}

function require_admin(): void
{
    if (!is_logged_in()) {
        set_flash('info', 'Entre com uma conta administradora para continuar.');
        redirect_to('admin/login.php');
    }

    if ((current_user()['role'] ?? '') === 'admin') {
        return;
    }

    http_response_code(403);
    $pageTitle = 'Acesso restrito';

    require APP_ROOT . '/app/views/header.php';
    ?>
    <section class="message-card">
        <p class="eyebrow">ACESSO RESTRITO</p>
        <h1>Esta área é exclusiva da administração.</h1>
        <p>Entre com uma conta administradora para continuar.</p>
        <a class="button button-primary" href="<?= e(app_url('index.php')) ?>">
            Voltar ao início
        </a>
    </section>
    <?php
    require APP_ROOT . '/app/views/footer.php';
    exit;
}
