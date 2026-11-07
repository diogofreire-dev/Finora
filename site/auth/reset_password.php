<?php
// site/auth/reset_password.php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/password_rules.php';
require_once __DIR__ . '/../helpers/auth_layout.php';

$errors = [];
$token  = (string) ($_GET['token'] ?? '');
$status = 'invalid';
$user   = null;

// O token tem sempre 64 caracteres hexadecimais; procura-se pelo hash (é o que está guardado).
// A validade é comparada pelo MySQL, o mesmo relógio que a gravou.
if (strlen($token) === 64 && ctype_xdigit($token)) {
    $stmt = $pdo->prepare('SELECT id, password_reset_expires > NOW() AS still_valid
                           FROM users WHERE password_reset_token = :h LIMIT 1');
    $stmt->execute([':h' => hash('sha256', $token)]);
    $user = $stmt->fetch();
    if ($user) $status = $user['still_valid'] ? 'valid' : 'expired';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $status === 'valid') {
    $password  = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');

    if (!hash_equals(csrf_token(), (string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = t('A página esteve aberta demasiado tempo. Tenta outra vez.');
    } elseif ($msg = password_problem_message($password)) {
        $errors[] = $msg;
    } elseif ($password !== $password2) {
        $errors[] = t('As duas palavras-passe não são iguais.');
    } else {
        // Abrir o link do email prova que a pessoa controla o email: a conta fica verificada
        // e o bloqueio por tentativas falhadas é levantado. O token só serve uma vez.
        $pdo->prepare('UPDATE users SET password_hash = :hash, password_reset_token = NULL, password_reset_expires = NULL,
                              last_password_change = NOW(), failed_login_attempts = 0, login_locked_until = NULL,
                              email_verified = 1, verification_token = NULL, verification_expires = NULL
                       WHERE id = :id AND password_reset_token = :h')
            ->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $user['id'], ':h' => hash('sha256', $token)]);
        $status = 'success';
    }
}

auth_page_start(t('Nova palavra-passe'));
?>
      <div class="au-card">
<?php if ($status === 'valid'): ?>
        <div class="au-head au-head-center">
          <div class="au-status"><i class="bi bi-shield-lock" aria-hidden="true"></i></div>
          <h2><?= te('Escolhe uma palavra-passe nova') ?></h2>
          <p><?= te('Depois disto, a antiga deixa de funcionar.') ?></p>
        </div>

        <?= auth_alert('err', $errors) ?>

        <form method="post" data-once>
          <?= csrf_field() ?>
          <div class="au-field">
            <label class="au-label" for="password"><?= te('Nova palavra-passe') ?></label>
            <?= auth_password_input('password', 'password', 'new-password', 'minlength="8" maxlength="72" aria-describedby="passwordReqs" autofocus') ?>
            <?= auth_password_rules('password') ?>
          </div>
          <div class="au-field">
            <label class="au-label" for="password2"><?= te('Repetir palavra-passe') ?></label>
            <?= auth_password_input('password2', 'password2', 'new-password', 'maxlength="72"') ?>
          </div>
          <button type="submit" class="au-btn"><?= te('Guardar palavra-passe') ?></button>
        </form>
<?php elseif ($status === 'success'): ?>
        <div class="au-head au-head-center">
          <div class="au-status ok"><i class="bi bi-check-lg" aria-hidden="true"></i></div>
          <h2><?= te('Palavra-passe guardada') ?></h2>
          <p><?= te('Já podes entrar com a palavra-passe nova.') ?></p>
        </div>
        <a href="login.php" class="au-btn"><?= te('Entrar') ?></a>
<?php else: ?>
        <div class="au-head au-head-center">
          <div class="au-status bad"><i class="bi bi-<?= $status === 'expired' ? 'hourglass-bottom' : 'x-lg' ?>" aria-hidden="true"></i></div>
          <h2><?= $status === 'expired' ? t('Este link expirou') : t('Este link não funciona') ?></h2>
          <p><?= $status === 'expired'
                ? t('Os links de recuperação valem durante uma hora. Pede um novo.')
                : t('Pode já ter sido usado, ou foi copiado incompleto. Pede um novo.') ?></p>
        </div>
        <div class="au-stack">
          <a href="forgot_password.php" class="au-btn"><?= te('Pedir link novo') ?></a>
          <a href="login.php" class="au-btn-quiet"><?= te('Voltar ao login') ?></a>
        </div>
<?php endif; ?>
      </div>
<?php auth_page_end(); ?>
