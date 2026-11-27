<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/includes/bootstrap.php';
require_admin();

$pageTitle = 'Ocasiões';
$activeNav = 'admin';
$statement = db()->query(
    'SELECT id, name, min_temp_c, max_temp_c
     FROM occasions
     ORDER BY name'
);
$occasions = $statement->fetchAll();

require APP_ROOT . '/app/views/header.php';
?>
<section class="admin-heading">
    <div>
        <p class="eyebrow">PAINEL ADMINISTRATIVO</p>
        <h1>Ocasiões</h1>
        <p>Defina as faixas de temperatura para orientar as sugestões de look.</p>
    </div>
    <a class="button button-primary" href="<?= e(app_url('admin/ocasiao.php')) ?>">
        + Nova ocasião
    </a>
</section>

<section class="admin-list-panel panel">
    <div class="list-heading">
        <div>
            <p class="eyebrow">CATÁLOGO</p>
            <h2>Ocasiões <span class="count-badge"><?= count($occasions) ?></span></h2>
        </div>
        <span class="list-note">Faixa de temperatura indicada</span>
    </div>

    <?php if ($occasions === []): ?>
        <div class="empty-closet">
            <div>
                <h3>Nenhuma ocasião cadastrada.</h3>
                <p>Adicione ocasiões para orientar as sugestões de look.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="occasion-list">
            <?php foreach ($occasions as $occasion): ?>
                <article class="occasion-row">
                    <div>
                        <strong><?= e($occasion['name']) ?></strong>
                        <span>
                            <?= e(number_format((float) $occasion['min_temp_c'], 0, ',', '.')) ?>°C
                            a
                            <?= e(number_format((float) $occasion['max_temp_c'], 0, ',', '.')) ?>°C
                        </span>
                    </div>
                    <div class="occasion-actions">
                        <a
                            class="button button-outline"
                            href="<?= e(app_url('admin/ocasiao.php?id=' . (int) $occasion['id'])) ?>"
                        >
                            Editar
                        </a>
                        <a
                            class="button button-danger"
                            href="<?= e(app_url('admin/excluir-ocasiao.php?id=' . (int) $occasion['id'])) ?>"
                        >
                            Excluir
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
