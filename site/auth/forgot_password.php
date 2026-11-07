<?php
// site/auth/forgot_password.php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/EmailService.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/throttle.php';
require_once __DIR__ . '/../helpers/auth_layout.php';

// Limites: por ligação (alguém a experimentar muitos emails) e por email (encher a caixa de alguém)
const FORGOT_IP_MAX    = 5;
const FORGOT_EMAIL_MAX = 3;
const FORGOT_MINUTES   = 60;

$errors = [];
$sent   = false;
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    if (!hash_equals(csrf_token(), (string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = t('A página esteve aberta demasiado tempo. Tenta outra vez.');
    } elseif (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('Esse email não parece válido.');
    } elseif (!throttle_allow($pdo, 'forgot', client_ip(), FORGOT_IP_MAX, FORGOT_MINUTES)) {
        $errors[] = t('Demasiados pedidos a partir desta ligação. Tenta daqui a uma hora.');
    } else {
        $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch();

        // Se o limite por email for atingido não se envia nada, mas a resposta é a mesma
        if ($user && throttle_allow($pdo, 'forgot_email', $email, FORGOT_EMAIL_MAX, FORGOT_MINUTES)) {
            // O link leva o token; a base de dados guarda só o hash dele.
            // Quem tiver uma cópia da base de dados não consegue usar os links.
            $token = bin2hex(random_bytes(32));
            $pdo->prepare('UPDATE users SET password_reset_token = :h, password_reset_expires = NOW() + INTERVAL 1 HOUR WHERE id = :id')
                ->execute([':h' => hash('sha256', $token), ':id' => $user['id']]);
            if (!(new EmailService())->sendPasswordResetEmail($user['email'], $user['username'], $token)) {
                error_log('[forgot_password] Falha ao enviar email para o utilizador ' . $user['id']);
            }
        }
        // Resposta igual quer o email exista quer não (evita enumeração de contas)
        $sent = true;
    }
}

auth_page_start(t('Recuperar palavra-passe'));
?>
      <div class="au-card">
<?php if ($sent): ?>
        <div class="au-head au-head-center">
          <div class="au-status"><i class="bi bi-envelope-paper" aria-hidden="true"></i></div>
          <h2><?= te('Vê o teu email') ?></h2>
          <p><?= t('Se existir uma conta com {email}, enviámos um link para escolheres uma palavra-passe nova. O link vale durante uma hora.', ['email' => '<b>' . htmlspecialchars($email) . '</b>']) ?></p>
        </div>
        <div class="au-stack">
          <a href="login.php" class="au-btn"><?= te('Voltar ao login') ?></a>
        </div>
        <p class="au-foot au-muted"><?= te('Não chegou? Vê a pasta de spam ou') ?> <a href="forgot_password.php"><?= te('pede outro link') ?></a>.</p>
<?php else: ?>
        <div class="au-head au-head-center">
          <div class="au-status"><i class="bi bi-key" aria-hidden="true"></i></div>
          <h2><?= te('Esqueceste-te da palavra-passe?') ?></h2>
          <p><?= te('Escreve o email da tua conta e enviamos um link para criares uma nova.') ?></p>
        </div>

        <?= auth_alert('err', $errors) ?>

        <form method="post" data-once>
          <?= csrf_field() ?>
          <div class="au-field">
            <label class="au-label" for="email"><?= te('Email') ?></label>
            <div class="au-input-wrap">
              <i class="bi bi-envelope au-input-ico" aria-hidden="true"></i>
              <input type="email" id="email" name="email" class="au-input" maxlength="255"
                     value="<?= htmlspecialchars($email) ?>" autocomplete="email" required autofocus>
            </div>
          </div>
          <button type="submit" class="au-btn"><?= te('Enviar link') ?></button>
        </form>
        <p class="au-foot"><?= te('Lembraste-te?') ?> <a href="login.php"><?= te('Entrar') ?></a></p>
<?php endif; ?>
      </div>
<?php auth_page_end(); ?>
