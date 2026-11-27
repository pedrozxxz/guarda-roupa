<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/includes/bootstrap.php';
require_admin();

$pdo = db();
$pageTitle = 'Nova ocasião';
$activeNav = 'admin';
$occasionId = null;
$occasion = null;
$errors = [];
$values = [
    'name' => '',
    'min_temp_c' => '18',
    'max_temp_c' => '30',
];

if (isset($_GET['id'])) {
    $occasionId = filter_var(
        $_GET['id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($occasionId === false) {
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

    $pageTitle = 'Editar ocasião';
    $values = [
        'name' => (string) $occasion['name'],
        'min_temp_c' => (string) $occasion['min_temp_c'],
        'max_temp_c' => (string) $occasion['max_temp_c'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedId = filter_var(
        $_POST['occasion_id'] ?? '',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($postedId !== false) {
        $occasionId = $postedId;
    }

    $values['name'] = trim(
        is_string($_POST['name'] ?? null) ? $_POST['name'] : ''
    );
    $values['min_temp_c'] = trim(
        is_string($_POST['min_temp_c'] ?? null) ? $_POST['min_temp_c'] : ''
    );
    $values['max_temp_c'] = trim(
        is_string($_POST['max_temp_c'] ?? null) ? $_POST['max_temp_c'] : ''
    );
    $minTemp = filter_var($values['min_temp_c'], FILTER_VALIDATE_FLOAT);
    $maxTemp = filter_var($values['max_temp_c'], FILTER_VALIDATE_FLOAT);

    if (!csrf_is_valid($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    }
    if (text_length($values['name']) < 2 || text_length($values['name']) > 80) {
        $errors[] = 'Informe um nome com até 80 caracteres.';
    }
    if ($minTemp === false || $minTemp < -10 || $minTemp > 55) {
        $errors[] = 'A temperatura mínima deve ficar entre -10°C e 55°C.';
    }
    if ($maxTemp === false || $maxTemp < -10 || $maxTemp > 55) {
        $errors[] = 'A temperatura máxima deve ficar entre -10°C e 55°C.';
    }
    if ($minTemp !== false && $maxTemp !== false && $minTemp > $maxTemp) {
        $errors[] = 'A temperatura mínima não pode superar a máxima.';
    }

    if ($errors === []) {
        try {
            if ($occasionId === null) {
                $saveStatement = $pdo->prepare(
                    'INSERT INTO occasions (name, min_temp_c, max_temp_c)
                     VALUES (:name, :min_temp_c, :max_temp_c)'
                );
                $saveStatement->execute([
                    'name' => $values['name'],
                    'min_temp_c' => $minTemp,
                    'max_temp_c' => $maxTemp,
                ]);
                set_flash('success', 'Ocasião cadastrada.');
            } else {
                $saveStatement = $pdo->prepare(
                    'UPDATE occasions
                     SET name = :name, min_temp_c = :min_temp_c, max_temp_c = :max_temp_c
                     WHERE id = :id'
                );
                $saveStatement->execute([
                    'name' => $values['name'],
                    'min_temp_c' => $minTemp,
                    'max_temp_c' => $maxTemp,
                    'id' => $occasionId,
                ]);
                set_flash('success', 'Ocasião atualizada.');
            }

            redirect_to('admin/ocasioes.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $errors[] = 'Já existe uma ocasião com esse nome.';
            } else {
                error_log('[Vestí] Falha ao salvar ocasião: ' . $exception->getMessage());
                $errors[] = 'Não foi possível salvar a ocasião. Tente novamente.';
            }
        }
    }
}

require APP_ROOT . '/app/views/header.php';
?>
<section class="admin-heading">
    <div>
        <p class="eyebrow">CATÁLOGO DE OCASIÕES</p>
        <h1><?= $occasionId === null ? 'Nova ocasião' : 'Editar ocasião' ?></h1>
        <p>Defina a faixa de temperatura indicada para cada situação.</p>
    </div>
    <a class="button button-outline" href="<?= e(app_url('admin/ocasioes.php')) ?>">Voltar às ocasiões</a>
</section>

<section class="admin-form-wrap">
    <div class="admin-form-card panel">
        <?php if ($errors !== []): ?>
            <div class="form-errors" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= e(app_url('admin/ocasiao.php' . ($occasionId ? '?id=' . $occasionId : ''))) ?>" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="occasion_id" value="<?= e($occasionId ?? '') ?>">
            <div class="form-field">
                <label for="name">Nome da ocasião</label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    maxlength="80"
                    placeholder="Ex.: Trabalho"
                    value="<?= e($values['name']) ?>"
                    required
                >
            </div>
            <div class="form-two">
                <div class="form-field">
                    <label for="min_temp_c">Temperatura mínima</label>
                    <div class="input-suffix">
                        <input id="min_temp_c" name="min_temp_c" type="number" min="-10" max="55" step="0.5" value="<?= e($values['min_temp_c']) ?>" required>
                        <span>°C</span>
                    </div>
                </div>
                <div class="form-field">
                    <label for="max_temp_c">Temperatura máxima</label>
                    <div class="input-suffix">
                        <input id="max_temp_c" name="max_temp_c" type="number" min="-10" max="55" step="0.5" value="<?= e($values['max_temp_c']) ?>" required>
                        <span>°C</span>
                    </div>
                </div>
            </div>
            <div class="occasion-form-actions">
                <button class="button button-primary" type="submit">
                    <?= $occasionId === null ? 'Cadastrar ocasião' : 'Salvar alterações' ?>
                </button>
                <a class="button button-outline" href="<?= e(app_url('admin/ocasioes.php')) ?>">Cancelar</a>
            </div>
        </form>
    </div>
</section>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
