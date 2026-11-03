<?php
// site/auth/verify_email_change.php — confirmar a mudança de email pedida nas definições
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/auth_layout.php';

$token  = (string) ($_GET['token'] ?? '');
$status = 'invalid';
$user   = null;
$errors = [];

// Procura pelo hash do token; a validade é comparada pelo MySQL, o relógio que a gravou.
// (Antes comparava-se com o relógio do PHP, que está noutro fuso: o link expirava logo.)
if (strlen($token) === 64 && ctype_xdigit($token)) {
    $stmt = $pdo->prepare('SELECT id, pending_email, pending_email_expires > NOW() AS still_valid
                           FROM users WHERE pending_email_token = :h LIMIT 1');
    $stmt->execute([':h' => hash('sha256', $token)]);
    $user = $stmt->fetch();
    if ($user) $status = $user['still_valid'] ? 'confirm' : 'expired';
}

// Só muda com um POST (botão). Alguns serviços de email abrem os links para os analisar;
// se um GET bastasse, a mudança era confirmada sem a pessoa carregar em nada.
if ($status === 'confirm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'A página esteve aberta demasiado tempo. Tenta outra vez.';
    } else {
        try {
            $upd = $pdo->prepare('UPDATE users SET email = pending_email, last_email_change = NOW(),
                                         pending_email = NULL, pending_email_token = NULL, pending_email_expires = NULL
                                  WHERE id = :id AND pending_email_token = :h AND pending_email_expires > NOW()');
            $upd->execute([':id' => $user['id'], ':h' => hash('sha256', $token)]);
            $status = $upd->rowCount() === 1 ? 'success' : 'invalid';
        } catch (PDOException $e) {
            // O email é UNIQUE: entretanto alguém criou uma conta com ele
            if ($e->getCode() === '23000') {
                $status = 'taken';
            } else {
                error_log('[verify_email_change] ' . $e->getMessage());
                $errors[] = 'Não foi possível mudar o email. Tenta outra vez.';
            }
        }
    }
}

if ($status === 'expired' && $user) {
    $pdo->prepare('UPDATE users SET pending_email = NULL, pending_email_token = NULL, pending_email_expires = NULL WHERE id = :id')
        ->execute([':id' => $user['id']]);
}

$back = !empty($_SESSION['user_id']) ? ['../pages/settings.php', 'Voltar às definições'] : ['login.php', 'Entrar'];

auth_page_start('Confirmar novo email');
?>
      <div class="au-card">
<?php if ($status === 'confirm'): ?>
        <div class="au-head au-head-center">
          <div class="au-status"><i class="bi bi-envelope-arrow-up" aria-hidden="true"></i></div>
          <h2>Confirmar novo email</h2>
          <p>A tua conta vai passar a usar <b><?= htmlspecialchars($user['pending_email']) ?></b> para entrar e receber avisos.</p>
        </div>
        <?= auth_alert('err', $errors) ?>
        <form method="post" data-once>
          <?= csrf_field() ?>
          <button type="submit" class="au-btn">Confirmar mudança</button>
        </form>
<?php elseif ($status === 'success'): ?>
        <div class="au-head au-head-center">
          <div class="au-status ok"><i class="bi bi-check-lg" aria-hidden="true"></i></div>
          <h2>Email mudado</h2>
          <p>A partir de agora entras com o email novo.</p>
        </div>
        <a href="<?= $back[0] ?>" class="au-btn"><?= $back[1] ?></a>
<?php else: ?>
        <div class="au-head au-head-center">
          <div class="au-status bad"><i class="bi bi-<?= $status === 'expired' ? 'hourglass-bottom' : 'x-lg' ?>" aria-hidden="true"></i></div>
          <h2><?= ['expired' => 'Este link expirou', 'taken' => 'Esse email já está em uso'][$status] ?? 'Este link não funciona' ?></h2>
          <p><?= [
                'expired' => 'Os links valem durante uma hora. Pede a mudança outra vez nas definições.',
                'taken'   => 'Entretanto foi criada outra conta com esse email. Escolhe outro nas definições.',
              ][$status] ?? 'Pode já ter sido usado, ou foi copiado incompleto. Pede a mudança outra vez nas definições.' ?></p>
        </div>
        <a href="<?= $back[0] ?>" class="au-btn"><?= $back[1] ?></a>
<?php endif; ?>
      </div>
<?php auth_page_end(); ?>
