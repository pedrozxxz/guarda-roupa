<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/includes/bootstrap.php';
require_login();

$user = current_user();
$pageTitle = 'Cadastrar peça';
$activeNav = 'wardrobe';
$photoFile = 'editorial-pair.png';
$photoAlt = 'Duas cenas editoriais de moda em um closet';
$errors = [];
$uploadedImagePath = null;
$categories = [
    'Calça',
    'Moletom',
    'Camiseta',
    'Camisa',
    'Blusa',
    'Jaqueta',
    'Saia',
    'Short',
    'Vestido',
    'Calçado',
    'Acessório',
    'Outro',
];
$styles = ['Versátil', 'Casual', 'Elegante', 'Criativo', 'Esportivo', 'Streetwear', 'Básico'];
$values = [
    'name' => '',
    'category' => '',
    'color' => '',
    'min_temp_c' => '18',
    'max_temp_c' => '30',
    'style' => 'Versátil',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $field) {
        if (is_string($_POST[$field] ?? null)) {
            $values[$field] = trim($_POST[$field]);
        }
    }

    if (!csrf_is_valid($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    }
    if (text_length($values['name']) < 2 || text_length($values['name']) > 100) {
        $errors[] = 'Informe o nome da peça com até 100 caracteres.';
    }
    if (!in_array($values['category'], $categories, true)) {
        $errors[] = 'Selecione uma categoria válida.';
    }
    if (text_length($values['color']) < 2 || text_length($values['color']) > 40) {
        $errors[] = 'Informe uma cor com até 40 caracteres.';
    }
    if (!in_array($values['style'], $styles, true)) {
        $errors[] = 'Selecione um estilo válido.';
    }

    $uploadTemporaryPath = null;
    $uploadExtension = null;
    $photoUpload = $_FILES['piece_photo'] ?? null;
    if (is_array($photoUpload)) {
        $uploadError = (int) ($photoUpload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_NO_FILE) {
            if ($uploadError !== UPLOAD_ERR_OK) {
                $errors[] = match ($uploadError) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A foto ultrapassa o limite de 8 MB.',
                    UPLOAD_ERR_PARTIAL => 'A foto foi enviada pela metade. Tente novamente.',
                    UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu receber a foto. Tente novamente.',
                    default => 'Não foi possível receber a foto. Escolha outro arquivo.',
                };
            } else {
                $uploadTemporaryPath = is_string($photoUpload['tmp_name'] ?? null)
                    ? $photoUpload['tmp_name']
                    : '';
                $uploadSize = filter_var(
                    $photoUpload['size'] ?? null,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if (
                    $uploadSize === false ||
                    $uploadSize > 8 * 1024 * 1024 ||
                    $uploadTemporaryPath === '' ||
                    !is_uploaded_file($uploadTemporaryPath)
                ) {
                    $errors[] = 'Escolha uma foto válida de até 8 MB.';
                } else {
                    $imageInfo = @getimagesize($uploadTemporaryPath);
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($uploadTemporaryPath);
                    $uploadExtension = match ($mimeType) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        default => null,
                    };

                    if (
                        !is_array($imageInfo) ||
                        $uploadExtension === null ||
                        ($imageInfo['mime'] ?? null) !== $mimeType
                    ) {
                        $errors[] = 'Use uma imagem JPG, PNG ou WebP.';
                    } elseif (
                        $imageInfo[0] > 6000 ||
                        $imageInfo[1] > 6000 ||
                        $imageInfo[0] * $imageInfo[1] > 25000000
                    ) {
                        $errors[] = 'A foto pode ter até 6.000 px por lado e 25 megapixels.';
                    }
                }
            }
        }
    }

    $minTemp = filter_var($values['min_temp_c'], FILTER_VALIDATE_FLOAT);
    $maxTemp = filter_var($values['max_temp_c'], FILTER_VALIDATE_FLOAT);
    if ($minTemp === false || $minTemp < -10 || $minTemp > 55) {
        $errors[] = 'Informe uma temperatura mínima entre -10°C e 55°C.';
    }
    if ($maxTemp === false || $maxTemp < -10 || $maxTemp > 55) {
        $errors[] = 'Informe uma temperatura máxima entre -10°C e 55°C.';
    }
    if ($minTemp !== false && $maxTemp !== false && $minTemp > $maxTemp) {
        $errors[] = 'A temperatura mínima precisa ser menor ou igual à máxima.';
    }

    if ($errors === [] && $uploadTemporaryPath !== null && $uploadExtension !== null) {
        $uploadDirectory = APP_ROOT . '/public/assets/images/uploads';
        try {
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                throw new RuntimeException('Não foi possível criar a pasta de imagens.');
            }

            $filename = 'piece-' . (int) $user['id'] . '-' . bin2hex(random_bytes(16)) . '.' . $uploadExtension;
            if (!move_uploaded_file($uploadTemporaryPath, $uploadDirectory . DIRECTORY_SEPARATOR . $filename)) {
                throw new RuntimeException('Não foi possível mover a foto enviada.');
            }
            $uploadedImagePath = 'assets/images/uploads/' . $filename;
        } catch (Throwable $exception) {
            error_log('[Vestí] Falha ao preparar foto da peça: ' . $exception->getMessage());
            $errors[] = 'Não foi possível guardar a foto agora. Tente novamente.';
        }
    }

    if ($errors === []) {
        try {
            $statement = db()->prepare(
                'INSERT INTO garments
                    (user_id, name, category, color, min_temp_c, max_temp_c, style, image_path)
                 VALUES
                    (:user_id, :name, :category, :color, :min_temp_c, :max_temp_c, :style, :image_path)'
            );
            $statement->execute([
                'user_id' => (int) $user['id'],
                'name' => $values['name'],
                'category' => $values['category'],
                'color' => $values['color'],
                'min_temp_c' => $minTemp,
                'max_temp_c' => $maxTemp,
                'style' => $values['style'],
                'image_path' => $uploadedImagePath,
            ]);
            set_flash('success', 'Peça adicionada ao seu acervo.');
            redirect_to('meu-acervo.php');
        } catch (PDOException $exception) {
            if ($uploadedImagePath !== null) {
                @unlink(APP_ROOT . '/public/' . $uploadedImagePath);
            }
            error_log('[Vestí] Falha ao cadastrar peça: ' . $exception->getMessage());
            $errors[] = 'Não foi possível salvar a peça agora. Tente novamente.';
        }
    }
}

