<?php
// site/auth/forgot_password.php - Página para solicitar reset de palavra-passe

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/EmailService.php';

$errors = [];
$success = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $errors[] = 'Por favor, preenche o campo de email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email inválido.';
    } else {
        // Procurar utilizador por email
        $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            // Gerar token de reset
            $resetToken = bin2hex(random_bytes(32));
            $tokenExpiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // 1 hora de validade

            // Guardar token na BD
            $update = $pdo->prepare('
                UPDATE users
                SET password_reset_token = :token, password_reset_expires = :expires
                WHERE id = :id
            ');
            $update->execute([
                ':token' => $resetToken,
                ':expires' => $tokenExpiry,
                ':id' => $user['id']
            ]);

            // Enviar email
            $emailService = new EmailService();
            if ($emailService->sendPasswordResetEmail($user['email'], $user['username'], $resetToken)) {
                $success = true;
                $message = 'Email de recuperação enviado com sucesso! Verifica a tua caixa de entrada.';
            } else {
                $errors[] = 'Erro ao enviar email. Tenta novamente.';
            }
        } else {
            // Por segurança, não revelar se o email existe ou não
            $success = true;
            $message = 'Se o email existir receberás um link de recuperação.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recuperar Palavra-passe - FreeCard</title>
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
    }

    .logo-container img {
      width: 120px;
      height: auto;
      filter: drop-shadow(0 4px 8px rgba(52, 152, 219, 0.2));
    }

    .login-header h1 {
      font-size: 26px;
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

    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
    }

    .alert {
      border-radius: 12px;
      border: none;
      padding: 12px 16px;
    }

    .alert-danger {
      background: #fdf2f2;
      color: #c0392b;
    }

    .alert-success {
      background: #f0fdf4;
      color: #27ae60;
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

    .form-links {
      text-align: center;
      padding: 20px 40px 30px;
      background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
      border-top: 1px solid #e9ecef;
    }

    .form-links p {
      margin: 8px 0;
      color: #7f8c8d;
    }

    .form-links a {
      color: var(--primary-blue);
      font-weight: 600;
      text-decoration: none;
      position: relative;
      transition: color 0.3s;
    }

    .form-links a:hover {
      color: var(--dark-blue);
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

    @media (max-width: 576px) {
      body {
        padding: 20px 0;
      }

      .login-container {
        padding: 15px;
      }

      .login-header, .login-body, .form-links {
        padding-left: 25px;
        padding-right: 25px;
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
</div>

<div class="login-container">
  <div class="login-card">
    <div class="login-header">
      <div class="logo-container">
        <img src="../assets/logo.png" alt="Freecard">
      </div>
      <h1>Recuperar Palavra-passe</h1>
      <p>Introduz o teu email para receber o link de recuperação</p>
    </div>

    <div class="login-body">
      <?php if ($success): ?>
        <div class="alert alert-success mb-3">
          <i class="bi bi-check-circle me-2"></i>
          <?= htmlspecialchars($message) ?>
        </div>
        <div class="text-center">
          <i class="bi bi-envelope-check" style="font-size: 60px; color: #3498db;"></i>
          <p class="mt-3" style="color: #7f8c8d;">
            Verifica a tua caixa de entrada e a pasta de spam.
          </p>
          <a href="login.php" class="btn btn-primary mt-3">
            <i class="bi bi-box-arrow-in-right me-2"></i>
            Voltar ao Login
          </a>
        </div>
      <?php else: ?>
        <?php if (!empty($errors)): ?>
          <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger mb-3">
              <i class="bi bi-exclamation-circle me-2"></i>
              <?= htmlspecialchars($error) ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <form method="POST" action="">
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
                placeholder="Introduz o teu email"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                required
                autofocus
              >
            </div>
          </div>

          <div class="form-group">
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-send me-2"></i>
              Enviar Link de Recuperação
            </button>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <div class="form-links">
      <p>Lembras-te da palavra-passe? <a href="login.php">Entra aqui</a></p>
      <p>Não tens conta? <a href="register.php">Regista-te</a></p>
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
</body>
</html>
