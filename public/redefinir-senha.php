<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/includes/bootstrap.php';

$pageTitle = 'Redefinir senha';
$activeNav = '';
$photoFile = 'editorial-floor.png';
$photoAlt = 'Mulher em um closet com roupas e acessórios ao redor';
$errors = [];
$requestEmail = '';
$requestMessage = '';
$demoResetUrl = null;
$token = '';
$validResetToken = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    if (!csrf_is_valid($_POST['_csrf'] ?? null)) {
        $errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } elseif ($action === 'request_reset') {
        $requestEmail = strtolower(trim(
            is_string($_POST['email'] ?? null) ? $_POST['email'] : ''
        ));

        if (
            filter_var($requestEmail, FILTER_VALIDATE_EMAIL) === false ||
            strlen($requestEmail) > 191
        ) {
            $errors[] = 'Informe um endereço de e-mail válido.';
        } else {
            try {
                $lookup = db()->prepare(
                    'SELECT id FROM users WHERE email = :email LIMIT 1'
                );
                $lookup->execute(['email' => $requestEmail]);
                $userId = $lookup->fetchColumn();
                $requestMessage =
                    'Se houver uma conta com esse e-mail, as instruções foram enviadas.';

                if ($userId !== false) {
                    $token = bin2hex(random_bytes(32));
                    $pdo = db();
                    $delete = $pdo->prepare(
                        'DELETE FROM password_resets WHERE user_id = :user_id'
                    );
                    $delete->execute(['user_id' => $userId]);

                    $insert = $pdo->prepare(
                        'INSERT INTO password_resets (user_id, token_hash, expires_at)
                         VALUES (
                            :user_id,
                            :token_hash,
                            DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 HOUR)
                         )'
                    );
                    $insert->execute([
                        'user_id' => $userId,
                        'token_hash' => hash('sha256', $token),
                    ]);

                    if (APP_SHOW_RESET_LINK) {
                        $demoResetUrl = app_url('redefinir-senha.php') .
                            '?token=' . rawurlencode($token);
                    }
                }
            } catch (Throwable $exception) {
                error_log('[Vestí] Falha ao solicitar redefinição: ' . $exception->getMessage());
                $errors[] = 'Não foi possível enviar as instruções agora. Tente novamente.';
                $requestMessage = '';
            }
        }
    } elseif ($action === 'change_password') {
        $token = is_string($_POST['token'] ?? null) ? trim($_POST['token']) : '';
        $newPassword = is_string($_POST['new_password'] ?? null)
            ? $_POST['new_password']
            : '';
        $passwordConfirmation = is_string($_POST['password_confirmation'] ?? null)
            ? $_POST['password_confirmation']
            : '';

        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            $errors[] = 'Este link é inválido ou expirou.';
        } else {
            try {
                $lookup = db()->prepare(
                    'SELECT user_id
                     FROM password_resets
                     WHERE token_hash = :token_hash
                       AND used_at IS NULL
                       AND expires_at > CURRENT_TIMESTAMP
                     LIMIT 1'
                );
                $lookup->execute(['token_hash' => hash('sha256', $token)]);
                $userId = $lookup->fetchColumn();

                if ($userId === false) {
                    $errors[] = 'Este link é inválido ou expirou.';
                } else {
                    $validResetToken = true;

                    if (strlen($newPassword) < 8) {
                        $errors[] = 'A nova senha precisa ter pelo menos 8 caracteres.';
                    }
                    if ($newPassword !== $passwordConfirmation) {
                        $errors[] = 'As senhas não coincidem.';
                    }

                    if ($errors === []) {
                        $pdo = db();
                        $pdo->beginTransaction();
                        try {
                            $updateUser = $pdo->prepare(
                                'UPDATE users SET password_hash = :password_hash WHERE id = :id'
                            );
                            $updateUser->execute([
                                'password_hash' => password_hash(
                                    $newPassword,
                                    PASSWORD_DEFAULT
                                ),
                                'id' => $userId,
                            ]);
                            $consume = $pdo->prepare(
                                'UPDATE password_resets
                                 SET used_at = CURRENT_TIMESTAMP
                                 WHERE user_id = :user_id AND used_at IS NULL'
                            );
                            $consume->execute(['user_id' => $userId]);
                            $pdo->commit();
                        } catch (Throwable $exception) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            throw $exception;
                        }

                        set_flash('success', 'Senha atualizada. Entre com sua nova senha.');
                        redirect_to('login.php?password_reset=1');
                    }
                }
            } catch (Throwable $exception) {
                error_log('[Vestí] Falha ao redefinir senha: ' . $exception->getMessage());
                $errors[] = 'Não foi possível atualizar sua senha agora. Tente novamente.';
            }
        }
    } else {
        $errors[] = 'Não foi possível reconhecer a solicitação.';
    }
} elseif (isset($_GET['token']) && is_string($_GET['token'])) {
    $token = trim($_GET['token']);

    if (strlen($token) === 64 && ctype_xdigit($token)) {
        try {
            $lookup = db()->prepare(
                'SELECT user_id
                 FROM password_resets
                 WHERE token_hash = :token_hash
                   AND used_at IS NULL
                   AND expires_at > CURRENT_TIMESTAMP
                 LIMIT 1'
            );
            $lookup->execute(['token_hash' => hash('sha256', $token)]);
            $validResetToken = $lookup->fetchColumn() !== false;
            if (!$validResetToken) {
                $errors[] = 'Este link é inválido ou expirou.';
            }
        } catch (Throwable $exception) {
            error_log('[Vestí] Falha ao validar redefinição: ' . $exception->getMessage());
            $errors[] = 'Não foi possível validar o link agora. Tente novamente.';
        }
    } else {
        $errors[] = 'Este link é inválido ou expirou.';
    }
}