require APP_ROOT . '/app/views/header.php';
?>
<section class="auth-layout auth-layout-garment">
    <div class="auth-card">
        <p class="eyebrow">SEU ARMÁRIO DIGITAL</p>
        <h1>Cadastrar peça.</h1>
        <p class="form-intro">Adicione uma peça nova do seu guarda-roupa e envie uma foto, se quiser.</p>

        <?php if ($errors !== []): ?>
            <div class="form-errors" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" action="<?= e(app_url('cadastro-peca.php')) ?>" class="form-stack">
            <?= csrf_field() ?>
            <div class="form-field">
                <label for="name">Nome da peça</label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    maxlength="100"
                    placeholder="Ex.: Camisa de linho"
                    value="<?= e($values['name']) ?>"
                    required
                >
            </div>
            <div class="form-field">
                <label for="category">Categoria</label>
                <select id="category" name="category" required>
                    <option value="">Selecione</option>
                    <?php foreach ($categories as $category): ?>
                        <option
                            value="<?= e($category) ?>"
                            <?= $values['category'] === $category ? 'selected' : '' ?>
                        >
                            <?= e($category) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="color">Cor</label>
                <input
                    id="color"
                    name="color"
                    type="text"
                    maxlength="40"
                    placeholder="Ex.: Areia"
                    value="<?= e($values['color']) ?>"
                    required
                >
            </div>
            <div class="form-field">
                <label for="piece_photo">Foto da sua peça</label>
                <input
                    class="piece-file-input"
                    id="piece_photo"
                    name="piece_photo"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    data-piece-upload
                >
                <small>JPG, PNG ou WebP · até 8 MB. Escolha a foto da peça que está adicionando.</small>
                <div class="piece-image-preview is-empty">
                    <img src="" alt="Prévia da foto da nova peça" data-piece-image-preview hidden>
                    <span data-piece-image-placeholder>Selecione uma foto para ver a prévia.</span>
                </div>
            </div>
            <div class="form-two">
                <div class="form-field">
                    <label for="min_temp_c">Conforto a partir de</label>
                    <div class="input-suffix">
                        <input id="min_temp_c" name="min_temp_c" type="number" min="-10" max="55" step="0.5" value="<?= e($values['min_temp_c']) ?>" required>
                        <span>°C</span>
                    </div>
                </div>
                <div class="form-field">
                    <label for="max_temp_c">Conforto até</label>
                    <div class="input-suffix">
                        <input id="max_temp_c" name="max_temp_c" type="number" min="-10" max="55" step="0.5" value="<?= e($values['max_temp_c']) ?>" required>
                        <span>°C</span>
                    </div>
                </div>
            </div>
            <div class="form-field">
                <label for="style">Estilo</label>
                <select id="style" name="style" required>
                    <?php foreach ($styles as $style): ?>
                        <option value="<?= e($style) ?>" <?= $values['style'] === $style ? 'selected' : '' ?>>
                            <?= e($style) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="button button-primary button-full" type="submit">
                Adicionar peça nova
            </button>
            <a class="button button-outline button-full" href="<?= e(app_url('meu-acervo.php')) ?>">Cancelar</a>
        </form>
    </div>

    <?php require APP_ROOT . '/app/views/photo-panel.php'; ?>
</section>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
