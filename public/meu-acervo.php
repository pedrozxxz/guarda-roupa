<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/includes/bootstrap.php';
require_login();

$user = current_user();
$userId = (int) $user['id'];
$looksOnly = isset($looksOnly) && $looksOnly === true;
$pageTitle = $looksOnly ? 'Montar look' : 'Meu acervo';
$activeNav = $looksOnly ? 'looks' : 'wardrobe';
$errors = [];
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    if (!csrf_is_valid($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } elseif ($action === 'delete_garment') {
        $garmentId = filter_var(
            $_POST['garment_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($garmentId === false) {
            $errors[] = 'A peça selecionada não é válida.';
        } else {
            $delete = $pdo->prepare(
                'DELETE FROM garments WHERE id = :id AND user_id = :user_id'
            );
            $delete->execute([
                'id' => $garmentId,
                'user_id' => $userId,
            ]);
            set_flash('success', 'Peça removida do seu acervo.');
            redirect_to('meu-acervo.php');
        }
    } elseif ($action === 'suggest_look') {
        $occasionId = filter_var(
            $_POST['occasion_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $temperatureRaw = is_string($_POST['temperature_c'] ?? null)
            ? trim($_POST['temperature_c'])
            : '';
        $humidityRaw = is_string($_POST['humidity_pct'] ?? null)
            ? trim($_POST['humidity_pct'])
            : '';
        $temperature = filter_var($temperatureRaw, FILTER_VALIDATE_FLOAT);
        $humidity = filter_var($humidityRaw, FILTER_VALIDATE_FLOAT);
        $period = is_string($_POST['day_period'] ?? null)
            ? $_POST['day_period']
            : '';

        if ($occasionId === false) {
            $errors[] = 'Escolha uma ocasião.';
        }
        if ($temperature === false || $temperature < -10 || $temperature > 55) {
            $errors[] = 'Informe uma temperatura entre -10°C e 55°C.';
        }
        if ($humidity === false || $humidity < 0 || $humidity > 100) {
            $errors[] = 'Informe uma umidade entre 0% e 100%.';
        }
        if (!in_array($period, ['manha', 'tarde', 'noite', 'madrugada'], true)) {
            $errors[] = 'Escolha um período do dia.';
        }

        if ($errors === []) {
            $occasionStatement = $pdo->prepare(
                'SELECT id, name, min_temp_c, max_temp_c
                 FROM occasions WHERE id = :id LIMIT 1'
            );
            $occasionStatement->execute(['id' => $occasionId]);
            $occasion = $occasionStatement->fetch();

            if (!is_array($occasion)) {
                $errors[] = 'A ocasião selecionada não está disponível.';
            } else {
                $garmentStatement = $pdo->prepare(
                    'SELECT id, name, category, color, style, min_temp_c, max_temp_c, image_path
                     FROM garments
                     WHERE user_id = :user_id
                       AND min_temp_c <= :min_temperature
                       AND max_temp_c >= :max_temperature'
                );
                $garmentStatement->execute([
                    'user_id' => $userId,
                    'min_temperature' => $temperature,
                    'max_temperature' => $temperature,
                ]);
                $compatibleGarments = $garmentStatement->fetchAll();
                $preferredStyle = (string) $user['preferred_style'];

                // Prioriza o estilo escolhido e depois peças versáteis.
                usort(
                    $compatibleGarments,
                    static function (array $first, array $second) use ($preferredStyle): int {
                        $score = static function (array $garment) use ($preferredStyle): int {
                            if (strcasecmp((string) $garment['style'], $preferredStyle) === 0) {
                                return 2;
                            }
                            if (strcasecmp((string) $garment['style'], 'Versátil') === 0) {
                                return 1;
                            }
                            return 0;
                        };

                        return ($score($second) <=> $score($first))
                            ?: ((int) $first['id'] <=> (int) $second['id']);
                    }
                );

                $selectedGarments = [];
                $usedCategories = [];
                foreach ($compatibleGarments as $garment) {
                    $category = (string) $garment['category'];
                    if (isset($usedCategories[$category])) {
                        continue;
                    }
                    $selectedGarments[] = $garment;
                    $usedCategories[$category] = true;
                    if (count($selectedGarments) >= 4) {
                        break;
                    }
                }

                $pdo->beginTransaction();
                try {
                    $lookStatement = $pdo->prepare(
                        'INSERT INTO look_suggestions
                            (user_id, occasion_id, occasion_name_snapshot,
                             temperature_c, humidity_pct, day_period)
                         VALUES
                            (:user_id, :occasion_id, :occasion_name,
                             :temperature, :humidity, :day_period)'
                    );
                    $lookStatement->execute([
                        'user_id' => $userId,
                        'occasion_id' => $occasionId,
                        'occasion_name' => $occasion['name'],
                        'temperature' => $temperature,
                        'humidity' => $humidity,
                        'day_period' => $period,
                    ]);
                    $lookId = (int) $pdo->lastInsertId();

                    $itemStatement = $pdo->prepare(
                        'INSERT INTO look_suggestion_items
                            (suggestion_id, garment_id, position, name_snapshot,
                             category_snapshot, color_snapshot, style_snapshot,
                             image_path_snapshot)
                         VALUES
                            (:suggestion_id, :garment_id, :position, :name,
                             :category, :color, :style, :image_path)'
                    );
                    foreach ($selectedGarments as $position => $garment) {
                        $itemStatement->execute([
                            'suggestion_id' => $lookId,
                            'garment_id' => $garment['id'],
                            'position' => $position + 1,
                            'name' => $garment['name'],
                            'category' => $garment['category'],
                            'color' => $garment['color'],
                            'style' => $garment['style'],
                            'image_path' => $garment['image_path'] ?? null,
                        ]);
                    }
                    $pdo->commit();
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }

                set_flash(
                    $selectedGarments === [] ? 'info' : 'success',
                    $selectedGarments === []
                        ? 'Não encontramos peças compatíveis. Cadastre mais itens no seu acervo.'
                        : 'Seu look foi sugerido para esta ocasião.'
                );
                redirect_to(($looksOnly ? 'escolher-look.php?look=' : 'meu-acervo.php?look=') . $lookId);
            }
        }
    } else {
        $errors[] = 'Não foi possível reconhecer esta ação.';
    }
}

$occasionStatement = $pdo->query(
    'SELECT id, name, min_temp_c, max_temp_c FROM occasions ORDER BY name'
);
$occasions = $occasionStatement->fetchAll();

$garmentsStatement = $pdo->prepare(
    'SELECT id, name, category, color, style, min_temp_c, max_temp_c, image_path
     FROM garments
     WHERE user_id = :user_id
     ORDER BY created_at DESC, id DESC'
);
$garmentsStatement->execute(['user_id' => $userId]);
$garments = $garmentsStatement->fetchAll();

$look = null;
$lookItems = [];
$lookId = filter_var(
    $_GET['look'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($lookId !== false && $lookId !== null) {
    $lookStatement = $pdo->prepare(
        'SELECT l.*, o.min_temp_c AS occasion_min_temp,
                o.max_temp_c AS occasion_max_temp
         FROM look_suggestions l
         LEFT JOIN occasions o ON o.id = l.occasion_id
         WHERE l.id = :id AND l.user_id = :user_id
         LIMIT 1'
    );
    $lookStatement->execute([
        'id' => $lookId,
        'user_id' => $userId,
    ]);
    $look = $lookStatement->fetch() ?: null;

    if (is_array($look)) {
        $lookItemsStatement = $pdo->prepare(
            'SELECT name_snapshot, category_snapshot, color_snapshot,
                    style_snapshot, image_path_snapshot
             FROM look_suggestion_items
             WHERE suggestion_id = :suggestion_id
             ORDER BY position'
        );
        $lookItemsStatement->execute(['suggestion_id' => $lookId]);
        $lookItems = $lookItemsStatement->fetchAll();
    }
}

$periodLabels = [
    'madrugada' => 'Madrugada',
    'manha' => 'Dia',
    'tarde' => 'Dia',
    'noite' => 'Noite',
];
$weatherTemperature = is_array($look) ? (float) $look['temperature_c'] : 24.0;
$weatherHumidity = is_array($look) ? (float) $look['humidity_pct'] : 72.0;
$weatherPeriod = is_array($look)
    ? ($periodLabels[$look['day_period']] ?? 'Dia')
    : 'Dia';
$humidityIsHigh = $weatherHumidity >= 65;
$categoryLabels = [
    'Calça' => 'Calças',
    'Moletom' => 'Moletons',
    'Camiseta' => 'Camisetas',
    'Camisa' => 'Camisas',
    'Blusa' => 'Blusas',
    'Jaqueta' => 'Jaquetas',
    'Saia' => 'Saias',
    'Short' => 'Shorts',
    'Vestido' => 'Vestidos',
    'Calçado' => 'Calçados',
    'Acessório' => 'Acessórios',
    'Parte de cima' => 'Partes de cima',
    'Parte de baixo' => 'Partes de baixo',
];
$categoryOrder = array_flip(array_keys($categoryLabels));
$garmentsByCategory = [];
foreach ($garments as $garment) {
    $category = (string) $garment['category'];
    $garmentsByCategory[$category][] = $garment;
}
uksort(
    $garmentsByCategory,
    static fn (string $first, string $second): int =>
        (($categoryOrder[$first] ?? 999) <=> ($categoryOrder[$second] ?? 999))
        ?: strcasecmp($first, $second)
);

require APP_ROOT . '/app/views/header.php';
?>
<section class="dashboard-heading">
    <div>
        <?php if ($looksOnly): ?>
            <p class="eyebrow">SEU PRÓXIMO LOOK</p>
            <h1>O que você vai vestir?</h1>
            <p>Escolha a ocasião e eu separo peças do seu acervo para combinar.</p>
        <?php else: ?>
            <p class="eyebrow">SEU ARMÁRIO DIGITAL</p>
            <h1>Meu acervo</h1>
            <p>Suas peças organizadas por categoria, prontas para virar look.</p>
        <?php endif; ?>
    </div>
    <?php if ($looksOnly): ?>
        <a class="button button-outline" href="<?= e(app_url('meu-acervo.php')) ?>">Voltar ao acervo</a>
    <?php else: ?>
        <div class="dashboard-actions">
            <a class="button button-outline" href="<?= e(app_url('escolher-look.php')) ?>">Montar look</a>
            <a class="button button-primary" href="<?= e(app_url('cadastro-peca.php')) ?>">+ Adicionar peça</a>
        </div>
    <?php endif; ?>
</section>

<?php if ($errors !== []): ?>
    <div class="form-errors page-errors" role="alert">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($looksOnly): ?>
<section class="closet-workspace">
    <div class="closet-suggest panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">SEU PRÓXIMO LOOK</p>
                <h2>O que você vai vestir hoje?</h2>
            </div>
            <span class="panel-sparkle" aria-hidden="true">✳</span>
        </div>
        <?php if ($occasions === []): ?>
            <p class="inline-notice">
                Ainda não há ocasiões cadastradas. Tente novamente mais tarde.
            </p>
        <?php else: ?>
            <form method="post" action="<?= e(app_url('escolher-look.php')) ?>" class="form-stack compact-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="suggest_look">
                <div class="form-field">
                    <label for="occasion_id">Escolha uma ocasião</label>
                    <select id="occasion_id" name="occasion_id" required>
                        <?php foreach ($occasions as $occasion): ?>
                            <option value="<?= (int) $occasion['id'] ?>">
                                <?= e($occasion['name']) ?>
                                · <?= e($occasion['min_temp_c']) ?>–<?= e($occasion['max_temp_c']) ?>°C
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-two">
                    <div class="form-field">
                        <label for="temperature_c">Temperatura</label>
                        <div class="input-suffix">
                            <input
                                id="temperature_c"
                                name="temperature_c"
                                type="number"
                                min="-10"
                                max="55"
                                step="0.5"
                                value="<?= e($weatherTemperature) ?>"
                                required
                            >
                            <span>°C</span>
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="humidity_pct">Umidade</label>
                        <div class="input-suffix">
                            <input
                                id="humidity_pct"
                                name="humidity_pct"
                                type="number"
                                min="0"
                                max="100"
                                step="1"
                                value="<?= e($weatherHumidity) ?>"
                                required
                            >
                            <span>%</span>
                        </div>
                    </div>
                </div>
                <div class="form-field">
                    <label for="day_period">Período</label>
                    <select id="day_period" name="day_period" required>
                        <option value="manha">Dia</option>
                        <option value="tarde">Fim de tarde</option>
                        <option value="noite">Noite</option>
                        <option value="madrugada">Madrugada</option>
                    </select>
                </div>
                <p class="sensor-hint">
                    <span class="sensor-dot"></span>
                    Clima demonstrativo; informe os valores atuais do ambiente.
                </p>
                <button class="button button-primary button-full" type="submit">
                    Sugerir meu look
                </button>
            </form>
        <?php endif; ?>
    </div>

    <aside class="weather-card panel">
        <div class="weather-card-top">
            <p class="eyebrow">CLIMA AGORA</p>
            <span class="weather-status"><i></i> Atualizado</span>
        </div>
        <div class="weather-values">
            <div><strong><?= e(number_format($weatherTemperature, 0, ',', '.')) ?>°C</strong><span>Temperatura</span></div>
            <div><strong><?= e(number_format($weatherHumidity, 0, ',', '.')) ?>%</strong><span>Umidade</span></div>
            <div><strong><?= e($weatherPeriod) ?></strong><span>Período</span></div>
        </div>
        <?php if ($humidityIsHigh): ?>
            <div class="humidity-alert">
                <span>⌁</span>
                <div>
                    <strong>Umidade alta no ambiente</strong>
                    <p>Areje o armário e confira se as peças estão secas antes de guardar.</p>
                </div>
            </div>
        <?php else: ?>
            <p class="weather-note">As condições ajudam a escolher peças confortáveis.</p>
        <?php endif; ?>
    </aside>
</section>
<?php endif; ?>

<?php if ($looksOnly && is_array($look)): ?>
    <?php
    $outsideOccasionRange = $look['occasion_min_temp'] !== null &&
        (
            (float) $look['temperature_c'] < (float) $look['occasion_min_temp'] ||
            (float) $look['temperature_c'] > (float) $look['occasion_max_temp']
        );
    ?>
    <section class="look-result panel">
        <div class="look-result-heading">
            <div>
                <p class="eyebrow">SUGESTÃO PARA VOCÊ</p>
                <h2>Um look para <?= e($look['occasion_name_snapshot']) ?></h2>
            </div>
            <span class="result-mark">✳</span>
        </div>
        <div class="look-weather-line">
            <span><?= e(number_format((float) $look['temperature_c'], 0, ',', '.')) ?>°C</span>
            <span><?= e(number_format((float) $look['humidity_pct'], 0, ',', '.')) ?>% umidade</span>
            <span><?= e($periodLabels[$look['day_period']] ?? 'Dia') ?></span>
            <?php if ($outsideOccasionRange): ?>
                <span class="range-note">
                    Temperatura fora da faixa indicada para esta ocasião.
                </span>
            <?php endif; ?>
        </div>

        <?php if ($lookItems !== []): ?>
            <div class="look-piece-list">
                <?php foreach ($lookItems as $index => $piece): ?>
                    <?php
                    $snapshotImage = (string) ($piece['image_path_snapshot'] ?? '');
                    $hasSnapshotImage = preg_match(
                        '~\Aassets/images/(?:garments|uploads)/[a-z0-9_-]+\.(?:png|jpe?g|webp)\z~i',
                        $snapshotImage
                    ) === 1;
                    ?>
                    <article class="look-piece">
                        <span class="piece-number"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                        <?php if ($hasSnapshotImage): ?>
                            <img
                                class="look-piece-photo"
                                src="<?= e(app_url($snapshotImage)) ?>"
                                alt="<?= e($piece['name_snapshot']) ?>"
                            >
                        <?php endif; ?>
                        <div>
                            <small><?= e($piece['category_snapshot']) ?></small>
                            <strong><?= e($piece['name_snapshot']) ?></strong>
                            <span><?= e($piece['color_snapshot']) ?> · <?= e($piece['style_snapshot']) ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-look">
                Ainda não há peças compatíveis com essa temperatura. Adicione peças
                com faixas térmicas diferentes para ampliar suas sugestões.
            </p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if (!$looksOnly): ?>
<section class="wardrobe-list">
    <div class="list-heading">
        <div>
            <p class="eyebrow">SEU ARMÁRIO DIGITAL</p>
            <h2>Minhas peças <span class="count-badge"><?= count($garments) ?></span></h2>
        </div>
        <a class="button button-outline button-small" href="<?= e(app_url('cadastro-peca.php')) ?>">
            + Cadastrar peça
        </a>
    </div>

    <?php if ($garments === []): ?>
        <div class="empty-closet">
            <span class="empty-hanger">⌁</span>
            <div>
                <h3>Seu acervo começa com uma peça.</h3>
                <p>Cadastre uma roupa para ver novas combinações.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($garmentsByCategory as $category => $categoryGarments): ?>
            <section class="category-group">
                <div class="category-heading">
                    <h3><?= e($categoryLabels[$category] ?? $category) ?></h3>
                    <span><?= count($categoryGarments) ?> <?= count($categoryGarments) === 1 ? 'peça' : 'peças' ?></span>
                </div>
                <div class="garment-grid">
                    <?php foreach ($categoryGarments as $garment): ?>
                        <?php
                        $shape = match ($garment['category']) {
                            'Parte de cima', 'Camiseta', 'Camisa', 'Blusa', 'Moletom', 'Jaqueta' => 'shape-top',
                            'Parte de baixo', 'Calça', 'Saia', 'Short' => 'shape-bottom',
                            'Calçado' => 'shape-shoe',
                            'Acessório' => 'shape-accessory',
                            default => 'shape-dress',
                        };
                        $palette = abs(crc32((string) $garment['color'])) % 5;
                        $imagePath = (string) ($garment['image_path'] ?? '');
                        $hasImage = preg_match(
                            '~\Aassets/images/(?:garments|uploads)/[a-z0-9_-]+\.(?:png|jpe?g|webp)\z~i',
                            $imagePath
                        ) === 1;
                        ?>
                        <article class="garment-card">
                            <div class="garment-art palette-<?= (int) $palette ?><?= $hasImage ? ' has-photo' : '' ?>">
                                <?php if ($hasImage): ?>
                                    <img
                                        class="garment-photo"
                                        src="<?= e(app_url($imagePath)) ?>"
                                        alt="<?= e($garment['name']) ?>"
                                        loading="lazy"
                                    >
                                <?php else: ?>
                                    <span class="garment-shape <?= e($shape) ?>" aria-hidden="true"></span>
                                <?php endif; ?>
                                <form method="post" action="<?= e(app_url('meu-acervo.php')) ?>" class="garment-delete">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_garment">
                                    <input type="hidden" name="garment_id" value="<?= (int) $garment['id'] ?>">
                                    <button type="submit" aria-label="Remover <?= e($garment['name']) ?>">×</button>
                                </form>
                            </div>
                            <div class="garment-info">
                                <small><?= e($garment['category']) ?></small>
                                <strong><?= e($garment['name']) ?></strong>
                                <span><?= e($garment['color']) ?> · <?= e($garment['style']) ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
