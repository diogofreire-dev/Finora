<?php
// site/auth/login.php
session_start();
require_once __DIR__ . '/../../config/db.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailOrUsername = trim($_POST['email_or_username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($emailOrUsername === '' || $password === '') {
        $errors[] = 'Preenche todos os campos.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password_hash, email_verified FROM users WHERE email = :e OR username = :u LIMIT 1');
        $stmt->execute([':e' => $emailOrUsername, ':u' => $emailOrUsername]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Verificar se o email foi confirmado
            if (!$user['email_verified']) {
                $errors[] = 'A tua conta ainda não foi verificada. Verifica o teu email para ativar a conta.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                session_regenerate_id(true);
                header('Location: ../dashboard.php');
                exit;
            }
        } else {
            $errors[] = 'Credenciais inválidas.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Entrar - FreeCard</title>
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
      left: -100px;
      animation-delay: 0s;
    }
    
    .shape2 {
      width: 200px;
      height: 200px;
      bottom: -50px;
      right: -50px;
      animation-delay: 5s;
    }
    
    .shape3 {
      width: 150px;
      height: 150px;
      top: 50%;
      right: 10%;
      animation-delay: 2s;
    }
    
    .shape4 {
      width: 100px;
      height: 100px;
      bottom: 20%;
      left: 15%;
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
    
    .login-container {
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
    
    .login-card {
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      overflow: hidden;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .login-header {
      background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
      padding: 40px 40px 30px;
      text-align: center;
      border-bottom: 1px solid #f0f0f0;
      position: relative;
      overflow: hidden;
    }
    
    .login-header::before {
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
    
    .login-header h1 {
      font-size: 28px;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 8px;
      position: relative;
      z-index: 1;
    }
    
    .login-header p {
      color: #7f8c8d;
      font-size: 15px;
      margin: 0;
      position: relative;
      z-index: 1;
    }
    
    .login-body {
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
    
    .register-link {
      text-align: center;
      padding: 20px 40px 30px;
      background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
      border-top: 1px solid #e9ecef;
    }
    
    .register-link p {
      margin: 0;
      color: #7f8c8d;
    }
    
    .register-link a {
      color: var(--primary-blue);
      font-weight: 600;
      text-decoration: none;
      position: relative;
      transition: color 0.3s;
    }

    .register-link a::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      width: 0;
      height: 2px;
      background: var(--primary-blue);
      transition: width 0.3s;
    }

    .register-link a:hover {
      color: var(--dark-blue);
    }

    .register-link a:hover::after {
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
      
      .login-container {
        padding: 15px;
      }
      
      .login-header, .login-body, .register-link {
        padding-left: 25px;
        padding-right: 25px;
      }
      
      .floating-shape {
        opacity: 0.5;
      }
    }
    
    @media (max-height: 700px) {
      body {
        padding: 30px 0;
      }
      
      .logo-container img {
        width: 100px;
      }
      
      .login-header {
        padding: 30px 40px 20px;
      }
      
      .login-body {
        padding: 30px;
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

<div class="login-container">
  <div class="login-card">
    <div class="login-header">
      <div class="logo-container">
        <img src="../assets/logo.png" alt="Freecard">
      </div>
      <h1>Bem-vindo de volta</h1>
      <p>Entra na tua conta para continuar</p>
    </div>

    <div class="login-body">
      <?php if(!empty($errors)): ?>
        <div class="alert alert-danger mb-3">
          <i class="bi bi-exclamation-circle"></i>
          <?php foreach($errors as $e): ?>
            <?=htmlspecialchars($e)?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post">
        <div class="form-group">
          <label class="form-label">
            <i class="bi bi-person-circle"></i>
            Email ou Nome de utilizador
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input 
              type="text" 
              name="email_or_username" 
              class="form-control" 
              placeholder="Introduz o teu email ou utilizador"
              value="<?=htmlspecialchars($emailOrUsername ?? '')?>" 
              required
              autofocus
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
              placeholder="Introduz a tua palavra-passe"
              required
            >
          </div>
        </div>

        <div class="form-group mb-2">
          <button class="btn btn-primary" type="submit">
            <i class="bi bi-box-arrow-in-right me-2"></i>
            Entrar
          </button>
        </div>

        <div class="text-center mt-3">
          <a href="forgot_password.php" style="color: var(--primary-blue); text-decoration: none; font-size: 14px;">
            <i class="bi bi-key me-1"></i>
            Esqueci-me da palavra-passe
          </a>
        </div>
      </form>
    </div>

    <div class="register-link">
      <p>Ainda não tens conta? <a href="register.php">Regista-te aqui</a></p>
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
</script>
</body>
</html>