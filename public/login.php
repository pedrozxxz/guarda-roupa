<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/includes/bootstrap.php';

$isAdminLogin = isset($adminLogin) && $adminLogin === true;
$pageTitle = $isAdminLogin ? 'Acesso administrativo' : 'Entrar';
$activeNav = 'login';
$photoFile = $isAdminLogin ? 'editorial-pair.png' : 'editorial-floor.png';
$photoAlt = $isAdminLogin
    ? 'Duas cenas editoriais de moda em um closet'
    : 'Mulher em um closet com roupas e acessórios ao redor';
$errors = [];
$email = '';

if (is_logged_in()) {
    $loggedUser = current_user();
    if (!$isAdminLogin || ($loggedUser['role'] ?? '') === 'admin') {
        redirect_to(($loggedUser['role'] ?? '') === 'admin'
            ? 'admin/ocasioes.php'
            : 'meu-acervo.php');
    }
    $errors[] = 'Saia da conta atual ou informe as credenciais de uma administradora.';
}

if (isset($_GET['password_reset'])) {
    set_flash('success', 'Senha atualizada. Entre com sua nova senha.');
}
if (isset($_GET['logged_out'])) {
    set_flash('success', 'Você saiu da sua conta.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim(
        is_string($_POST['email'] ?? null) ? $_POST['email'] : ''
    ));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!csrf_is_valid($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } elseif (
        filter_var($email, FILTER_VALIDATE_EMAIL) === false ||
        strlen($email) > 191 ||
        $password === ''
    ) {
        $errors[] = 'E-mail ou senha inválidos.';
    } else {
        try {
            $statement = db()->prepare(
                'SELECT id, full_name, email, password_hash, preferred_style, role
                 FROM users
                 WHERE email = :email
                 LIMIT 1'
            );
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();

            $credentialsValid = is_array($user) &&
                password_verify($password, $user['password_hash']);
            $roleValid = !$isAdminLogin ||
                (is_array($user) && $user['role'] === 'admin');

            if (!$credentialsValid || !$roleValid) {
                $errors[] = $isAdminLogin
                    ? 'E-mail ou senha inválidos para a administração.'
                    : 'E-mail ou senha inválidos.';
            } else {
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    $rehashStatement = db()->prepare(
                        'UPDATE users SET password_hash = :password_hash WHERE id = :id'
                    );
                    $rehashStatement->execute([
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'id' => $user['id'],
                    ]);
                }

                session_regenerate_id(true);
                unset($_SESSION['_csrf_token']);
                $_SESSION['user'] = [
                    'id' => (int) $user['id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'preferred_style' => $user['preferred_style'],
                    'role' => $user['role'],
                ];

                set_flash('success', 'Bem-vinda de volta ao Vestí.');
                redirect_to($user['role'] === 'admin'
                    ? 'admin/ocasioes.php'
                    : 'meu-acervo.php');
            }
        } catch (PDOException $exception) {
            error_log('[Vestí] Falha no login: ' . $exception->getMessage());
            $errors[] = 'Não foi possível entrar agora. Tente novamente em instantes.';
        }
    }
}

require APP_ROOT . '/app/views/header.php';
?>
<section class="auth-layout">
    <div class="auth-card">
        <?php if ($isAdminLogin): ?>
            <p class="eyebrow">ÁREA RESTRITA</p>
            <h1>Acesso administrativo.</h1>
            <p class="form-intro">Entre com as credenciais da administração.</p>
        <?php else: ?>
            <p class="eyebrow">SEU VESTÍ</p>
            <h1>Bem-vindo de volta.</h1>
            <p class="form-intro">Entre para continuar de onde parou.</p>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="form-errors" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form
            method="post"
            action="<?= e(app_url($isAdminLogin ? 'admin/login.php' : 'login.php')) ?>"
            class="form-stack"
        >
            <?= csrf_field() ?>
            <div class="form-field">
                <label for="email">E-mail</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    maxlength="191"
                    autocomplete="email"
                    value="<?= e($email) ?>"
                    required
                >
            </div>
            <div class="form-field">
                <div class="label-row">
                    <label for="password">Senha</label>
                    <?php if (!$isAdminLogin): ?>
                        <a class="subtle-link" href="<?= e(app_url('redefinir-senha.php')) ?>">
                            Esqueci minha senha
                        </a>
                    <?php endif; ?>
                </div>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                >
            </div>
            <div class="auth-form-actions">
                <button class="button button-primary button-full" type="submit">
                    <?= $isAdminLogin ? 'Entrar como administrador' : 'Entrar' ?>
                </button>
                <?php if (!$isAdminLogin): ?>
                    <p class="auth-switch">
                        Ainda não tem uma conta?
                        <a href="<?= e(app_url('cadastro.php')) ?>">Criar conta</a>
                    </p>
                <?php else: ?>
                    <p class="auth-switch">
                        <a href="<?= e(app_url('login.php')) ?>">Voltar ao login</a>
                    </p>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php require APP_ROOT . '/app/views/photo-panel.php'; ?>
</section>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