if ($requestMessage !== '') {
    $photoFile = 'editorial-chair.png';
}
if ($validResetToken) {
    $photoFile = 'editorial-phone.png';
    $photoAlt = 'Mulher em um closet com telefone e roupas ao redor';
}

require APP_ROOT . '/app/views/header.php';
?>
<section class="auth-layout">
    <div class="auth-card">
        <?php if ($validResetToken): ?>
            <p class="eyebrow">NOVO ACESSO</p>
            <h1>Defina sua senha.</h1>
            <p class="form-intro">Escolha uma senha com pelo menos 8 caracteres.</p>
        <?php elseif ($requestMessage !== ''): ?>
            <p class="eyebrow">INSTRUÇÕES DE ACESSO</p>
            <h1>Confirme seu e-mail.</h1>
            <p class="form-intro"><?= e($requestMessage) ?></p>
            <?php if ($demoResetUrl !== null): ?>
                <div class="dev-reset">
                    <strong>Link local de demonstração</strong>
                    <a href="<?= e($demoResetUrl) ?>">Abrir redefinição de senha</a>
                    <small>O ambiente local não envia e-mail real.</small>
                </div>
            <?php endif; ?>
            <a class="button button-primary button-full back-to-login" href="<?= e(app_url('login.php')) ?>">
                Voltar ao login
            </a>
        <?php else: ?>
            <p class="eyebrow">RECUPERE SEU ACESSO</p>
            <h1>Redefinir senha.</h1>
            <p class="form-intro">
                Informe o e-mail cadastrado para receber as instruções.
            </p>
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

        <?php if ($validResetToken): ?>
            <form method="post" action="<?= e(app_url('redefinir-senha.php')) ?>" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="form-field">
                    <label for="new_password">Nova senha</label>
                    <input
                        id="new_password"
                        name="new_password"
                        type="password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                </div>
                <div class="form-field">
                    <label for="password_confirmation">Confirme a nova senha</label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                </div>
                <button class="button button-primary button-full" type="submit">
                    Salvar nova senha
                </button>
            </form>
        <?php elseif ($requestMessage === ''): ?>
            <form method="post" action="<?= e(app_url('redefinir-senha.php')) ?>" class="form-stack">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="request_reset">
                <div class="form-field">
                    <label for="email">E-mail</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        maxlength="191"
                        autocomplete="email"
                        value="<?= e($requestEmail) ?>"
                        required
                    >
                </div>
                <button class="button button-primary button-full" type="submit">
                    Enviar link
                </button>
                <p class="auth-switch">
                    <a href="<?= e(app_url('login.php')) ?>">Voltar ao login</a>
                </p>
            </form>
        <?php endif; ?>
    </div>

    <?php require APP_ROOT . '/app/views/photo-panel.php'; ?>
</section>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
