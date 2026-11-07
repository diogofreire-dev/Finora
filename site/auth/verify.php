<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/EmailService.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/auth_layout.php';

if (isset($_SESSION['user_id'])) { header('Location: ../dashboard.php'); exit; }

// Proteção contra força bruta: o código só vale para a conta desta sessão,
// tem poucas tentativas e expira depressa.
const VERIFY_MAX_ATTEMPTS   = 5;
const VERIFY_CODE_MINUTES   = 15;
const VERIFY_RESEND_SECONDS = 60;

$status   = 'pending';
$message  = '';
$username = '';
$email    = $_SESSION['pending_verification_email'] ?? '';
$errors   = [];

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isPost && !hash_equals(csrf_token(), (string) ($_POST['csrf_token'] ?? ''))) {
    $errors[] = t('A página esteve aberta demasiado tempo. Tenta outra vez.');
    $isPost = false;
}

// Processar código de verificação
if ($isPost && !isset($_POST['resend'])) {
    // Aceita "123 456" ou "123456" (quem cola do email traz às vezes espaços)
    $digits = preg_replace('/\s+/', '', (string) ($_POST['code'] ?? ''));

    if ($email === '') {
        $errors[] = t('Sessão expirada. Faz login para receberes um novo código.');
    } elseif (strlen($digits) !== 6 || !ctype_digit($digits)) {
        $errors[] = t('Por favor, introduz um código válido de 6 dígitos.');
    } else {
        // Procurar a conta pelo email da sessão — nunca só pelo código
        $stmt = $pdo->prepare('SELECT id, username, email, email_verified, verification_token, verification_attempts,
                                      verification_expires < NOW() AS is_expired
                               FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // Gastar uma tentativa ANTES de comparar. Um único UPDATE atómico evita que
        // pedidos em paralelo leiam o mesmo contador e ultrapassem o limite.
        $reserved = false;
        if ($user && !$user['email_verified']) {
            $upd = $pdo->prepare('UPDATE users SET verification_attempts = verification_attempts + 1
                                  WHERE id = :id AND email_verified = 0 AND verification_token IS NOT NULL
                                    AND verification_expires > NOW() AND verification_attempts < :max');
            $upd->execute([':id' => $user['id'], ':max' => VERIFY_MAX_ATTEMPTS]);
            $reserved = $upd->rowCount() === 1;
        }

        if (!$user) {
            $errors[] = t('Código de verificação inválido.');
        } elseif ($user['email_verified'] == 1) {
            $status = 'already_verified';
        } elseif (!$reserved && $user['verification_token'] !== null && $user['is_expired']) {
            $status = 'expired';
        } elseif (!$reserved) {
            $errors[] = t('Demasiadas tentativas. Pede um novo código.');
        } elseif (hash_equals($user['verification_token'], $digits)) {
            $pdo->prepare('UPDATE users SET email_verified = 1, verification_token = NULL, verification_expires = NULL, verification_attempts = 0 WHERE id = :id')
                ->execute([':id' => $user['id']]);
            try { (new EmailService())->sendWelcomeEmail($user['email'], $user['username']); } catch (Exception $e) {}
            $username = $user['username'];
            $status = 'success';
            session_regenerate_id(true);
            unset($_SESSION['pending_verification_email']);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
        } else {
            $remaining = VERIFY_MAX_ATTEMPTS - ($user['verification_attempts'] + 1);
            if ($remaining <= 0) {
                // Esgotou as tentativas: o código deixa de existir
                $pdo->prepare('UPDATE users SET verification_token = NULL WHERE id = :id')->execute([':id' => $user['id']]);
                $errors[] = t('Demasiadas tentativas. Pede um novo código.');
            } else {
                $errors[] = "Código de verificação inválido. Restam $remaining tentativa" . ($remaining > 1 ? 's' : '') . '.';
            }
        }
    }
}

// Reenviar código
if ($isPost && isset($_POST['resend']) && $email) {
    // Todos os cálculos de tempo são feitos pelo MySQL, para usar um só relógio
    $stmt = $pdo->prepare('SELECT id, username, email_verified,
                                  TIMESTAMPDIFF(SECOND, NOW(), verification_sent_at + INTERVAL ' . VERIFY_RESEND_SECONDS . ' SECOND) AS wait_seconds
                           FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    if ($user && $user['email_verified'] == 0) {
        $wait = (int) $user['wait_seconds'];
        if ($wait > 0) {
            $errors[] = "Aguarda $wait segundos antes de pedires um novo código.";
        } else {
            $newCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE users SET verification_token = :code, verification_expires = NOW() + INTERVAL ' . VERIFY_CODE_MINUTES . ' MINUTE,
                                  verification_attempts = 0, verification_sent_at = NOW() WHERE id = :id')
                ->execute([':code' => $newCode, ':id' => $user['id']]);
            try {
                if ((new EmailService())->sendVerificationEmail($email, $user['username'], $newCode)) $message = t('Novo código enviado!');
                else $errors[] = t('Erro ao enviar email. Tenta novamente.');
            } catch (Exception $e) { $errors[] = t('Erro ao enviar email.'); }
        }
    }
}

auth_page_start(t('Verificar email'));
?>
      <div class="au-card">
<?php if ($status === 'success'): ?>
        <div class="au-head au-head-center">
          <div class="au-status ok"><i class="bi bi-check-lg" aria-hidden="true"></i></div>
          <h2><?= te('Conta confirmada') ?></h2>
          <p><?= te('Bem-vindo à Finora, {name}. Vamos levar-te ao painel.', ['name' => $username]) ?></p>
        </div>
        <a href="../dashboard.php" class="au-btn"><?= te('Ir para o painel') ?></a>
<?php elseif ($status === 'already_verified'): ?>
        <div class="au-head au-head-center">
          <div class="au-status ok"><i class="bi bi-patch-check" aria-hidden="true"></i></div>
          <h2><?= te('Esta conta já está confirmada') ?></h2>
          <p><?= te('Podes entrar normalmente.') ?></p>
        </div>
        <a href="login.php" class="au-btn"><?= te('Entrar') ?></a>
<?php elseif ($status === 'expired'): ?>
        <div class="au-head au-head-center">
          <div class="au-status bad"><i class="bi bi-hourglass-bottom" aria-hidden="true"></i></div>
          <h2><?= te('O código expirou') ?></h2>
          <p><?= te('Os códigos valem {n} minutos. Pede um novo e usa-o logo.', ['n' => VERIFY_CODE_MINUTES]) ?></p>
        </div>
        <?= auth_alert('err', $errors) ?>
        <form method="post" data-once>
          <?= csrf_field() ?>
          <button type="submit" name="resend" value="1" class="au-btn"><?= te('Enviar código novo') ?></button>
        </form>
<?php elseif ($email === ''): ?>
        <div class="au-head au-head-center">
          <div class="au-status"><i class="bi bi-envelope" aria-hidden="true"></i></div>
          <h2><?= te('Entra para confirmar o email') ?></h2>
          <p><?= te('A tua sessão terminou. Entra com o email e a palavra-passe e enviamos-te para aqui outra vez.') ?></p>
        </div>
        <a href="login.php" class="au-btn"><?= te('Entrar') ?></a>
<?php else: ?>
        <div class="au-head au-head-center">
          <div class="au-status"><i class="bi bi-envelope-open" aria-hidden="true"></i></div>
          <h2><?= te('Confirma o teu email') ?></h2>
          <p><?= t('Enviámos um código de 6 dígitos para {email}.', ['email' => '<b>' . htmlspecialchars($email) . '</b>']) ?></p>
        </div>

        <?= auth_alert('err', $errors) ?>
        <?= auth_alert('ok', $message ? [$message] : []) ?>

        <form method="post" id="codeForm" data-once>
          <?= csrf_field() ?>
          <div class="au-field">
            <label class="au-label" for="code"><?= te('Código') ?></label>
            <input type="text" id="code" name="code" class="au-input au-otp" inputmode="numeric"
                   autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus>
          </div>
          <button type="submit" class="au-btn"><?= te('Confirmar') ?></button>
        </form>
        <form method="post" class="au-foot">
          <?= csrf_field() ?>
          <?= te('Não chegou? Vê o spam ou') ?>
          <button type="submit" name="resend" value="1" class="au-linkbtn"><?= te('envia outro código') ?></button>.
        </form>
<?php endif; ?>
<?php if (!in_array($status, ['success', 'already_verified'], true)): ?>
        <p class="au-foot au-muted"><a href="login.php"><?= te('Voltar ao login') ?></a></p>
<?php endif; ?>
      </div>
<?php
auth_page_end($status === 'success' ? "setTimeout(function () { location.href = '../dashboard.php'; }, 2500);" : <<<'JS'
(function () {
  // Assim que houver 6 dígitos, envia sozinho (também quando o código é colado)
  var input = document.getElementById('code');
  if (!input) return;
  input.addEventListener('input', function () {
    var digits = input.value.replace(/\D/g, '').slice(0, 6);
    input.value = digits;
    if (digits.length === 6) document.getElementById('codeForm').requestSubmit();
  });
})();
JS);
