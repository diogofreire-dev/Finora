<?php
// site/settings.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/EmailService.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$message = '';
$messageType = 'info';

// Função de validação de password
function validatePassword($password) {
    if (strlen($password) < 8) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[a-z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return false;
    return true;
}

// Buscar ou criar configurações do utilizador
$stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = :uid");
$stmt->execute([':uid' => $uid]);
$settings = $stmt->fetch();

if (!$settings) {
    $stmt = $pdo->prepare("INSERT INTO user_settings (user_id) VALUES (:uid)");
    $stmt->execute([':uid' => $uid]);
    $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = :uid");
    $stmt->execute([':uid' => $uid]);
    $settings = $stmt->fetch();
}

// Buscar informações do utilizador
$stmt = $pdo->prepare("
    SELECT username, email, created_at, last_password_change,
           last_email_change, two_factor_enabled, pending_email, pending_email_expires
    FROM users
    WHERE id = :uid
");
$stmt->execute([':uid' => $uid]);
$user = $stmt->fetch();

// Verificar se há email pendente de confirmação
$hasPendingEmail = false;
if ($user['pending_email'] && $user['pending_email_expires']) {
    if (strtotime($user['pending_email_expires']) > time()) {
        $hasPendingEmail = true;
    } else {
        // Limpar email pendente expirado
        $stmt = $pdo->prepare("UPDATE users SET pending_email = NULL, pending_email_token = NULL, pending_email_expires = NULL WHERE id = :uid");
        $stmt->execute([':uid' => $uid]);
        $user['pending_email'] = null;
    }
}

// Verificar se pode alterar password/email (30 dias)
$canChangePassword = true;
$canChangeEmail = true;
$daysUntilPasswordChange = 0;
$daysUntilEmailChange = 0;

if ($user['last_password_change']) {
    $lastChange = new DateTime($user['last_password_change']);
    $now = new DateTime();
    $diff = $now->diff($lastChange);
    $daysSince = $diff->days;
    
    if ($daysSince < 30) {
        $canChangePassword = false;
        $daysUntilPasswordChange = 30 - $daysSince;
    }
}

if ($user['last_email_change']) {
    $lastChange = new DateTime($user['last_email_change']);
    $now = new DateTime();
    $diff = $now->diff($lastChange);
    $daysSince = $diff->days;
    
    if ($daysSince < 30) {
        $canChangeEmail = false;
        $daysUntilEmailChange = 30 - $daysSince;
    }
}

// Processar alterações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_theme') {
        $theme = $_POST['theme'] ?? 'light';
        $notifications = isset($_POST['notifications']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("
                UPDATE user_settings
                SET theme = :theme, notifications = :notifications
                WHERE user_id = :uid
            ");
            $stmt->execute([
                ':theme' => $theme,
                ':notifications' => $notifications,
                ':uid' => $uid
            ]);

            $settings['theme'] = $theme;
            $settings['notifications'] = $notifications;

            $message = 'Configurações atualizadas com sucesso!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Erro ao atualizar configurações.';
            $messageType = 'danger';
        }
    }
    
    elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        $errors = [];
        
        if (!$canChangePassword) {
            $errors[] = "Só podes alterar a password novamente daqui a {$daysUntilPasswordChange} dia(s).";
        }
        
        // Verificar password atual
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :uid");
        $stmt->execute([':uid' => $uid]);
        $userPass = $stmt->fetch();
        
        if (!password_verify($currentPassword, $userPass['password_hash'])) {
            $errors[] = 'A password atual está incorreta.';
        }
        
        if (!validatePassword($newPassword)) {
            $errors[] = 'A nova password deve ter pelo menos 8 caracteres, incluindo maiúsculas, minúsculas, números e caracteres especiais.';
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'As passwords não coincidem.';
        }
        
        if (password_verify($newPassword, $userPass['password_hash'])) {
            $errors[] = 'A nova password não pode ser igual à atual.';
        }
        
        if (empty($errors)) {
            try {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET password_hash = :hash, last_password_change = NOW()
                    WHERE id = :uid
                ");
                $stmt->execute([':hash' => $hash, ':uid' => $uid]);

                // Enviar notificação por email
                try {
                    $emailService = new EmailService();
                    $emailService->sendPasswordChangedNotification($user['email'], $user['username']);
                } catch (Exception $e) {
                    error_log("Erro ao enviar notificacao de password: " . $e->getMessage());
                }

                $message = 'Password alterada com sucesso! Próxima alteração disponível em 30 dias.';
                $messageType = 'success';

                // Atualizar data
                $user['last_password_change'] = date('Y-m-d H:i:s');
                $canChangePassword = false;
                $daysUntilPasswordChange = 30;
            } catch (PDOException $e) {
                $message = 'Erro ao alterar password.';
                $messageType = 'danger';
            }
        } else {
            $message = implode('<br>', $errors);
            $messageType = 'danger';
        }
    }
    
    elseif ($action === 'change_email') {
        $currentPassword = $_POST['current_password_email'] ?? '';
        $newEmail = trim($_POST['new_email'] ?? '');

        $errors = [];

        if (!$canChangeEmail) {
            $errors[] = "Só podes alterar o email novamente daqui a {$daysUntilEmailChange} dia(s).";
        }

        if ($hasPendingEmail) {
            $errors[] = "Já tens um pedido de alteração de email pendente. Verifica a tua caixa de entrada.";
        }

        // Verificar password atual
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :uid");
        $stmt->execute([':uid' => $uid]);
        $userPass = $stmt->fetch();

        if (!password_verify($currentPassword, $userPass['password_hash'])) {
            $errors[] = 'A password está incorreta.';
        }

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido.';
        }

        if ($newEmail === $user['email']) {
            $errors[] = 'O novo email não pode ser igual ao atual.';
        }

        // Verificar se email já está em uso
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :uid");
        $stmt->execute([':email' => $newEmail, ':uid' => $uid]);
        if ($stmt->fetch()) {
            $errors[] = 'Este email já está em uso por outra conta.';
        }

        if (empty($errors)) {
            try {
                // Gerar token de verificação
                $token = bin2hex(random_bytes(32));
                $tokenExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Guardar email pendente na BD
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET pending_email = :email, pending_email_token = :token, pending_email_expires = :expires
                    WHERE id = :uid
                ");
                $stmt->execute([
                    ':email' => $newEmail,
                    ':token' => $token,
                    ':expires' => $tokenExpiry,
                    ':uid' => $uid
                ]);

                // Enviar email de verificação para o NOVO email
                $emailService = new EmailService();
                if ($emailService->sendEmailChangeVerification($newEmail, $user['username'], $token)) {
                    $message = 'Enviamos um email de confirmação para <strong>' . htmlspecialchars($newEmail) . '</strong>. Clica no link para confirmar a alteração.';
                    $messageType = 'success';
                    $hasPendingEmail = true;
                    $user['pending_email'] = $newEmail;
                } else {
                    // Limpar dados se falhar envio
                    $stmt = $pdo->prepare("UPDATE users SET pending_email = NULL, pending_email_token = NULL, pending_email_expires = NULL WHERE id = :uid");
                    $stmt->execute([':uid' => $uid]);
                    $errors[] = 'Erro ao enviar email de verificação. Tenta novamente.';
                }
            } catch (Exception $e) {
                $message = 'Erro ao processar pedido.';
                $messageType = 'danger';
            }
        }

        if (!empty($errors)) {
            $message = implode('<br>', $errors);
            $messageType = 'danger';
        }
    }

    elseif ($action === 'cancel_email_change') {
        $stmt = $pdo->prepare("
            UPDATE users
            SET pending_email = NULL, pending_email_token = NULL, pending_email_expires = NULL
            WHERE id = :uid
        ");
        $stmt->execute([':uid' => $uid]);
        $hasPendingEmail = false;
        $user['pending_email'] = null;
        $message = 'Pedido de alteração de email cancelado.';
        $messageType = 'info';
    }
}

