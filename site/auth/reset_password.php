<?php
// site/auth/reset_password.php - Página para redefinir a palavra-passe

session_start();
require_once __DIR__ . '/../../config/db.php';

$errors = [];
$success = false;
$message = '';
$token = $_GET['token'] ?? '';
$user = null;
$status = 'invalid'; // 'invalid', 'expired', 'valid', 'success'

if ($token !== '') {
    // Procurar utilizador com este token
    $stmt = $pdo->prepare('
        SELECT id, username, email, password_reset_expires
        FROM users
        WHERE password_reset_token = :token
        LIMIT 1
    ');
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if ($user) {
        // Verificar se o token expirou
        if (strtotime($user['password_reset_expires']) < time()) {
            $status = 'expired';
            $message = 'O link de recuperação expirou. Por favor, solicita um novo.';
        } else {
            $status = 'valid';
        }
    } else {
        $status = 'invalid';
        $message = 'Link de recuperação inválido ou não encontrado.';
    }
}

// Processar o formulário de reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $status === 'valid') {
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Validar palavra-passe
    if (strlen($password) < 8) {
        $errors[] = 'A palavra-passe deve ter pelo menos 8 caracteres.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'A palavra-passe deve incluir pelo menos uma maiúscula.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'A palavra-passe deve incluir pelo menos uma minúscula.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'A palavra-passe deve incluir pelo menos um número.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'A palavra-passe deve incluir pelo menos um caractere especial (!@#$%^&*).';
    } elseif ($password !== $password2) {
        $errors[] = 'As palavras-passe não coincidem.';
    }

    if (empty($errors)) {
        // Atualizar palavra-passe na BD
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare('
            UPDATE users
            SET password_hash = :hash, password_reset_token = NULL, password_reset_expires = NULL, last_password_change = NOW()
            WHERE id = :id
        ');
        $update->execute([
            ':hash' => $hash,
            ':id' => $user['id']
        ]);

        $status = 'success';
        $message = 'Palavra-passe alterada com sucesso! Podes agora fazer login com a tua nova palavra-passe.';
        $success = true;
    }
}
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Redefinir Palavra-passe - FreeCard</title>
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
      max-width: 480px;
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

    .alert-warning {
      background: #fef9e7;
      color: #e67e22;
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

    .password-requirements {
      background: linear-gradient(135deg, rgba(52, 152, 219, 0.08) 0%, rgba(41, 128, 185, 0.06) 100%);
      border: 2px solid rgba(52, 152, 219, 0.2);
      padding: 18px;
      border-radius: 12px;
      margin-bottom: 25px;
      font-size: 14px;
    }

    .password-requirements strong {
      color: var(--dark-blue);
      font-weight: 700;
      display: block;
      margin-bottom: 12px;
    }

    .requirement {
      margin: 8px 0;
      color: #666;
      display: flex;
      align-items: center;
      transition: all 0.3s ease;
    }

    .requirement i {
      margin-right: 8px;
      color: var(--primary-blue);
      width: 20px;
    }

    .requirement.valid {
      color: #27ae60;
    }

    .requirement.valid i {
      color: #27ae60;
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
      <h1>Nova Palavra-passe</h1>
      <p>Define uma nova palavra-passe segura</p>
    </div>

    <div class="login-body">
      <?php if ($status === 'invalid' || $status === 'expired'): ?>
        <div class="alert alert-warning mb-3">
          <i class="bi bi-exclamation-triangle me-2"></i>
          <?= htmlspecialchars($message) ?>
        </div>
        <div class="text-center">
          <i class="bi bi-link-45deg" style="font-size: 60px; color: #f39c12;"></i>
          <p class="mt-3" style="color: #7f8c8d;">
            O link que utilizaste não é válido ou já expirou.
          </p>
          <a href="forgot_password.php" class="btn btn-primary mt-3">
            <i class="bi bi-arrow-repeat me-2"></i>
            Solicitar novo link
          </a>
        </div>

      <?php elseif ($status === 'success'): ?>
        <div class="alert alert-success mb-3">
          <i class="bi bi-check-circle me-2"></i>
          <?= htmlspecialchars($message) ?>
        </div>
        <div class="text-center">
          <i class="bi bi-shield-check" style="font-size: 60px; color: #27ae60;"></i>
          <p class="mt-3" style="color: #7f8c8d;">
            A tua palavra-passe foi alterada com sucesso.
          </p>
          <a href="login.php" class="btn btn-primary mt-3">
            <i class="bi bi-box-arrow-in-right me-2"></i>
            Ir para Login
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

        <div class="password-requirements">
          <strong><i class="bi bi-list-check me-2"></i>Requisitos da Palavra-passe:</strong>
          <div class="requirement" data-requirement="length">
            <i class="bi bi-circle"></i> Mínimo 8 caracteres
          </div>
          <div class="requirement" data-requirement="uppercase">
            <i class="bi bi-circle"></i> Pelo menos uma maiúscula (A-Z)
          </div>
          <div class="requirement" data-requirement="lowercase">
            <i class="bi bi-circle"></i> Pelo menos uma minúscula (a-z)
          </div>
          <div class="requirement" data-requirement="number">
            <i class="bi bi-circle"></i> Pelo menos um número (0-9)
          </div>
          <div class="requirement" data-requirement="special">
            <i class="bi bi-circle"></i> Pelo menos um caractere especial (!@#$%^&*)
          </div>
        </div>

        <form method="POST" action="">
          <div class="form-group">
            <label class="form-label">
              <i class="bi bi-lock"></i>
              Nova Palavra-passe
            </label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-key"></i></span>
              <input
                type="password"
                name="password"
                id="password"
                class="form-control"
                placeholder="Introduz a nova palavra-passe"
                required
                autofocus
              >
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">
              <i class="bi bi-lock-fill"></i>
              Confirmar Palavra-passe
            </label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
              <input
                type="password"
                name="password2"
                id="password2"
                class="form-control"
                placeholder="Confirma a palavra-passe"
                required
              >
            </div>
          </div>

          <div class="form-group">
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-check-circle me-2"></i>
              Guardar Nova Palavra-passe
            </button>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <div class="form-links">
      <p>Lembras-te da palavra-passe? <a href="login.php">Entra aqui</a></p>
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
// Validação dinâmica de palavra-passe
const passwordInput = document.getElementById('password');
const requirements = {
  length: document.querySelector('[data-requirement="length"]'),
  uppercase: document.querySelector('[data-requirement="uppercase"]'),
  lowercase: document.querySelector('[data-requirement="lowercase"]'),
  number: document.querySelector('[data-requirement="number"]'),
  special: document.querySelector('[data-requirement="special"]')
};

if (passwordInput) {
  passwordInput.addEventListener('input', function() {
    const pwd = this.value;

    updateRequirement(requirements.length, pwd.length >= 8, 'Mínimo 8 caracteres');
    updateRequirement(requirements.uppercase, /[A-Z]/.test(pwd), 'Pelo menos uma maiúscula (A-Z)');
    updateRequirement(requirements.lowercase, /[a-z]/.test(pwd), 'Pelo menos uma minúscula (a-z)');
    updateRequirement(requirements.number, /[0-9]/.test(pwd), 'Pelo menos um número (0-9)');
    updateRequirement(requirements.special, /[^A-Za-z0-9]/.test(pwd), 'Pelo menos um caractere especial (!@#$%^&*)');
  });
}

function updateRequirement(element, isValid, text) {
  if (!element) return;

  if (isValid) {
    element.classList.add('valid');
    element.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + text;
  } else {
    element.classList.remove('valid');
    element.innerHTML = '<i class="bi bi-circle"></i> ' + text;
  }
}
</script>
</body>
</html>
