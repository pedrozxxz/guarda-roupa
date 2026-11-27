<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/includes/bootstrap.php';
require_admin();

$pdo = db();
$occasionId = filter_var(
    $_GET['id'] ?? $_POST['occasion_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($occasionId === false || $occasionId === null) {
    set_flash('error', 'A ocasião solicitada não é válida.');
    redirect_to('admin/ocasioes.php');
}

$loadStatement = $pdo->prepare(
    'SELECT id, name, min_temp_c, max_temp_c
     FROM occasions
     WHERE id = :id
     LIMIT 1'
);
$loadStatement->execute(['id' => $occasionId]);
$occasion = $loadStatement->fetch() ?: null;

if (!is_array($occasion)) {
    set_flash('error', 'A ocasião não foi encontrada.');
    redirect_to('admin/ocasioes.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        try {
            $deleteStatement = $pdo->prepare(
                'DELETE FROM occasions WHERE id = :id'
            );
            $deleteStatement->execute(['id' => $occasionId]);
            set_flash('success', 'Ocasião excluída.');
            redirect_to('admin/ocasioes.php');
        } catch (PDOException $exception) {
            error_log('[Vestí] Falha ao excluir ocasião: ' . $exception->getMessage());
            $errors[] = 'Não foi possível excluir a ocasião. Tente novamente.';
        }
    }
}

$pageTitle = 'Excluir ocasião';
$activeNav = 'admin';
require APP_ROOT . '/app/views/header.php';
?>
<section class="admin-heading">
    <div>
        <p class="eyebrow">CATÁLOGO DE OCASIÕES</p>
        <h1>Excluir ocasião</h1>
        <p>Confirme se deseja remover esta opção do catálogo.</p>
    </div>
</section>

<section class="admin-form-wrap">
    <div class="admin-form-card panel confirm-card">
        <?php if ($errors !== []): ?>
            <div class="form-errors" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <p class="eyebrow">CONFIRMAR REMOÇÃO</p>
        <h2><?= e($occasion['name']) ?></h2>
        <p class="confirm-range">
            Faixa indicada:
            <?= e(number_format((float) $occasion['min_temp_c'], 0, ',', '.')) ?>°C
            a
            <?= e(number_format((float) $occasion['max_temp_c'], 0, ',', '.')) ?>°C
        </p>
        <form method="post" action="<?= e(app_url('admin/excluir-ocasiao.php?id=' . (int) $occasionId)) ?>" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="occasion_id" value="<?= (int) $occasionId ?>">
            <button class="button button-danger button-full" type="submit">
                Excluir ocasião
            </button>
            <a class="button button-outline button-full" href="<?= e(app_url('admin/ocasioes.php')) ?>">Cancelar</a>
        </form>
    </div>
</section>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
