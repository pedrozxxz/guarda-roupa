<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/includes/bootstrap.php';

if (is_logged_in()) {
    redirect_to('meu-acervo.php');
}

$pageTitle = 'Criar conta';
$activeNav = 'register';
$photoFile = 'editorial-chair.png';
$photoAlt = 'Mulher sentada em uma poltrona em frente ao seu guarda-roupa';
$errors = [];
$values = [
    'full_name' => '',
    'email' => '',
    'preferred_style' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['full_name'] = trim(
        is_string($_POST['full_name'] ?? null) ? $_POST['full_name'] : ''
    );
    $values['email'] = strtolower(trim(
        is_string($_POST['email'] ?? null) ? $_POST['email'] : ''
    ));
    $values['preferred_style'] = trim(
        is_string($_POST['preferred_style'] ?? null)
            ? $_POST['preferred_style']
            : ''
    );
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!csrf_is_valid($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    }
    if (text_length($values['full_name']) < 2 || text_length($values['full_name']) > 120) {
        $errors[] = 'Informe seu nome com até 120 caracteres.';
    }
    if (
        filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false ||
        strlen($values['email']) > 191
    ) {
        $errors[] = 'Informe um endereço de e-mail válido.';
    }
    if (
        text_length($values['preferred_style']) < 2 ||
        text_length($values['preferred_style']) > 60
    ) {
        $errors[] = 'Informe seu estilo preferido com até 60 caracteres.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'A senha precisa ter pelo menos 8 caracteres.';
    }

    if ($errors === []) {
        try {
            $statement = db()->prepare(
                'INSERT INTO users
                    (full_name, email, password_hash, preferred_style, role)
                 VALUES
                    (:full_name, :email, :password_hash, :preferred_style, :role)'
            );
            $statement->execute([
                'full_name' => $values['full_name'],
                'email' => $values['email'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'preferred_style' => $values['preferred_style'],
                'role' => 'user',
            ]);

            set_flash('success', 'Cadastro concluído. Entre com sua conta.');
            redirect_to('login.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $errors[] = 'Este e-mail já está cadastrado.';
            } else {
                error_log('[Vestí] Falha no cadastro: ' . $exception->getMessage());
                $errors[] = 'Não foi possível concluir o cadastro. Tente novamente.';
            }
        }
    }
}

require APP_ROOT . '/app/views/header.php';
?>
<section class="auth-layout">
    <div class="auth-card">
        <p class="eyebrow">NOVO PERFIL</p>
        <h1>Crie sua conta.</h1>
        <p class="form-intro">
            Cadastre-se para organizar seu acervo e receber sugestões.
        </p>

        <?php if ($errors !== []): ?>
            <div class="form-errors" role="alert">
                <p>Confira estes campos:</p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(app_url('cadastro.php')) ?>" class="form-stack">
            <?= csrf_field() ?>
            <div class="form-field">
                <label for="full_name">Nome completo</label>
                <input
                    id="full_name"
                    name="full_name"
                    type="text"
                    maxlength="120"
                    autocomplete="name"
                    value="<?= e($values['full_name']) ?>"
                    required
                >
            </div>
            <div class="form-field">
                <label for="email">E-mail</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    maxlength="191"
                    autocomplete="email"
                    value="<?= e($values['email']) ?>"
                    required
                >
            </div>
            <div class="form-field">
                <label for="password">Senha</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
                <small>Mínimo de 8 caracteres.</small>
            </div>
            <div class="form-field">
                <label for="preferred_style">Estilo preferido</label>
                <select id="preferred_style" name="preferred_style" required>
                    <option value="">Selecione</option>
                    <?php foreach (['Versátil', 'Casual', 'Elegante', 'Criativo', 'Esportivo', 'Básico'] as $style): ?>
                        <option
                            value="<?= e($style) ?>"
                            <?= $values['preferred_style'] === $style ? 'selected' : '' ?>
                        >
                            <?= e($style) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="auth-form-actions">
                <button class="button button-primary button-full" type="submit">
                    Criar conta
                </button>
                <p class="auth-switch">
                    Já tem uma conta?
                    <a href="<?= e(app_url('login.php')) ?>">Entrar</a>
                </p>
            </div>
        </form>
    </div>

    <?php require APP_ROOT . '/app/views/photo-panel.php'; ?>
</section>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
