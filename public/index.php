<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/includes/bootstrap.php';

$pageTitle = 'Vista o que você já tem';
$activeNav = 'home';
$user = current_user();
$photoFile = 'editorial-chair.png';
$photoAlt = 'Mulher em um closet, sentada em uma poltrona e lendo uma revista de moda';

require APP_ROOT . '/app/views/header.php';
?>
<section class="landing-hero">
    <div class="landing-copy">
        <p class="eyebrow">GUARDA-ROUPA INTELIGENTE</p>
        <h1>Vista o que você<br>já tem.</h1>
        <p class="landing-description">
            O Vestí combina suas peças com o clima e a ocasião. Você escolhe
            o look e ele separa as roupas para você.
        </p>
        <div class="landing-actions">
            <?php if ($user === null): ?>
                <a class="button button-primary" href="<?= e(app_url('cadastro.php')) ?>">
                    Começar agora
                </a>
            <?php else: ?>
                <a class="button button-primary" href="<?= e(app_url('cadastro-peca.php')) ?>">
                    Adicionar peça
                </a>
                <a class="text-link" href="<?= e(app_url('escolher-look.php')) ?>">
                    Montar um look
                </a>
            <?php endif; ?>
            <?php if ($user === null): ?>
                <a class="text-link" href="<?= e(app_url('login.php')) ?>">Já tenho uma conta</a>
            <?php endif; ?>
        </div>
    </div>

    <?php require APP_ROOT . '/app/views/photo-panel.php'; ?>
</section>

<section class="feature-row" aria-label="Recursos do Vestí">
    <article class="feature-item">
        <h2>Seu acervo</h2>
        <p>Cadastre as peças que você tem e mantenha seu guarda-roupa organizado.</p>
        <a class="feature-link" href="<?= e(app_url($user === null ? 'cadastro.php' : 'meu-acervo.php')) ?>">
            Abrir meu acervo
        </a>
    </article>
    <article class="feature-item">
        <h2>Seu próximo look</h2>
        <p>Escolha a ocasião e receba uma combinação adequada ao clima.</p>
        <a class="feature-link" href="<?= e(app_url($user === null ? 'login.php' : 'escolher-look.php')) ?>">
            Escolher ocasião
        </a>
    </article>
</section>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
