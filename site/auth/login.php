<?php
// site/auth/login.php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/paths.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/throttle.php';
require_once __DIR__ . '/../helpers/auth_layout.php';

// Quem já tem sessão vai direto para o painel. Antes a sessão era destruída num simples GET,
// e qualquer site podia terminar a sessão de alguém só com <img src=".../login.php">.
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . (safe_next($_GET['next'] ?? null) ?? '../dashboard.php'));
    exit;
}

// Força bruta numa conta: bloqueio temporário dessa conta
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_MINUTES = 15;
// Força bruta espalhada por muitas contas: limite por IP
const LOGIN_IP_MAX     = 20;
const LOGIN_IP_MINUTES = 15;
// Quando o email não existe, o password_verify corre na mesma contra este hash falso.
// Assim a resposta demora o mesmo e o tempo não revela se a conta existe.
const LOGIN_DUMMY_HASH = '$2y$10$KmytJahFyjj4PSyNTz7n6eHlpKhKP7AXbBIApRcE9mA25uC/qcD8W';

$errors = [];
$email  = '';

if (!empty($_SESSION['google_error'])) {
    $errors[] = $_SESSION['google_error'];
    unset($_SESSION['google_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    // CSRF no login: sem isto, um site podia pôr a vítima a entrar na conta do atacante
    // e tudo o que ela registasse ficava visível para ele (login CSRF).
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = t('A página esteve aberta demasiado tempo. Tenta outra vez.');
    } elseif ($email === '' || $password === '') {
        $errors[] = t('Preenche o email e a palavra-passe.');
    } elseif (throttle_count($pdo, 'login', client_ip(), LOGIN_IP_MINUTES) >= LOGIN_IP_MAX) {
        $errors[] = t('Demasiadas tentativas a partir desta ligação. Espera {n} minutos.', ['n' => LOGIN_IP_MINUTES]);
    } else {
        // O tempo de bloqueio é calculado pelo MySQL (mesmo relógio que grava o NOW())
        $stmt = $pdo->prepare('SELECT id, username, email, password_hash, email_verified,
                                      GREATEST(TIMESTAMPDIFF(SECOND, NOW(), login_locked_until), 0) AS lock_seconds
                               FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch();

        $passwordOk = password_verify($password, $user['password_hash'] ?? LOGIN_DUMMY_HASH);

        if ($user && $user['lock_seconds'] > 0) {
            // Conta bloqueada: recusar mesmo que a palavra-passe esteja certa
            $min = (int) ceil($user['lock_seconds'] / 60);
            $errors[] = $min > 1 ? t('Demasiadas tentativas. Tenta novamente daqui a {n} minutos.', ['n' => $min])
                               : t('Demasiadas tentativas. Tenta novamente daqui a 1 minuto.');
        } elseif ($user && $passwordOk) {
            // Login certo: o contador volta a zero
            $pdo->prepare('UPDATE users SET failed_login_attempts = 0, login_locked_until = NULL WHERE id = :id')
                ->execute([':id' => $user['id']]);

            if (!$user['email_verified']) {
                $_SESSION['pending_verification_email'] = $user['email'];
                header('Location: verify.php');
                exit;
            }
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            // Voltar à página pedida antes do login, só se for um caminho interno
            header('Location: ' . (safe_next($_GET['next'] ?? null) ?? '../dashboard.php'));
            exit;
        } else {
            throttle_record($pdo, 'login', client_ip());
            if ($user) {
                // Contar a falha num UPDATE atómico (pedidos em paralelo não se perdem)
                $pdo->prepare('UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = :id')
                    ->execute([':id' => $user['id']]);
                // À 5.ª falha: bloqueio temporário e o contador recomeça
                $pdo->prepare('UPDATE users SET login_locked_until = NOW() + INTERVAL ' . LOGIN_LOCK_MINUTES . ' MINUTE, failed_login_attempts = 0
                               WHERE id = :id AND failed_login_attempts >= :max')
                    ->execute([':id' => $user['id'], ':max' => LOGIN_MAX_ATTEMPTS]);
            }
            // Mesma mensagem para email inexistente e palavra-passe errada (evita enumeração de contas)
            $errors[] = t('Email ou palavra-passe incorretos.');
        }
    }
}

auth_page_start(t('Entrar'), [
    'description' => t('Entra na tua conta Finora e controla os teus cartões, transações e orçamentos.'),
    'robots'      => 'noindex, follow',
    'panel'       => [
        'title' => t('Bem-vindo de volta.'),
        'text'  => t('Os teus cartões, gastos e orçamentos estão onde os deixaste.'),
    ],
]);
?>
      <div class="au-card">
        <div class="au-head">
          <h2><?= te('Entrar na conta') ?></h2>
          <p><?= te('Usa o email e a palavra-passe com que te registaste.') ?></p>
        </div>

        <?= auth_alert('err', $errors) ?>

        <?= auth_google_button(t('Continuar com Google')) ?>
        <div class="au-or"><?= te('ou com email') ?></div>

        <form method="post" data-once>
          <?= csrf_field() ?>
          <div class="au-field">
            <label class="au-label" for="email"><?= te('Email') ?></label>
            <div class="au-input-wrap">
              <i class="bi bi-envelope au-input-ico" aria-hidden="true"></i>
              <input type="email" id="email" name="email" class="au-input" maxlength="255"
                     value="<?= htmlspecialchars($email) ?>" autocomplete="email" required
                     <?= $email === '' ? 'autofocus' : '' ?>>
            </div>
          </div>
          <div class="au-field">
            <div class="au-label">
              <label for="password"><?= te('Palavra-passe') ?></label>
              <a href="forgot_password.php"><?= te('Esqueci-me') ?></a>
            </div>
            <?= auth_password_input('password', 'password', 'current-password', $email !== '' ? 'autofocus' : '') ?>
          </div>
          <button type="submit" class="au-btn"><?= te('Entrar') ?></button>
        </form>

        <p class="au-foot"><?= te('Ainda não tens conta?') ?> <a href="register.php"><?= te('Criar conta') ?></a></p>
      </div>
<?php auth_page_end(); ?>