$currentTheme = $settings['theme'] ?? 'light';
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Configurações - FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <style>
    /* ========== BACKGROUND ANIMADO ========== */
    body {
      position: relative;
      min-height: 100vh;
    }
    .bg-animation {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 0;
      pointer-events: none;
      overflow: hidden;
    }
    [data-theme="light"] body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, #e8eef5ff 0%, #e9eef8ff 50%, #e8edf5ff 100%);
      z-index: -1;
    }
    [data-theme="dark"] body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, #1a1d29 0%, #252936 50%, #1a1d29 100%);
      z-index: -1;
    }
    .floating-shape {
      position: absolute;
      border-radius: 50%;
      animation: float 20s infinite ease-in-out;
    }
  [data-theme="light"] .floating-shape {
    background: rgba(46, 88, 204);
  }
  [data-theme="dark"] .floating-shape {
    background: rgba(46, 88, 204);
  }
    .shape1 { width: 300px; height: 300px; top: -100px; left: -100px; animation-delay: 0s; }
    .shape2 { width: 200px; height: 200px; bottom: -50px; right: -50px; animation-delay: 5s; }
    .shape3 { width: 150px; height: 150px; top: 50%; right: 10%; animation-delay: 2s; }
    .shape4 { width: 100px; height: 100px; bottom: 20%; left: 15%; animation-delay: 7s; }
    .shape5 { width: 250px; height: 250px; top: 30%; left: 50%; animation-delay: 3s; }
    .shape6 { width: 250px; height: 300px; top: -100px; right: -100px; animation-delay: 6s; }
    .shape7 { width: 180px; height: 180px; top: 10%; left: 70%; animation-delay: 1s; }
    .shape8 { width: 120px; height: 120px; bottom: 10%; right: 30%; animation-delay: 4s; }
    .shape9 { width: 220px; height: 220px; top: 60%; left: -80px; animation-delay: 8s; }
    @keyframes float {
      0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.3; }
      50% { transform: translateY(-30px) rotate(180deg); opacity: 0.6; }
    }
    .particle {
      position: absolute;
      width: 4px;
      height: 4px;
      border-radius: 50%;
      animation: rise 15s infinite ease-in;
    }
  [data-theme="light"] .particle { background: rgba(46, 88, 204); }
  [data-theme="dark"] .particle { background: rgba(46, 88, 204); }
    @keyframes rise {
      0% { transform: translateY(0) translateX(0); opacity: 0; }
      10% { opacity: 1; }
      90% { opacity: 1; }
      100% { transform: translateY(-100vh) translateX(50px); opacity: 0; }
    }
    .navbar, .container { position: relative; z-index: 1; }
    @media (max-width: 768px) {
      .floating-shape { opacity: 0.4; animation-duration: 25s; }
      .particle { display: none; }
    }
    @media (prefers-reduced-motion: reduce) {
      .floating-shape, .particle { animation: none; opacity: 0.2; }
    }
    :root {
      --primary-blue: #3498db;
      --dark-blue: #2980b9;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background-color: var(--bg-primary);
      color: var(--text-primary);
      transition: background-color 0.3s, color 0.3s;
    }
    .navbar { 
      box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
      background: var(--navbar-bg);
    }
    .navbar-brand img { height: 35px; margin-right: 8px; }
    .btn-primary {
      background: var(--primary-blue);
      border-color: var(--primary-blue);
    }
    .btn-primary:hover {
      background: var(--dark-blue);
      border-color: var(--dark-blue);
    }
    .card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 4px 20px var(--shadow);
      background: var(--bg-secondary);
      color: var(--text-primary);
      transition: all 0.3s;
      margin-bottom: 20px;
    }
    .card-header-custom {
      background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
      border-radius: 16px 16px 0 0;
      padding: 20px 24px;
      color: white;
    }
    .form-label {
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 8px;
    }
    .form-control, .form-select {
      border: 2px solid var(--border-color);
      border-radius: 10px;
      padding: 12px 16px;
      transition: all 0.3s;
      background: var(--bg-primary);
      color: var(--text-primary);
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--primary-blue);
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
      background: var(--bg-primary);
      color: var(--text-primary);
    }
    .setting-item {
      padding: 24px;
      border-bottom: 1px solid var(--border-color);
      transition: background-color 0.3s;
    }
    .setting-item:last-child {
      border-bottom: none;
    }
    .setting-item:hover {
      background: var(--bg-hover);
    }
    .theme-preview {
      width: 80px;
      height: 50px;
      border-radius: 8px;
      border: 3px solid transparent;
      cursor: pointer;
      transition: all 0.3s;
      position: relative;
      overflow: hidden;
    }
    .theme-preview:hover {
      transform: scale(1.05);
    }
    .theme-preview.active {
      border-color: var(--primary-blue);
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
    }
    .theme-preview-light {
      background: linear-gradient(135deg, #ffffff 50%, #f8f9fa 50%);
    }
    .theme-preview-dark {
      background: linear-gradient(135deg, #1a1d29 50%, #2c3e50 50%);
    }
    .theme-preview input[type="radio"] {
      display: none;
    }
    .form-check-input:checked {
      background-color: var(--primary-blue);
      border-color: var(--primary-blue);
    }
    .security-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
    }
    .security-badge.available {
      background: rgba(52, 152, 219, 0.1);
      color: var(--primary-blue);
    }
    .security-badge.locked {
      background: rgba(231, 76, 60, 0.1);
      color: #e74c3c;
    }
    .security-badge.coming-soon {
      background: rgba(52, 152, 219, 0.1);
      color: #3498db;
    }
    .info-box {
      background: var(--bg-primary);
      padding: 12px 16px;
      border-radius: 8px;
      margin-top: 12px;
    }
    .password-strength {
      height: 6px;
      background: var(--bg-hover);
      border-radius: 3px;
      margin-top: 10px;
      overflow: hidden;
    }
    .password-strength-bar {
      height: 100%;
      width: 0;
      transition: all 0.4s;
      border-radius: 3px;
    }
    .strength-weak { width: 33%; background: #e74c3c; }
    .strength-medium { width: 66%; background: #f39c12; }
    .strength-strong { width: 100%; background: var(--primary-blue); }
    
    [data-theme="dark"] .form-control,
    [data-theme="dark"] .form-select {
      background: var(--bg-secondary);
      color: var(--text-primary);
    }
    [data-theme="dark"] .form-control::placeholder {
      color: var(--text-secondary);
      opacity: 0.6;
    }
  </style>
</head>
<body>
  <!-- Background animado -->
<div class="bg-animation">
  <div class="floating-shape shape1"></div>
  <div class="floating-shape shape2"></div>
  <div class="floating-shape shape3"></div>
  <div class="floating-shape shape4"></div>
  <div class="floating-shape shape5"></div>
  <div class="floating-shape shape6"></div>
  <div class="floating-shape shape7"></div>
  <div class="floating-shape shape8"></div>  
  <div class="floating-shape shape9"></div>
</div>
<nav class="navbar navbar-expand-lg navbar-light">
  <div class="container">
    <a class="navbar-brand fw-bold" href="../dashboard.php">
      <img src="../assets/logo2.png" alt="Freecard">
      FreeCard
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="cards.php"><i class="bi bi-wallet2"></i> Cartões</a></li>
        <li class="nav-item"><a class="nav-link" href="transactions.php"><i class="bi bi-receipt"></i> Transações</a></li>
        <li class="nav-item"><a class="nav-link" href="budgets.php"><i class="bi bi-piggy-bank"></i> Orçamentos</a></li>
        <li class="nav-item"><a class="nav-link" href="reminders.php"><i class="bi bi-calendar-check"></i> Lembretes</a></li>
        <li class="nav-item"><a class="nav-link" href="analytics.php"><i class="bi bi-graph-up"></i> Análise</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <?=htmlspecialchars($_SESSION['username'])?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item active" href="settings.php"><i class="bi bi-gear"></i> Configurações</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4 mb-5">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10">
      <div class="mb-4">
        <a href="../dashboard.php" class="text-decoration-none" style="color: var(--text-secondary);">
          <i class="bi bi-arrow-left"></i> Voltar ao Dashboard
        </a>
      </div>

      <h2 class="mb-4"><i class="bi bi-gear"></i> Configurações</h2>

      <?php if ($message): ?>
        <div class="alert alert-<?=$messageType?> alert-dismissible fade show">
          <i class="bi bi-<?=$messageType === 'success' ? 'check-circle' : 'exclamation-circle'?>"></i>
          <?=$message?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- SEGURANÇA -->
      <div class="card">
        <div class="card-header-custom">
          <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Segurança</h5>
        </div>
        <div class="card-body p-0">
          
          <!-- Alterar Password -->
          <div class="setting-item">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h6 class="mb-1">
                  <i class="bi bi-key"></i> Alterar Password
                  <?php if ($canChangePassword): ?>
                    <span class="security-badge available">
                      <i class="bi bi-check-circle"></i> Disponível
                    </span>
                  <?php else: ?>
                    <span class="security-badge locked">
                      <i class="bi bi-lock"></i> Bloqueado por <?=$daysUntilPasswordChange?> dia(s)
                    </span>
                  <?php endif; ?>
                </h6>
                <p class="text-muted small mb-0">
                  Última alteração: 
                  <?php if ($user['last_password_change']): ?>
                    <?=date('d/m/Y', strtotime($user['last_password_change']))?>
                  <?php else: ?>
                    Nunca
                  <?php endif; ?>
                </p>
              </div>
              <button 
                class="btn btn-sm btn-outline-primary" 
                data-bs-toggle="collapse" 
                data-bs-target="#changePasswordForm"
                <?=!$canChangePassword ? 'disabled' : ''?>
              >
                <i class="bi bi-pencil"></i> Alterar
              </button>
            </div>
            
            <div class="collapse" id="changePasswordForm">
              <form method="post" class="mt-3">
                <input type="hidden" name="action" value="change_password">
                
                <div class="mb-3">
                  <label class="form-label">Password Atual *</label>
                  <input 
                    type="password" 
                    name="current_password" 
                    class="form-control" 
                    placeholder="Introduz a tua password atual"
                    required
                  >
                </div>
                
                <div class="mb-3">
                  <label class="form-label">Nova Password *</label>
                  <input 
                    type="password" 
                    name="new_password" 
                    id="new_password"
                    class="form-control" 
                    placeholder="Cria uma password segura"
                    required
                  >
                  <div class="password-strength">
                    <div class="password-strength-bar" id="strength-bar"></div>
                  </div>
                  <small class="text-muted">Mínimo 8 caracteres, incluindo maiúsculas, minúsculas, números e especiais</small>
                </div>
                
                <div class="mb-3">
                  <label class="form-label">Confirmar Nova Password *</label>
                  <input 
                    type="password" 
                    name="confirm_password" 
                    class="form-control" 
                    placeholder="Repete a nova password"
                    required
                  >
                </div>
                
                <div class="info-box">
                  <small>
                    <i class="bi bi-info-circle"></i>
                    <strong>Política de Segurança:</strong> Por razões de segurança, só podes alterar a password uma vez a cada 30 dias.
                  </small>
                </div>
                
                <div class="d-grid gap-2 mt-3">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Alterar Password
                  </button>
                  <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#changePasswordForm">
                    Cancelar
                  </button>
                </div>
              </form>
            </div>
          </div>

          <!-- Alterar Email -->
          <div class="setting-item">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h6 class="mb-1">
                  <i class="bi bi-envelope"></i> Alterar Email
                  <?php if ($hasPendingEmail): ?>
                    <span class="security-badge" style="background: rgba(243, 156, 18, 0.1); color: #f39c12;">
                      <i class="bi bi-clock-history"></i> Pendente
                    </span>
                  <?php elseif ($canChangeEmail): ?>
                    <span class="security-badge available">
                      <i class="bi bi-check-circle"></i> Disponível
                    </span>
                  <?php else: ?>
                    <span class="security-badge locked">
                      <i class="bi bi-lock"></i> Bloqueado por <?=$daysUntilEmailChange?> dia(s)
                    </span>
                  <?php endif; ?>
                </h6>
                <p class="text-muted small mb-0">
                  Email atual: <strong><?=htmlspecialchars($user['email'])?></strong>
                </p>
                <?php if ($hasPendingEmail): ?>
                <p class="small mb-0" style="color: #f39c12;">
                  <i class="bi bi-arrow-right"></i> Novo email pendente: <strong><?=htmlspecialchars($user['pending_email'])?></strong>
                </p>
                <?php endif; ?>
                <p class="text-muted small mb-0">
                  Última alteração:
                  <?php if ($user['last_email_change']): ?>
                    <?=date('d/m/Y', strtotime($user['last_email_change']))?>
                  <?php else: ?>
                    Nunca
                  <?php endif; ?>
                </p>
              </div>
              <?php if (!$hasPendingEmail): ?>
              <button
                class="btn btn-sm btn-outline-primary"
                data-bs-toggle="collapse"
                data-bs-target="#changeEmailForm"
                <?=!$canChangeEmail ? 'disabled' : ''?>
              >
                <i class="bi bi-pencil"></i> Alterar
              </button>
              <?php endif; ?>
            </div>

            <?php if ($hasPendingEmail): ?>
            <div class="info-box" style="background: rgba(243, 156, 18, 0.1);">
              <small>
                <i class="bi bi-envelope-exclamation"></i>
                <strong>Verificação pendente:</strong> Enviamos um email para <strong><?=htmlspecialchars($user['pending_email'])?></strong>.
                Clica no link para confirmar a alteração. O link expira em 1 hora.
              </small>
            </div>
            <form method="post" class="mt-2">
              <input type="hidden" name="action" value="cancel_email_change">
              <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-circle"></i> Cancelar pedido
              </button>
            </form>
            <?php else: ?>
            <div class="collapse" id="changeEmailForm">
              <form method="post" class="mt-3">
                <input type="hidden" name="action" value="change_email">

                <div class="mb-3">
                  <label class="form-label">Password (para confirmar) *</label>
                  <input
                    type="password"
                    name="current_password_email"
                    class="form-control"
                    placeholder="Introduz a tua password"
                    required
                  >
                </div>

                <div class="mb-3">
                  <label class="form-label">Novo Email *</label>
                  <input
                    type="email"
                    name="new_email"
                    class="form-control"
                    placeholder="novo@email.com"
                    required
                  >
                </div>

                <div class="info-box">
                  <small>
                    <i class="bi bi-info-circle"></i>
                    <strong>Verificação necessária:</strong> Vais receber um email no novo endereço para confirmar a alteração.
                  </small>
                </div>

                <div class="d-grid gap-2 mt-3">
                  <button type="submit" class="btn btn-primary">
                    <i class="bi bi-envelope"></i> Enviar Verificação
                  </button>
                  <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#changeEmailForm">
                    Cancelar
                  </button>
                </div>
              </form>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- APARÊNCIA -->
      <div class="card">
        <div class="card-header-custom">
          <h5 class="mb-0"><i class="bi bi-palette"></i> Aparência</h5>
        </div>
        <form method="post">
          <input type="hidden" name="action" value="update_theme">
          <div class="card-body p-0">
            <div class="setting-item">
              <div class="mb-3">
                <h6 class="mb-1"><i class="bi bi-moon-stars"></i> Tema</h6>
                <p class="text-muted small mb-3">Escolhe o tema que preferes para a interface</p>
              </div>
              
              <div class="d-flex gap-3">
                <label class="theme-preview theme-preview-light <?=$currentTheme === 'light' ? 'active' : ''?>">
                  <input type="radio" name="theme" value="light" <?=$currentTheme === 'light' ? 'checked' : ''?>>
                  <div class="p-2">
                    <small class="fw-bold" style="color: #2c3e50;">Claro</small>
                  </div>
                </label>
                
                <label class="theme-preview theme-preview-dark <?=$currentTheme === 'dark' ? 'active' : ''?>">
                  <input type="radio" name="theme" value="dark" <?=$currentTheme === 'dark' ? 'checked' : ''?>>
                  <div class="p-2">
                    <small class="fw-bold" style="color: #ecf0f1;">Escuro</small>
                  </div>
                </label>
              </div>
            </div>

            <div class="setting-item">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h6 class="mb-1"><i class="bi bi-bell"></i> Notificações </h6>
                  <p class="text-muted small mb-0">Recebe alertas quando te aproximas dos limites dos cartões</p>
                </div>
                <div class="form-check form-switch">
                  <input 
                    class="form-check-input" 
                    type="checkbox" 
                    name="notifications" 
                    <?=$settings['notifications'] ? 'checked' : ''?>
                    style="width: 3em; height: 1.5em;"
                  >
                </div>
              </div>
            </div>
          </div>
          
          <div class="card-body">
            <div class="d-grid gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle"></i> Guardar Alterações
              </button>
            </div>
          </div>
        </form>
      </div>

      <!-- INFORMAÇÕES DA CONTA -->
      <div class="card">
        <div class="card-header-custom">
          <h5 class="mb-0"><i class="bi bi-person"></i> Informações da Conta</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <small class="text-muted">Nome de utilizador</small>
              <div class="fw-semibold"><?=htmlspecialchars($user['username'])?></div>
            </div>
            <div class="col-md-6 mb-3">
              <small class="text-muted">Email</small>
              <div class="fw-semibold"><?=htmlspecialchars($user['email'])?></div>
            </div>
            <div class="col-md-6 mb-3">
              <small class="text-muted">Membro desde</small>
              <div class="fw-semibold"><?=date('d/m/Y', strtotime($user['created_at']))?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- SOBRE -->
      <div class="card">
        <div class="card-body">
          <h6 class="mb-3"><i class="bi bi-info-circle"></i> Sobre o FreeCard</h6>
          <p class="text-muted small mb-2">Versão: 1.0.0</p>
          <p class="text-muted small mb-0">
            Desenvolvido por Diogo Freire e Jandro Antunes<br>
            Projeto de Aptidão Profissional
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Preview ao vivo do tema
document.querySelectorAll('input[name="theme"]').forEach(radio => {
  radio.addEventListener('change', function() {
    document.documentElement.setAttribute('data-theme', this.value);
    document.querySelectorAll('.theme-preview').forEach(preview => {
      preview.classList.remove('active');
    });
    this.closest('.theme-preview').classList.add('active');
  });
});

// Password strength indicator
const passwordInput = document.getElementById('new_password');
if (passwordInput) {
  passwordInput.addEventListener('input', function(e) {
    const password = e.target.value;
    const strengthBar = document.getElementById('strength-bar');
    
    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    strengthBar.className = 'password-strength-bar';
    if (strength >= 4) {
      strengthBar.classList.add('strength-strong');
    } else if (strength >= 2) {
      strengthBar.classList.add('strength-medium');
    } else if (strength >= 1) {
      strengthBar.classList.add('strength-weak');
    }
  });
}

(function() {
  if (window.innerWidth <= 768) return;
  const container = document.querySelector('.bg-animation');
  if (!container) return;
  for (let i = 0; i < 15; i++) {
    const particle = document.createElement('div');
    particle.className = 'particle';
    particle.style.left = Math.random() * 100 + '%';
    particle.style.animationDelay = Math.random() * 15 + 's';
    particle.style.animationDuration = (15 + Math.random() * 10) + 's';
    container.appendChild(particle);
  }
})();
</script>
</body>
</html>