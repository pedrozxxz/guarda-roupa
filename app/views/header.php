<?php
declare(strict_types=1);

$pageTitle = isset($pageTitle) && is_string($pageTitle)
    ? $pageTitle
    : APP_NAME;
$activeNav = isset($activeNav) && is_string($activeNav)
    ? $activeNav
    : '';
$signedInUser = current_user();
$flashMessage = take_flash();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f0e9">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
    <script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
</head>
<body>
<div class="site-shell">
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="<?= e(app_url('index.php')) ?>" aria-label="Vestí, início">
                <img
                    class="brand-logo"
                    src="<?= e(app_url('assets/images/vesti-logo.png')) ?>"
                    alt="Vestí"
                >
            </a>

            <button
                class="menu-toggle"
                type="button"
                aria-label="Abrir navegação"
                aria-controls="main-nav"
                aria-expanded="false"
                data-menu-toggle
            >
                <span></span>
                <span></span>
            </button>

            <nav class="main-nav" id="main-nav" aria-label="Navegação principal">
                <?php if ($signedInUser !== null): ?>
                    <a
                        class="nav-link<?= $activeNav === 'wardrobe' ? ' is-active' : '' ?>"
                        href="<?= e(app_url('meu-acervo.php')) ?>"
                    >
                        Meu acervo
                    </a>
                    <a
                        class="nav-link<?= $activeNav === 'looks' ? ' is-active' : '' ?>"
                        href="<?= e(app_url('escolher-look.php')) ?>"
                    >
                        Montar look
                    </a>
                    <?php if (($signedInUser['role'] ?? '') === 'admin'): ?>
                        <a
                            class="nav-link<?= $activeNav === 'admin' ? ' is-active' : '' ?>"
                            href="<?= e(app_url('admin/ocasioes.php')) ?>"
                        >
                            Ocasiões
                        </a>
                    <?php endif; ?>
                    <a class="button button-small button-primary nav-add-piece" href="<?= e(app_url('cadastro-peca.php')) ?>">
                        + Adicionar peça
                    </a>
                    <form class="nav-logout" method="post" action="<?= e(app_url('logout.php')) ?>">
                        <?= csrf_field() ?>
                        <button class="nav-link nav-link-button" type="submit">Sair</button>
                    </form>
                <?php else: ?>
                    <a
                        class="nav-link<?= $activeNav === 'login' ? ' is-active' : '' ?>"
                        href="<?= e(app_url('login.php')) ?>"
                    >
                        Entrar
                    </a>
                    <a
                        class="button button-small button-primary<?= $activeNav === 'register' ? ' is-active' : '' ?>"
                        href="<?= e(app_url('cadastro.php')) ?>"
                    >
                        Criar conta
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="page-content" id="main-content">
        <?php if ($flashMessage !== null): ?>
            <div
                class="flash flash-<?= e($flashMessage['type']) ?>"
                role="<?= $flashMessage['type'] === 'error' ? 'alert' : 'status' ?>"
            >
                <?= e($flashMessage['message']) ?>
            </div>
        <?php endif; ?>
