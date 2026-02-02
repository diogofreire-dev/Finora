<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/EmailService.php';

function validatePassword($password) {
    if (strlen($password) < 8) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[a-z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return false;
    return true;
}

$errors = [];
$success = false;
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($username === '' || strlen($username) < 3) $errors[] = 'Nome de utilizador inválido (mínimo 3 caracteres).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
    if (!validatePassword($password)) $errors[] = 'A palavra-passe deve ter pelo menos 8 caracteres, incluindo maiúsculas, minúsculas, números e caracteres especiais.';
    if ($password !== $password2) $errors[] = 'As palavras-passe não coincidem.';

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
        $stmt->execute([':u' => $username, ':e' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Nome de utilizador ou email já em uso.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Gerar código de verificação de 6 dígitos
            $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $tokenExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

            // Criar utilizador com email_verified = 0
            $ins = $pdo->prepare('
                INSERT INTO users (username, email, password_hash, email_verified, verification_token, verification_expires)
                VALUES (:u, :e, :p, 0, :token, :expires)
            ');
            $ins->execute([
                ':u' => $username,
                ':e' => $email,
                ':p' => $hash,
                ':token' => $verificationCode,
                ':expires' => $tokenExpiry
            ]);

            $userId = $pdo->lastInsertId();

            // Enviar email de verificação
            $emailSent = false;
            try {
                $emailService = new EmailService();
                $emailSent = $emailService->sendVerificationEmail($email, $username, $verificationCode);
                if ($emailSent) {
                    error_log("Email de verificacao enviado para: $email ($username)");
                    // Guardar email na sessão para a página de verificação
                    $_SESSION['pending_verification_email'] = $email;
                    // Redirecionar para página de verificação
                    header('Location: verify.php');
                    exit;
                } else {
                    error_log("Falha ao enviar email de verificacao para: $email ($username)");
                    $errors[] = 'Erro ao enviar email de verificação. Contacta o suporte.';
                }
            } catch (Exception $e) {
                error_log("Erro ao enviar email de verificacao: " . $e->getMessage());
                $errors[] = 'Erro ao enviar email de verificação. Contacta o suporte.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registar - FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    :root {
      --primary-blue: #3498db;
      --dark-blue: #2980b9;
      --light-bg: #f8f9fa;
    }
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      background: linear-gradient(135deg, #1e3c72 0%, #2c3e50 50%, #1e3c72 100%);
      background-attachment: fixed;
      min-height: 100vh;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      padding: 40px 0;
      position: relative;
      overflow-x: hidden;
      overflow-y: auto;
    }
    
    /* Background animado */
    .bg-animation {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 0;
      pointer-events: none;
    }
    
    .floating-shape {
      position: absolute;
      border-radius: 50%;
      background: rgba(52, 152, 219, 0.1);
      animation: float 20s infinite ease-in-out;
    }
    
    .shape1 {
      width: 300px;
      height: 300px;
      top: -100px;
      right: -100px;
      animation-delay: 0s;
    }
    
    .shape2 {
      width: 200px;
      height: 200px;
      bottom: -50px;
      left: -50px;
      animation-delay: 5s;
    }
    
    .shape3 {
      width: 150px;
      height: 150px;
      top: 40%;
      left: 10%;
      animation-delay: 2s;
    }
    
    .shape4 {
      width: 100px;
      height: 100px;
      bottom: 30%;
      right: 15%;
      animation-delay: 7s;
    }
    
    @keyframes float {
      0%, 100% {
        transform: translateY(0) rotate(0deg);
        opacity: 0.3;
      }
      50% {
        transform: translateY(-50px) rotate(180deg);
        opacity: 0.6;
      }
    }
    
    /* Partículas */
    .particle {
      position: absolute;
      width: 4px;
      height: 4px;
      background: rgba(52, 152, 219, 0.4);
      border-radius: 50%;
      animation: rise 15s infinite ease-in;
    }
    
    @keyframes rise {
      0% {
        transform: translateY(0) translateX(0);
        opacity: 0;
      }
      10% {
        opacity: 1;
      }
      90% {
        opacity: 1;
      }
      100% {
        transform: translateY(-100vh) translateX(50px);
        opacity: 0;
      }
    }
    
    .register-container {
      min-width: 320px;
      max-width: 450px;
      width: 100%;
      margin: 0 auto;
      padding: 20px;
      position: relative;
      z-index: 1;
      animation: slideIn 0.6s ease-out;
    }
    
    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .register-card {
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      overflow: hidden;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .register-header {
      background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
      padding: 40px 40px 30px;
      text-align: center;
      border-bottom: 1px solid #f0f0f0;
      position: relative;
      overflow: hidden;
    }
    
    .register-header::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(52, 152, 219, 0.05) 0%, transparent 70%);
      animation: pulse 4s infinite ease-in-out;
    }
    
    @keyframes pulse {
      0%, 100% {
        transform: scale(1);
        opacity: 0.5;
      }
      50% {
        transform: scale(1.1);
        opacity: 0.8;
      }
    }
    
    .logo-container {
      margin-bottom: 20px;
      position: relative;
      z-index: 1;
      animation: logoFloat 3s infinite ease-in-out;
    }
    
    .logo-container img {
      width: 120px;
      height: auto;
      filter: drop-shadow(0 4px 8px rgba(52, 152, 219, 0.2));
    }
    
    .register-header h1 {
      font-size: 28px;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 8px;
      position: relative;
      z-index: 1;
    }
    
    .register-header p {
      color: #7f8c8d;
      font-size: 15px;
      margin: 0;
      position: relative;
      z-index: 1;
    }
    
    .register-body {
      padding: 40px;
      position: relative;
    }
    
    .form-group {
      margin-bottom: 20px;
      animation: fadeInUp 0.6s ease-out backwards;
    }
    
    .form-group:nth-child(1) { animation-delay: 0.1s; }
    .form-group:nth-child(2) { animation-delay: 0.2s; }
    .form-group:nth-child(3) { animation-delay: 0.3s; }
    .form-group:nth-child(4) { animation-delay: 0.4s; }
    .form-group:nth-child(5) { animation-delay: 0.5s; }
    
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .form-label {
      font-weight: 600;
      color: #2c3e50;
      margin-bottom: 8px;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    
    .form-control {
      border: 2px solid #e9ecef;
      border-radius: 12px;
      padding: 12px 16px;
      font-size: 15px;
      transition: all 0.3s;
      background: white;
    }
    
    .form-control:focus {
      border-color: var(--primary-blue);
      box-shadow: none;
    }
    
    .btn-primary {
      background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
      border: none;
      border-radius: 12px;
      padding: 14px;
      font-weight: 600;
      font-size: 16px;
      transition: all 0.3s;
      width: 100%;
      position: relative;
      overflow: hidden;
    }
    
    .btn-primary::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
      transition: left 0.5s;
    }
    
    .btn-primary:hover::before {
      left: 100%;
    }
    
    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
    }
    
    .btn-primary:active {
      transform: translateY(-1px);
    }
    
    .alert {
      border-radius: 12px;
      border: none;
      padding: 12px 16px;
      animation: shake 0.5s;
    }
    
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-10px); }
      75% { transform: translateX(10px); }
    }
    
    .login-link {
      text-align: center;
      padding: 20px 40px 30px;
      background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
      border-top: 1px solid #e9ecef;
    }
    
    .login-link p {
      margin: 0;
      color: #7f8c8d;
    }
    
    .login-link a {
      color: var(--primary-blue);
      font-weight: 600;
      text-decoration: none;
      position: relative;
      transition: color 0.3s;
    }

    .login-link a::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      width: 0;
      height: 2px;
      background: var(--primary-blue);
      transition: width 0.3s;
    }

    .login-link a:hover {
      color: var(--dark-blue);
    }
    
    .login-link a:hover::after {
      width: 100%;
    }
    
    .input-group-text {
      background: #f8f9fa;
      border: 2px solid #e9ecef;
      border-right: none;
      border-radius: 12px 0 0 12px;
      color: var(--primary-blue);
      transition: all 0.3s;
    }
    
    .input-group .form-control {
      border-left: none;
      border-radius: 0 12px 12px 0;
    }
    
    .input-group:focus-within .input-group-text {
      border-color: var(--primary-blue);
    }
    
    .password-strength {
      height: 6px;
      background: #e9ecef;
      border-radius: 3px;
      margin-top: 10px;
      overflow: hidden;
      box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .password-strength-bar {
      height: 100%;
      width: 0;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      border-radius: 3px;
      position: relative;
    }
    
    .password-strength-bar::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
      animation: shimmer 2s infinite;
    }
    
    @keyframes shimmer {
      0% { transform: translateX(-100%); }
      100% { transform: translateX(100%); }
    }
    
    .strength-weak { 
      width: 33%; 
      background: linear-gradient(90deg, #e74c3c, #c0392b);
    }
    
    .strength-medium { 
      width: 66%; 
      background: linear-gradient(90deg, #f39c12, #e67e22);
    }
    
    .strength-strong {
      width: 100%;
      background: linear-gradient(90deg, var(--primary-blue), var(--dark-blue));
    }
    
    .password-hint {
      font-size: 12px;
      color: #95a5a6;
      margin-top: 6px;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    
    .password-hint i {
      font-size: 10px;
    }
    
    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: rgba(255, 255, 255, 0.9);
      text-decoration: none;
      padding: 10px 20px;
      border-radius: 10px;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      transition: all 0.3s;
    }
    
    .back-link:hover {
      background: rgba(255, 255, 255, 0.2);
      color: white;
      transform: translateX(-5px);
    }
    
    /* Responsivo */
    @media (max-width: 576px) {
      body {
        padding: 20px 0;
      }
      
      .register-container {
        padding: 15px;
      }
      
      .register-header, .register-body, .login-link {
        padding-left: 25px;
        padding-right: 25px;
      }
      
      .floating-shape {
        opacity: 0.5;
      }
    }
    
    @media (max-height: 800px) {
      body {
        padding: 30px 0;
      }
      
      .logo-container img {
        width: 100px;
      }
      
      .register-header {
        padding: 30px 40px 20px;
      }
      
      .register-body {
        padding: 30px;
      }
      
      .form-group {
        margin-bottom: 15px;
      }
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
</div>

<div class="register-container">
  <div class="register-card">
    <div class="register-header">
      <div class="logo-container">
        <img src="../assets/logo.png" alt="Freecard">
      </div>
      <h1>Cria a tua conta</h1>
      <p>Começa a gerir as tuas finanças hoje</p>
    </div>

    <div class="register-body">
      <?php if ($success): ?>
        <div class="alert alert-success mb-3" style="background: #d4edda;">
          <i class="bi bi-check-circle" style="color: #27ae60;"></i>
          <strong style="color: #27ae60;"><?= htmlspecialchars($successMessage) ?></strong>
        </div>
        <div class="text-center">
          <i class="bi bi-envelope-check" style="font-size: 60px; color: #3498db;"></i>
          <p class="mt-3" style="color: #7f8c8d;">
            Enviamos um email para <strong><?= htmlspecialchars($email) ?></strong>.<br>
            Clica no link de verificação para ativar a tua conta.
          </p>
          <p class="mt-2" style="color: #95a5a6; font-size: 14px;">
            Não recebeste o email? Verifica a pasta de spam.
          </p>
          <a href="login.php" class="btn btn-primary mt-3">
            <i class="bi bi-box-arrow-in-right me-2"></i>
            Ir para Login
          </a>
        </div>
      <?php else: ?>

      <?php if(!empty($errors)): ?>
        <div class="alert alert-danger mb-3">
          <i class="bi bi-exclamation-circle"></i>
          <ul class="mb-0 mt-2 ps-3">
            <?php foreach($errors as $e): ?>
              <li><?=htmlspecialchars($e)?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" novalidate>
        <div class="form-group">
          <label class="form-label">
            <i class="bi bi-person-circle"></i>
            Nome de utilizador
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input 
              type="text" 
              name="username" 
              class="form-control" 
              placeholder="Escolhe um nome de utilizador"
              value="<?=htmlspecialchars($username ?? '')?>" 
              required
              autofocus
            >
          </div>
          <div class="password-hint">
            <i class="bi bi-info-circle"></i>
            Mínimo 3 caracteres
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">
            <i class="bi bi-envelope-at"></i>
            Email
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input 
              type="email" 
              name="email" 
              class="form-control" 
              placeholder="O teu melhor email"
              value="<?=htmlspecialchars($email ?? '')?>" 
              required
            >
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">
            <i class="bi bi-shield-lock"></i>
            Palavra-passe
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input 
              type="password" 
              name="password" 
              class="form-control" 
              placeholder="Cria uma palavra-passe segura"
              id="password"
              required
            >
          </div>
          <div class="password-strength">
            <div class="password-strength-bar" id="strength-bar"></div>
          </div>
          <div class="password-hint">
            <i class="bi bi-info-circle"></i>
            Mínimo 8 caracteres, incluindo maiúsculas, minúsculas, números e especiais
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">
            <i class="bi bi-shield-check"></i>
            Confirmar palavra-passe
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input 
              type="password" 
              name="password2" 
              class="form-control" 
              placeholder="Repete a palavra-passe"
              required
            >
          </div>
        </div>

        <div class="form-group">
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-check-circle me-2"></i>
            Criar conta
          </button>
        </div>
      </form>
      <?php endif; ?>
    </div>

    <div class="login-link">
      <p>Já tens conta? <a href="login.php">Entra aqui</a></p>
    </div>
  </div>

  <div class="text-center mt-4">
    <a href="../index.php" class="back-link">
      <i class="bi bi-arrow-left"></i>
      <span>Voltar à página inicial</span>
    </a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Criar partículas animadas
function createParticles() {
  const container = document.querySelector('.bg-animation');
  const particleCount = 20;
  
  for (let i = 0; i < particleCount; i++) {
    const particle = document.createElement('div');
    particle.className = 'particle';
    particle.style.left = Math.random() * 100 + '%';
    particle.style.animationDelay = Math.random() * 15 + 's';
    particle.style.animationDuration = (15 + Math.random() * 10) + 's';
    container.appendChild(particle);
  }
}

createParticles();

// Password strength indicator melhorado
document.getElementById('password').addEventListener('input', function(e) {
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
</script>
</body>
</html>