<?php
// site/auth/register.php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/EmailService.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/throttle.php';
require_once __DIR__ . '/../helpers/password_rules.php';
require_once __DIR__ . '/../helpers/auth_layout.php';

if (!empty($_SESSION['user_id'])) { header('Location: ../dashboard.php'); exit; }

// Cada registo envia um email: sem limite, o formulário servia para encher a caixa de alguém
const REGISTER_IP_MAX     = 5;
const REGISTER_IP_MINUTES = 60;

// O domínio tem de existir e receber email (apanha erros como "gmail.con")
function email_domain_ok(string $email): bool {
    $domain = substr(strrchr($email, '@'), 1);
    if (!function_exists('checkdnsrr')) return true;
    return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
}

$errors = [];
$username = ''; $email = ''; $emailConfirm = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nome: sem caracteres invisíveis/de controlo e com espaços normais
    $username     = trim(preg_replace('/\s+/u', ' ', preg_replace('/\p{C}/u', '', (string) ($_POST['username'] ?? '')) ?? ''));
    $email        = strtolower(trim((string) ($_POST['email'] ?? '')));
    $emailConfirm = strtolower(trim((string) ($_POST['email_confirm'] ?? '')));
    $password     = (string) ($_POST['password'] ?? '');
    $password2    = (string) ($_POST['password2'] ?? '');

    if (!hash_equals(csrf_token(), (string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'A página esteve aberta demasiado tempo. Tenta outra vez.';
    } else {
        $len = mb_strlen($username);
        if ($len < 3 || $len > 50)                                    $errors[] = 'O nome tem de ter entre 3 e 50 caracteres.';
        if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Esse email não parece válido.';
        elseif (!email_domain_ok($email))                             $errors[] = 'O domínio desse email não recebe mensagens. Confirma se está bem escrito.';
        if ($email !== $emailConfirm)                                 $errors[] = 'Os dois emails não são iguais.';
        if ($msg = password_problem_message($password))               $errors[] = $msg;
        if ($password !== $password2)                                 $errors[] = 'As duas palavras-passe não são iguais.';
    }

    if (!$errors && !throttle_allow($pdo, 'register', client_ip(), REGISTER_IP_MAX, REGISTER_IP_MINUTES)) {
        $errors[] = 'Foram criadas demasiadas contas a partir desta ligação. Tenta daqui a uma hora.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Já existe uma conta com este email. Entra ou recupera a palavra-passe.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            try {
                // Conta e email juntos: se o email falhar, a conta não fica criada a meio
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO users (username, email, password_hash, email_verified, verification_token, verification_expires, verification_sent_at)
                               VALUES (:u, :e, :p, 0, :token, NOW() + INTERVAL 15 MINUTE, NOW())')
                    ->execute([':u' => $username, ':e' => $email, ':p' => $hash, ':token' => $code]);
                if (!(new EmailService())->sendVerificationEmail($email, $username, $code)) {
                    throw new RuntimeException('Falha ao enviar email.');
                }
                $pdo->commit();
                $_SESSION['pending_verification_email'] = $email;
                header('Location: verify.php');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('[register] ' . $e->getMessage());
                $errors[] = 'Não conseguimos enviar o email com o código, por isso a conta não foi criada. Tenta outra vez.';
            }
        }
    }
}

auth_page_start('Criar conta', [
    'description' => 'Cria a tua conta Finora gratuitamente e começa a gerir os teus cartões e despesas hoje.',
    'robots'      => 'noindex, follow',
    'panel'       => [
        'title' => 'O teu dinheiro, com clareza.',
        'text'  => 'Cria a conta em dois minutos, confirma o email com um código e começa a registar.',
    ],
]);
?>
      <div class="au-card">
        <div class="au-head">
          <h2>Criar conta</h2>
          <p>É grátis. Vais receber um código no email para confirmar.</p>
        </div>

        <?= auth_alert('err', $errors) ?>

        <?= auth_google_button('Registar com Google') ?>
        <div class="au-or">ou com email</div>

        <form method="post" data-once>
          <?= csrf_field() ?>
          <div class="au-field">
            <label class="au-label" for="username">Nome</label>
            <div class="au-input-wrap">
              <i class="bi bi-person au-input-ico" aria-hidden="true"></i>
              <input type="text" id="username" name="username" class="au-input" minlength="3" maxlength="50"
                     value="<?= htmlspecialchars($username) ?>" autocomplete="name" required autofocus>
            </div>
          </div>

          <div class="au-row">
            <div class="au-field">
              <label class="au-label" for="email">Email</label>
              <div class="au-input-wrap">
                <i class="bi bi-envelope au-input-ico" aria-hidden="true"></i>
                <input type="email" id="email" name="email" class="au-input" maxlength="255"
                       value="<?= htmlspecialchars($email) ?>" autocomplete="email" required>
              </div>
            </div>
            <div class="au-field">
              <label class="au-label" for="email_confirm">Repetir email</label>
              <div class="au-input-wrap">
                <i class="bi bi-envelope-check au-input-ico" aria-hidden="true"></i>
                <input type="email" id="email_confirm" name="email_confirm" class="au-input" maxlength="255"
                       value="<?= htmlspecialchars($emailConfirm) ?>" autocomplete="off" required>
              </div>
            </div>
          </div>

          <div class="au-field">
            <label class="au-label" for="password">Palavra-passe</label>
            <?= auth_password_input('password', 'password', 'new-password', 'minlength="8" maxlength="72" aria-describedby="passwordReqs"') ?>
            <?= auth_password_rules('password') ?>
          </div>

          <div class="au-field">
            <label class="au-label" for="password2">Repetir palavra-passe</label>
            <?= auth_password_input('password2', 'password2', 'new-password', 'maxlength="72"') ?>
          </div>

          <button type="submit" class="au-btn">Criar conta</button>
        </form>

        <p class="au-foot">Já tens conta? <a href="login.php">Entrar</a></p>
      </div>
<?php auth_page_end(); ?>
