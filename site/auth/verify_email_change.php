<?php
// site/auth/verify_email_change.php - Verificar alteração de email

session_start();
require_once __DIR__ . '/../../config/db.php';

$token = $_GET['token'] ?? '';
$status = 'invalid';
$message = '';

if ($token !== '') {
    // Procurar utilizador com este token
    $stmt = $pdo->prepare('
        SELECT id, username, email, pending_email, pending_email_expires
        FROM users
        WHERE pending_email_token = :token
        LIMIT 1
    ');
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if ($user) {
        // Verificar se o token expirou
        if (strtotime($user['pending_email_expires']) < time()) {
            $status = 'expired';
            $message = 'O link de verificação expirou. Por favor, solicita uma nova alteração.';

            // Limpar dados expirados
            $stmt = $pdo->prepare('
                UPDATE users
                SET pending_email = NULL, pending_email_token = NULL, pending_email_expires = NULL
                WHERE id = :id
            ');
            $stmt->execute([':id' => $user['id']]);
        } else {
            // Token válido - aplicar a alteração de email
            try {
                $stmt = $pdo->prepare('
                    UPDATE users
                    SET email = :new_email,
                        last_email_change = NOW(),
                        pending_email = NULL,
                        pending_email_token = NULL,
                        pending_email_expires = NULL
                    WHERE id = :id
                ');
                $stmt->execute([
                    ':new_email' => $user['pending_email'],
                    ':id' => $user['id']
                ]);

                $status = 'success';
                $message = 'Email alterado com sucesso!';
            } catch (PDOException $e) {
                $status = 'error';
                $message = 'Erro ao alterar email. Por favor, tenta novamente.';
                error_log("Erro ao alterar email: " . $e->getMessage());
            }
        }
    } else {
        $status = 'invalid';
        $message = 'Link de verificação inválido ou já utilizado.';
    }
}
?>
<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verificar Alteração de Email - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    :root {
      --primary-blue: #3498db;
      --dark-blue: #2980b9;
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

    .shape1 { width: 300px; height: 300px; top: -100px; left: -100px; animation-delay: 0s; }
    .shape2 { width: 200px; height: 200px; bottom: -50px; right: -50px; animation-delay: 5s; }
    .shape3 { width: 150px; height: 150px; top: 50%; right: 10%; animation-delay: 2s; }

    @keyframes float {
      0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.3; }
      50% { transform: translateY(-50px) rotate(180deg); opacity: 0.6; }
    }

    .verify-container {
      min-width: 320px;
      max-width: 500px;
      width: 100%;
      margin: 0 auto;
      padding: 20px;
      position: relative;
      z-index: 1;
      animation: slideIn 0.6s ease-out;
    }

    @keyframes slideIn {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .verify-card {
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      overflow: hidden;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .verify-header {
      background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
      padding: 40px 40px 30px;
      text-align: center;
      border-bottom: 1px solid #f0f0f0;
    }

    .logo-container {
      margin-bottom: 20px;
    }

    .logo-container img {
      width: 120px;
      height: auto;
      filter: drop-shadow(0 4px 8px rgba(52, 152, 219, 0.2));
    }

    .verify-header h1 {
      font-size: 26px;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 8px;
    }

    .verify-header p {
      color: #7f8c8d;
      font-size: 15px;
      margin: 0;
    }

    .verify-body {
      padding: 40px;
      text-align: center;
    }

    .status-icon {
      font-size: 80px;
      margin-bottom: 20px;
    }

    .status-icon.success { color: #27ae60; }
    .status-icon.error { color: #e74c3c; }
    .status-icon.warning { color: #f39c12; }

    .status-message {
      font-size: 18px;
      color: #2c3e50;
      margin-bottom: 10px;
      font-weight: 600;
    }

    .status-description {
      color: #7f8c8d;
      margin-bottom: 25px;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
      border: none;
      border-radius: 12px;
      padding: 14px 30px;
      font-weight: 600;
      font-size: 16px;
      transition: all 0.3s;
    }

    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
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
      body { padding: 20px 0; }
      .verify-container { padding: 15px; }
      .verify-header, .verify-body { padding-left: 25px; padding-right: 25px; }
    }
  </style>
</head>
<body>

<div class="bg-animation">
  <div class="floating-shape shape1"></div>
  <div class="floating-shape shape2"></div>
  <div class="floating-shape shape3"></div>
</div>

<div class="verify-container">
  <div class="verify-card">
    <div class="verify-header">
      <div class="logo-container">
        <img src="../assets/logo2.png" alt="Finora">
      </div>
      <h1>Verificação de Email</h1>
      <p>Confirmação de alteração de email</p>
    </div>

    <div class="verify-body">
      <?php if ($status === 'success'): ?>
        <i class="bi bi-envelope-check status-icon success"></i>
        <div class="status-message">Email Alterado com Sucesso!</div>
        <p class="status-description">
          O teu email foi atualizado. Usa o novo email para fazer login.
        </p>
        <a href="login.php" class="btn btn-primary">
          <i class="bi bi-box-arrow-in-right me-2"></i>
          Ir para Login
        </a>

      <?php elseif ($status === 'expired'): ?>
        <i class="bi bi-clock-history status-icon warning"></i>
        <div class="status-message">Link Expirado</div>
        <p class="status-description">
          <?= htmlspecialchars($message) ?>
        </p>
        <a href="../pages/settings.php" class="btn btn-primary">
          <i class="bi bi-gear me-2"></i>
          Ir para Configurações
        </a>

      <?php else: ?>
        <i class="bi bi-x-circle status-icon error"></i>
        <div class="status-message">Link Inválido</div>
        <p class="status-description">
          <?= htmlspecialchars($message) ?>
        </p>
        <a href="../pages/settings.php" class="btn btn-primary">
          <i class="bi bi-gear me-2"></i>
          Ir para Configurações
        </a>
      <?php endif; ?>
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
