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

function validatePassword($password) {
    if (strlen($password) < 8) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[a-z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return false;
    return true;
}

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

// Auto-migração: adicionar coluna avatar_path se não existir
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL DEFAULT NULL");
} catch (PDOException $e) { /* coluna já existe */ }

// Auto-migração: adicionar coluna google_id se não existir
try { $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(128) NULL DEFAULT NULL"); } catch (PDOException $e) {}

// Auto-migração: remover índice UNIQUE de username (nome é apenas display name)
try {
    $pdo->exec("ALTER TABLE users DROP INDEX username");
} catch (PDOException $e) { /* índice já não existe */ }

// Diretório de avatares
$avatarDir = __DIR__ . '/../../site/assets/avatars/';
if (!is_dir($avatarDir)) @mkdir($avatarDir, 0755, true);

$stmt = $pdo->prepare("
    SELECT username, email, created_at, last_password_change,
           last_email_change, two_factor_enabled, pending_email, pending_email_expires,
           avatar_path, google_id
    FROM users WHERE id = :uid
");
$stmt->execute([':uid' => $uid]);
$user = $stmt->fetch();

$hasPendingEmail = false;
if ($user['pending_email'] && $user['pending_email_expires']) {
    if (strtotime($user['pending_email_expires']) > time()) {
        $hasPendingEmail = true;
    } else {
        $stmt = $pdo->prepare("UPDATE users SET pending_email = NULL, pending_email_token = NULL, pending_email_expires = NULL WHERE id = :uid");
        $stmt->execute([':uid' => $uid]);
        $user['pending_email'] = null;
    }
}

$canChangePassword = true;
$canChangeEmail = true;
$daysUntilPasswordChange = 0;
$daysUntilEmailChange = 0;

if ($user['last_password_change']) {
    $lastChange = new DateTime($user['last_password_change']);
    $now = new DateTime();
    $daysSince = $now->diff($lastChange)->days;
    if ($daysSince < 30) { $canChangePassword = false; $daysUntilPasswordChange = 30 - $daysSince; }
}
if ($user['last_email_change']) {
    $lastChange = new DateTime($user['last_email_change']);
    $now = new DateTime();
    $daysSince = $now->diff($lastChange)->days;
    if ($daysSince < 30) { $canChangeEmail = false; $daysUntilEmailChange = 30 - $daysSince; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_theme') {
        $theme = $_POST['theme'] ?? 'light';
        $notifications = isset($_POST['notifications']) ? 1 : 0;
        try {
            $stmt = $pdo->prepare("UPDATE user_settings SET theme = :theme, notifications = :notifications WHERE user_id = :uid");
            $stmt->execute([':theme' => $theme, ':notifications' => $notifications, ':uid' => $uid]);
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
        if (!$canChangePassword) $errors[] = "Só podes alterar a password novamente daqui a {$daysUntilPasswordChange} dia(s).";
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :uid");
        $stmt->execute([':uid' => $uid]);
        $userPass = $stmt->fetch();
        if (!password_verify($currentPassword, $userPass['password_hash'])) $errors[] = 'A password atual está incorreta.';
        if (!validatePassword($newPassword)) $errors[] = 'A nova password deve ter pelo menos 8 caracteres, incluindo maiúsculas, minúsculas, números e caracteres especiais.';
        if ($newPassword !== $confirmPassword) $errors[] = 'As passwords não coincidem.';
        if (password_verify($newPassword, $userPass['password_hash'])) $errors[] = 'A nova password não pode ser igual à atual.';
        if (empty($errors)) {
            try {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash, last_password_change = NOW() WHERE id = :uid");
                $stmt->execute([':hash' => $hash, ':uid' => $uid]);
                try { $emailService = new EmailService(); $emailService->sendPasswordChangedNotification($user['email'], $user['username']); } catch (Exception $e) {}
                $message = 'Password alterada com sucesso! Próxima alteração disponível em 30 dias.';
                $messageType = 'success';
                $user['last_password_change'] = date('Y-m-d H:i:s');
                $canChangePassword = false;
                $daysUntilPasswordChange = 30;
            } catch (PDOException $e) { $message = 'Erro ao alterar password.'; $messageType = 'danger'; }
        } else { $message = implode('<br>', $errors); $messageType = 'danger'; }
    }

    elseif ($action === 'change_email') {
        $currentPassword = $_POST['current_password_email'] ?? '';
        $newEmail = trim($_POST['new_email'] ?? '');
        $errors = [];
        if (!$canChangeEmail) $errors[] = "Só podes alterar o email novamente daqui a {$daysUntilEmailChange} dia(s).";
        if ($hasPendingEmail) $errors[] = "Já tens um pedido de alteração de email pendente.";
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :uid");
        $stmt->execute([':uid' => $uid]);
        $userPass = $stmt->fetch();
        if (!password_verify($currentPassword, $userPass['password_hash'])) $errors[] = 'A password está incorreta.';
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
        if ($newEmail === $user['email']) $errors[] = 'O novo email não pode ser igual ao atual.';
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :uid");
        $stmt->execute([':email' => $newEmail, ':uid' => $uid]);
        if ($stmt->fetch()) $errors[] = 'Este email já está em uso por outra conta.';
        if (empty($errors)) {
            try {
                $token = bin2hex(random_bytes(32));
                $tokenExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $stmt = $pdo->prepare("UPDATE users SET pending_email = :email, pending_email_token = :token, pending_email_expires = :expires WHERE id = :uid");
                $stmt->execute([':email' => $newEmail, ':token' => $token, ':expires' => $tokenExpiry, ':uid' => $uid]);
                $emailService = new EmailService();
                if ($emailService->sendEmailChangeVerification($newEmail, $user['username'], $token)) {
                    $message = 'Enviamos um email de confirmação para <strong>' . htmlspecialchars($newEmail) . '</strong>. Clica no link para confirmar a alteração.';
                    $messageType = 'success';
                    $hasPendingEmail = true;
                    $user['pending_email'] = $newEmail;
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET pending_email = NULL, pending_email_token = NULL, pending_email_expires = NULL WHERE id = :uid");
                    $stmt->execute([':uid' => $uid]);
                    $errors[] = 'Erro ao enviar email de verificação. Tenta novamente.';
                }
            } catch (Exception $e) { $message = 'Erro ao processar pedido.'; $messageType = 'danger'; }
        }
        if (!empty($errors)) { $message = implode('<br>', $errors); $messageType = 'danger'; }
    }

    elseif ($action === 'cancel_email_change') {
        $stmt = $pdo->prepare("UPDATE users SET pending_email = NULL, pending_email_token = NULL, pending_email_expires = NULL WHERE id = :uid");
        $stmt->execute([':uid' => $uid]);
        $hasPendingEmail = false;
        $user['pending_email'] = null;
        $message = 'Pedido de alteração de email cancelado.';
        $messageType = 'info';
    }

    elseif ($action === 'unlink_google') {
        try {
            $pdo->prepare("UPDATE users SET google_id = NULL WHERE id = :uid")->execute([':uid' => $uid]);
            $user['google_id'] = null;
            $message = 'Conta Google desvinculada com sucesso.';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Erro ao desvincular conta Google.';
            $messageType = 'danger';
        }
    }


    elseif ($action === 'change_username') {
        $newUsername = trim($_POST['new_username'] ?? '');
        $errors = [];
        if (strlen($newUsername) < 3)  $errors[] = 'O nome deve ter pelo menos 3 caracteres.';
        if (strlen($newUsername) > 50) $errors[] = 'O nome não pode ter mais de 50 caracteres.';
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET username = :u WHERE id = :uid");
                $stmt->execute([':u' => $newUsername, ':uid' => $uid]);
                $_SESSION['username'] = $newUsername;
                $user['username'] = $newUsername;
                $message = 'Nome atualizado com sucesso.';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Erro ao atualizar o nome. Tenta novamente.';
                $messageType = 'danger';
            }
        }
        if (!empty($errors)) { $message = implode('<br>', $errors); $messageType = 'danger'; }
    }

    elseif ($action === 'upload_avatar') {
        $file = $_FILES['avatar'] ?? null;
        $errors = [];
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Erro ao receber o ficheiro.';
        } elseif (!extension_loaded('gd')) {
            error_log('[settings] Upload de avatar sem a extensão GD');
            $errors[] = 'O servidor não consegue processar imagens neste momento.';
        } else {
            // Só aceitamos os formatos que a GD deste servidor consegue ler (o XAMPP não lê WebP)
            $readers = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng',
                        'image/gif'  => 'imagecreatefromgif',  'image/webp' => 'imagecreatefromwebp'];
            $readers = array_filter($readers, 'function_exists');
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $names = implode(', ', array_map(fn($m) => strtoupper(substr($m, 6)), array_keys($readers)));
            if (!isset($readers[$mime])) $errors[] = "Formato não suportado. Usa $names.";
            if ($file['size'] > 3 * 1024 * 1024) $errors[] = 'A imagem não pode ter mais de 3 MB.';
            if (!is_dir($avatarDir) || !is_writable($avatarDir)) {
                error_log("[settings] Pasta de avatares sem permissão de escrita: $avatarDir");
                $errors[] = 'Não foi possível guardar a imagem. Tenta mais tarde.';
            }
        }
        if (empty($errors)) {
            // Recortar ao centro e redimensionar para 300×300. Voltar a codificar a imagem
            // também descarta qualquer conteúdo escondido no ficheiro original.
            $src = @$readers[$mime]($file['tmp_name']);
            $saved = false;
            if ($src) {
                $srcW = imagesx($src); $srcH = imagesy($src);
                $size = 300;
                $minDim = min($srcW, $srcH);
                $dst = imagecreatetruecolor($size, $size);
                imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // fundo branco para PNG/GIF transparentes
                imagecopyresampled($dst, $src, 0, 0, intval(($srcW - $minDim) / 2), intval(($srcH - $minDim) / 2), $size, $size, $minDim, $minDim);
                $saved = imagejpeg($dst, $avatarDir . $uid . '.jpg', 88);
                imagedestroy($src); imagedestroy($dst);
            }
            if ($saved) {
                $avatarRel = 'assets/avatars/' . $uid . '.jpg';
                $stmt = $pdo->prepare("UPDATE users SET avatar_path = :av WHERE id = :uid");
                $stmt->execute([':av' => $avatarRel, ':uid' => $uid]);
                $user['avatar_path'] = $avatarRel;
                $message = 'Foto de perfil atualizada.';
                $messageType = 'success';
            } else {
                error_log('[settings] Falha ao ler ou gravar o avatar do utilizador ' . $uid);
                $message = 'Não foi possível processar a imagem. Experimenta outra.';
                $messageType = 'danger';
            }
        } else { $message = implode('<br>', array_map('htmlspecialchars', $errors)); $messageType = 'danger'; }
    }

    elseif ($action === 'remove_avatar') {
        foreach (glob($avatarDir . $uid . '.*') ?: [] as $oldPath) @unlink($oldPath);
        $stmt = $pdo->prepare("UPDATE users SET avatar_path = NULL WHERE id = :uid");
        $stmt->execute([':uid' => $uid]);
        $user['avatar_path'] = null;
        $message = 'Foto de perfil removida.';
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
  <title>Definições - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css?v=6">
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background-color: var(--bg-primary);
      color: var(--text-primary);
    }

    /* ===== LAYOUT CENTRADO ===== */
    .settings-wrapper {
      max-width: 700px;
      margin: 0 auto;
      padding: 0 16px 60px;
    }

    /* ===== CABEÇALHO DA PÁGINA ===== */
    .page-header {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 32px;
      padding-top: 8px;
    }
    .page-header-icon {
      width: 42px; height: 42px;
      border-radius: 12px;
      background: var(--bg-secondary);
      border: 1px solid var(--border-color);
      display: flex; align-items: center; justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
    }
    .page-header h2 {
      font-size: 20px;
      font-weight: 600;
      margin: 0 0 2px;
      color: var(--text-primary);
    }
    .page-header p {
      font-size: 13px;
      color: var(--text-secondary);
      margin: 0;
    }

    /* ===== GRUPOS ===== */
    .settings-group {
      margin-bottom: 24px;
    }
    .group-label {
      font-size: 11px;
      font-weight: 600;
      letter-spacing: .07em;
      text-transform: uppercase;
      color: var(--text-secondary);
      margin: 0 0 8px 4px;
    }

    /* ===== CARD CLEAN ===== */
    .settings-card {
      background: var(--bg-secondary);
      border: 1px solid var(--border-color);
      border-radius: 14px;
      overflow: hidden;
    }

    /* ===== ROWS ===== */
    .s-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      padding: 16px 20px;
      transition: background .15s;
    }
    .s-row:not(:last-child) {
      border-bottom: 1px solid var(--border-color);
    }
    .s-row-text {}
    .s-row-label {
      font-size: 14px;
      font-weight: 500;
      color: var(--text-primary);
      margin: 0 0 2px;
    }
    .s-row-sub {
      font-size: 12px;
      color: var(--text-secondary);
      margin: 0;
    }
    .s-row-action { flex-shrink: 0; }

    /* ===== AVATAR ===== */
    .avatar-wrap {
      position: relative;
      width: 64px; height: 64px;
      flex-shrink: 0;
    }
    .avatar-circle {
      width: 64px; height: 64px;
      border-radius: 50%;
      background: var(--fn-accent);
      color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 24px; font-weight: 600;
    }
    .avatar-img {
      width: 64px; height: 64px;
      border-radius: 50%;
      object-fit: cover;
      display: block;
      border: 2px solid var(--border-color);
    }
    .avatar-edit-btn {
      position: absolute;
      bottom: 0; right: 0;
      width: 22px; height: 22px;
      border-radius: 50%;
      background: var(--fn-accent);
      color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 10px;
      cursor: pointer;
      border: 2px solid var(--bg-secondary);
      transition: background .15s;
      line-height: 1;
    }
    .avatar-edit-btn:hover { background: var(--fn-accent-hover); }
    .avatar-preview-ring {
      box-shadow: 0 0 0 3px var(--fn-accent)44;
    }

    /* ===== BADGES ===== */
    .s-badge {
      font-size: 11px; font-weight: 600;
      padding: 3px 9px;
      border-radius: 20px;
      display: inline-flex; align-items: center; gap: 4px;
    }
    .s-badge-role { background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-secondary); }
    .s-badge-ok { background: rgba(16,185,129,.1); color: #059669; }
    .s-badge-locked { background: rgba(239,68,68,.1); color: #dc2626; }
    .s-badge-pending { background: rgba(245,158,11,.1); color: #d97706; }

    /* ===== BOTÕES LIMPOS ===== */
    .btn-s {
      font-size: 13px; font-weight: 500;
      padding: 7px 16px;
      border-radius: 9px;
      border: 1px solid var(--border-color);
      background: var(--bg-primary);
      color: var(--text-primary);
      cursor: pointer;
      transition: all .15s;
      text-decoration: none;
      display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-s:hover { border-color: #999; background: var(--bg-hover); }
    .btn-s-primary {
      background: var(--fn-accent); color: #fff;
      border-color: var(--fn-accent);
    }
    .btn-s-primary:hover { background: var(--fn-accent-hover); color: #fff; border-color: var(--fn-accent-hover); }
    .btn-s-danger { border-color: #dc2626; color: #dc2626; }
    .btn-s-danger:hover { background: rgba(239,68,68,.07); }
    .btn-s:disabled { opacity: .45; cursor: not-allowed; pointer-events: none; }

    /* ===== TEMA SELECTOR ===== */
    .theme-options { display: flex; gap: 8px; }
    .theme-opt {
      display: flex; align-items: center; gap: 7px;
      padding: 7px 14px;
      border-radius: 9px;
      border: 1px solid var(--border-color);
      cursor: pointer;
      font-size: 13px; font-weight: 500;
      color: var(--text-primary);
      background: transparent;
      transition: all .15s;
    }
    .theme-opt input { display: none; }
    .theme-opt.active {
      border-width: 2px;
      border-color: var(--fn-accent);
      background: var(--bg-primary);
    }
    [data-theme="dark"] .theme-opt.active { border-color: var(--fn-accent); }

    /* ===== SWITCH ===== */
    .s-switch {
      width: 44px; height: 24px;
      border-radius: 12px; border: none;
      cursor: pointer; position: relative;
      transition: background .2s; flex-shrink: 0; padding: 0;
    }
    .s-switch-thumb {
      position: absolute; top: 3px;
      width: 18px; height: 18px; border-radius: 50%;
      background: #fff; transition: left .2s;
      box-shadow: 0 1px 3px rgba(0,0,0,.2);
    }

    /* ===== FORMS ===== */
    .s-form { padding: 0 20px 20px; }
    .s-form .form-label {
      font-size: 12px; font-weight: 600; color: var(--text-secondary);
      text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px;
    }
    .s-form .form-control {
      font-size: 14px;
      border: 1px solid var(--border-color);
      border-radius: 9px;
      padding: 10px 14px;
      background: var(--bg-primary);
      color: var(--text-primary);
      transition: border-color .15s;
    }
    .s-form .form-control:focus {
      border-color: var(--fn-accent);
      box-shadow: none;
      background: var(--bg-primary);
      color: var(--text-primary);
    }
    [data-theme="dark"] .s-form .form-control:focus { border-color: #fff; }
    .s-info-box {
      background: var(--bg-primary);
      border: 1px solid var(--border-color);
      border-radius: 9px;
      padding: 12px 14px;
      font-size: 12px;
      color: var(--text-secondary);
    }
    .s-info-box.warning {
      background: rgba(245,158,11,.07);
      border-color: rgba(245,158,11,.3);
      color: #92400e;
    }
    [data-theme="dark"] .s-info-box.warning { color: #fbbf24; }

    /* ===== PASSWORD STRENGTH ===== */
    .pw-strength { height: 4px; background: var(--border-color); border-radius: 2px; margin-top: 8px; overflow: hidden; }
    .pw-bar { height: 100%; width: 0; transition: all .3s; border-radius: 2px; }
    .pw-weak { width: 33%; background: #ef4444; }
    .pw-medium { width: 66%; background: #f59e0b; }
    .pw-strong { width: 100%; background: #10b981; }

    /* ===== ALERT ===== */
    .s-alert {
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 13px;
      margin-bottom: 20px;
      display: flex; align-items: flex-start; gap: 10px;
      border: 1px solid transparent;
    }
    .s-alert-success { background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.3); color: #065f46; }
    .s-alert-danger  { background: rgba(239,68,68,.1);  border-color: rgba(239,68,68,.3);  color: #7f1d1d; }
    .s-alert-info    { background: rgba(59,130,246,.1); border-color: rgba(59,130,246,.3); color: #1e3a8a; }
    [data-theme="dark"] .s-alert-success { color: #6ee7b7; }
    [data-theme="dark"] .s-alert-danger  { color: #fca5a5; }
    [data-theme="dark"] .s-alert-info    { color: #93c5fd; }

    /* ===== CONTA INFO ===== */
    .account-grid {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 0;
    }
    .account-cell {
      padding: 14px 20px;
      border-bottom: 1px solid var(--border-color);
    }
    .account-cell:nth-child(odd) { border-right: 1px solid var(--border-color); }
    .account-cell:nth-last-child(-n+2) { border-bottom: none; }
    .account-cell .cell-label { font-size: 11px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 3px; }
    .account-cell .cell-value { font-size: 14px; font-weight: 500; color: var(--text-primary); }

    @media (max-width: 600px) {
      .account-grid { grid-template-columns: 1fr; }
      .account-cell:nth-child(odd) { border-right: none; }
      .account-cell:nth-last-child(-n+2) { border-bottom: 1px solid var(--border-color); }
      .account-cell:last-child { border-bottom: none; }
      .theme-options { flex-direction: column; }
      .s-row { flex-wrap: wrap; }
    }

    .linked-row { display: flex; align-items: center; gap: 14px; padding: 16px 20px; }
    .linked-row:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .linked-icon { width: 36px; height: 36px; border-radius: 10px; background: var(--bg-primary); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .linked-info { flex: 1; min-width: 0; }
    .linked-name { font-size: 14px; font-weight: 500; color: var(--text-primary); margin: 0 0 2px; }
    .linked-sub { font-size: 12px; color: var(--text-secondary); margin: 0; }
  </style>
  <link rel="stylesheet" href="../css/finora.css?v=6">
</head>
<body>
<div class="app-layout">
<?php
$activePage    = 'settings';
$sidebarPrefix = '../';
include __DIR__ . '/../components/sidebar.php';
?>
<main class="main-content">
<div class="page-content">
<div class="settings-wrapper">

  <!-- CABEÇALHO -->
  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-gear"></i></div>
    <div>
      <h2>Definições</h2>
      <p>Personalize a sua experiência Finora</p>
    </div>
  </div>

  <!-- ALERTA -->
  <?php if ($message): ?>
    <?php $icon = $messageType === 'success' ? 'check-circle' : ($messageType === 'danger' ? 'exclamation-circle' : 'info-circle'); ?>
    <div class="s-alert s-alert-<?=$messageType?>">
      <i class="bi bi-<?=$icon?>" style="flex-shrink:0;margin-top:1px"></i>
      <span><?=$message?></span>
    </div>
  <?php endif; ?>

  <!-- PERFIL -->
  <div class="settings-group">
    <p class="group-label">Perfil</p>
    <div class="settings-card">
      <!-- Foto de perfil -->
      <div class="s-row" style="flex-wrap:wrap;gap:16px">
        <div style="display:flex;align-items:center;gap:14px;flex:1;min-width:0">
          <div class="avatar-wrap" id="avatarWrap">
            <?php if (!empty($user['avatar_path'])): ?>
              <img src="../<?=htmlspecialchars($user['avatar_path'])?>?v=<?=time()?>"
                   alt="avatar" class="avatar-img" id="avatarImg">
            <?php else: ?>
              <div class="avatar-circle" id="avatarLetter"><?=strtoupper(substr($user['username'],0,1))?></div>
            <?php endif; ?>
            <label for="avatarInput" class="avatar-edit-btn" title="Alterar foto">
              <i class="bi bi-camera-fill"></i>
            </label>
          </div>
          <div style="min-width:0">
            <p class="s-row-label"><?=htmlspecialchars($user['username'])?></p>
            <p class="s-row-sub"><?=htmlspecialchars($user['email'])?></p>
            <span class="s-badge s-badge-role" style="margin-top:4px">Membro</span>
          </div>
        </div>
        <div style="display:flex;gap:8px;flex-shrink:0;align-items:center">
          <form method="post" enctype="multipart/form-data" id="avatarForm"><?= csrf_field() ?>
            <input type="hidden" name="action" value="upload_avatar">
            <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/gif" style="display:none">
          </form>
          <?php if (!empty($user['avatar_path'])): ?>
          <form method="post" id="removeAvatarForm"><?= csrf_field() ?>
            <input type="hidden" name="action" value="remove_avatar">
            <button type="submit" class="btn-s btn-s-danger"
                    onclick="return confirm('Remover foto de perfil?')">
              <i class="bi bi-trash3"></i> Remover foto de perfil
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <!-- Alterar Nome -->
      <div class="s-row" style="align-items:flex-start">
        <div class="s-row-text">
          <p class="s-row-label">Nome</p>
          <p class="s-row-sub"><?=htmlspecialchars($user['username'])?></p>
        </div>
        <button class="btn-s" data-bs-toggle="collapse" data-bs-target="#formUsername">
          <i class="bi bi-pencil"></i> Alterar
        </button>
      </div>
      <div class="collapse" id="formUsername">
        <div class="s-form">
          <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="action" value="change_username">
            <div class="mb-3">
              <label class="form-label">Novo nome</label>
              <input type="text" name="new_username" class="form-control"
                     placeholder="ex: João Silva"
                     value="<?=htmlspecialchars($user['username'])?>"
                     minlength="3" maxlength="50" required>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end">
              <button type="button" class="btn-s" data-bs-toggle="collapse" data-bs-target="#formUsername">Cancelar</button>
              <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-check-circle"></i> Guardar</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Alterar Password -->
      <div class="s-row" style="align-items:flex-start">
        <div class="s-row-text">
          <p class="s-row-label">
            Palavra-passe
            &nbsp;
            <?php if ($canChangePassword): ?>
              <span class="s-badge s-badge-ok"><i class="bi bi-check-circle" style="font-size:10px"></i> Disponível</span>
            <?php else: ?>
              <span class="s-badge s-badge-locked"><i class="bi bi-lock" style="font-size:10px"></i> Bloqueado <?=$daysUntilPasswordChange?> dia(s)</span>
            <?php endif; ?>
          </p>
          <p class="s-row-sub">
            Última alteração:
            <?= $user['last_password_change'] ? date('d/m/Y', strtotime($user['last_password_change'])) : 'Nunca' ?>
          </p>
        </div>
        <button
          class="btn-s <?=!$canChangePassword?'':''?>"
          data-bs-toggle="collapse"
          data-bs-target="#formPassword"
          <?=!$canChangePassword?'disabled':''?>
        ><i class="bi bi-pencil"></i> Alterar</button>
      </div>
      <div class="collapse" id="formPassword">
        <div class="s-form">
          <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="mb-3">
              <label class="form-label">Password atual</label>
              <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Nova password</label>
              <input type="password" name="new_password" id="new_password" class="form-control" placeholder="••••••••" required>
              <div class="pw-strength"><div class="pw-bar" id="pw-bar"></div></div>
              <small style="font-size:11px;color:var(--text-secondary)">Mínimo 8 caracteres — maiúsculas, minúsculas, números e especiais</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Confirmar nova password</label>
              <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
            </div>
            <div class="s-info-box mb-3">
              <i class="bi bi-info-circle"></i> Por segurança, só podes alterar a password uma vez a cada 30 dias.
            </div>
            <div style="display:flex;gap:8px">
              <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-check-circle"></i> Guardar</button>
              <button type="button" class="btn-s" data-bs-toggle="collapse" data-bs-target="#formPassword">Cancelar</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Alterar Email -->
      <div class="s-row" style="align-items:flex-start">
        <div class="s-row-text">
          <p class="s-row-label">
            Email
            &nbsp;
            <?php if ($hasPendingEmail): ?>
              <span class="s-badge s-badge-pending"><i class="bi bi-clock-history" style="font-size:10px"></i> Pendente</span>
            <?php elseif ($canChangeEmail): ?>
              <span class="s-badge s-badge-ok"><i class="bi bi-check-circle" style="font-size:10px"></i> Disponível</span>
            <?php else: ?>
              <span class="s-badge s-badge-locked"><i class="bi bi-lock" style="font-size:10px"></i> Bloqueado <?=$daysUntilEmailChange?> dia(s)</span>
            <?php endif; ?>
          </p>
          <p class="s-row-sub">
            <?=htmlspecialchars($user['email'])?>
            <?php if ($hasPendingEmail): ?>
              → <strong><?=htmlspecialchars($user['pending_email'])?></strong>
            <?php endif; ?>
          </p>
        </div>
        <?php if (!$hasPendingEmail): ?>
          <button class="btn-s" data-bs-toggle="collapse" data-bs-target="#formEmail" <?=!$canChangeEmail?'disabled':''?>>
            <i class="bi bi-pencil"></i> Alterar
          </button>
        <?php endif; ?>
      </div>
      <?php if ($hasPendingEmail): ?>
        <div class="s-form">
          <div class="s-info-box warning mb-3">
            <i class="bi bi-envelope-exclamation"></i>
            Enviamos um email para <strong><?=htmlspecialchars($user['pending_email'])?></strong>. Clica no link para confirmar. O link expira em 1 hora.
          </div>
          <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel_email_change">
            <button type="submit" class="btn-s"><i class="bi bi-x-circle"></i> Cancelar pedido</button>
          </form>
        </div>
      <?php else: ?>
        <div class="collapse" id="formEmail">
          <div class="s-form">
            <form method="post"><?= csrf_field() ?>
              <input type="hidden" name="action" value="change_email">
              <div class="mb-3">
                <label class="form-label">Password (para confirmar)</label>
                <input type="password" name="current_password_email" class="form-control" placeholder="••••••••" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Novo email</label>
                <input type="email" name="new_email" class="form-control" placeholder="novo@email.com" required>
              </div>
              <div class="s-info-box mb-3">
                <i class="bi bi-info-circle"></i> Vais receber um email no novo endereço para confirmar a alteração.
              </div>
              <div style="display:flex;gap:8px">
                <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-envelope"></i> Enviar verificação</button>
                <button type="button" class="btn-s" data-bs-toggle="collapse" data-bs-target="#formEmail">Cancelar</button>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- APARÊNCIA -->
  <div class="settings-group">
    <p class="group-label">Aparência</p>
    <div class="settings-card">
      <form method="post"><?= csrf_field() ?>
        <input type="hidden" name="action" value="update_theme">

        <div class="s-row">
          <div class="s-row-text">
            <p class="s-row-label">Tema</p>
            <p class="s-row-sub">Escolhe entre o tema claro e escuro</p>
          </div>
          <div class="theme-options">
            <label class="theme-opt <?=$currentTheme==='light'?'active':''?>" id="opt-light">
              <input type="radio" name="theme" value="light" <?=$currentTheme==='light'?'checked':''?>>
              ☀ Claro
            </label>
            <label class="theme-opt <?=$currentTheme==='dark'?'active':''?>" id="opt-dark">
              <input type="radio" name="theme" value="dark" <?=$currentTheme==='dark'?'checked':''?>>
              ☾ Escuro
            </label>
          </div>
        </div>

        <div class="s-row">
          <div class="s-row-text">
            <p class="s-row-label">Notificações</p>
            <p class="s-row-sub">Alertas quando se aproxima dos limites dos cartões</p>
          </div>
          <label style="display:flex;align-items:center;cursor:pointer;gap:8px">
            <div style="position:relative">
              <input type="checkbox" name="notifications" id="notif-cb" <?=$settings['notifications']?'checked':''?> style="display:none">
              <button type="button" class="s-switch" id="notif-toggle"
                style="background:<?=$settings['notifications']?'var(--fn-accent)':'var(--border-color)'?>">
                <span class="s-switch-thumb" style="left:<?=$settings['notifications']?'23px':'3px'?>"></span>
              </button>
            </div>
          </label>
        </div>

        <div style="padding:12px 20px;border-top:1px solid var(--border-color)">
          <button type="submit" class="btn-s btn-s-primary">
            <i class="bi bi-check-circle"></i> Guardar alterações
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- CONTA -->
  <div class="settings-group">
    <p class="group-label">Informações da conta</p>
    <div class="settings-card">
      <div class="account-grid">
        <div class="account-cell">
          <p class="cell-label">Nome de utilizador</p>
          <p class="cell-value"><?=htmlspecialchars($user['username'])?></p>
        </div>
        <div class="account-cell">
          <p class="cell-label">Email</p>
          <p class="cell-value" style="font-size:13px"><?=htmlspecialchars($user['email'])?></p>
        </div>
        <div class="account-cell">
          <p class="cell-label">Membro desde</p>
          <p class="cell-value"><?=date('d/m/Y', strtotime($user['created_at']))?></p>
        </div>
        <div class="account-cell">
          <p class="cell-label">Versão</p>
          <p class="cell-value">Finora 2.0.0</p>
        </div>
      </div>
    </div>
  </div>

  <!-- CONTAS VINCULADAS -->
  <div class="settings-group">
    <p class="group-label"><i class="bi bi-link-45deg me-1"></i>Contas vinculadas</p>
    <div class="settings-card">
      <div class="linked-row">
        <div class="linked-icon">
          <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.964 10.707A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.707V4.961H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.039l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.961L3.964 6.293C4.672 4.169 6.656 3.58 9 3.58z"/></svg>
        </div>
        <div class="linked-info">
          <p class="linked-name">Google</p>
          <?php if (!empty($user['google_id'])): ?>
            <p class="linked-sub"><span class="s-badge s-badge-active" style="background:rgba(34,197,94,.1);color:#16a34a;border:none"><i class="bi bi-check-circle" style="font-size:10px"></i> Vinculada</span></p>
          <?php else: ?>
            <p class="linked-sub">Não vinculada — podes usar o Google para entrar</p>
          <?php endif; ?>
        </div>
        <?php if (!empty($user['google_id'])): ?>
          <form method="post" onsubmit="return confirm('Tens a certeza que queres desvincular a conta Google?')"><?= csrf_field() ?>
            <input type="hidden" name="action" value="unlink_google">
            <button class="btn-s btn-s-danger" style="font-size:12px;padding:5px 12px" type="submit">
              <i class="bi bi-x-circle"></i> Desvincular
            </button>
          </form>
        <?php else: ?>
          <a href="../auth/google_redirect.php?link=1" class="btn-s btn-s-primary" style="font-size:12px;padding:5px 12px">
            <i class="bi bi-link-45deg"></i> Vincular
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div><!-- /settings-wrapper -->
</div><!-- /page-content -->
</main>
</div><!-- /app-layout -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Tema — preview ao vivo
document.querySelectorAll('input[name="theme"]').forEach(r => {
  r.addEventListener('change', function() {
    document.documentElement.setAttribute('data-theme', this.value);
    document.querySelectorAll('.theme-opt').forEach(o => o.classList.remove('active'));
    this.closest('.theme-opt').classList.add('active');
  });
});

// Toggle notificações
var notifToggle = document.getElementById('notif-toggle');
var notifCb = document.getElementById('notif-cb');
if (notifToggle) {
  notifToggle.addEventListener('click', function() {
    var on = notifCb.checked = !notifCb.checked;
    this.style.background = on ? 'var(--fn-accent)' : 'var(--border-color)';
    this.querySelector('.s-switch-thumb').style.left = on ? '23px' : '3px';
  });
}

// Avatar — auto-submit ao escolher ficheiro
var avatarInput = document.getElementById('avatarInput');
if (avatarInput) {
  avatarInput.addEventListener('change', function() {
    if (!this.files[0]) return;
    var file = this.files[0];
    // Preview imediato
    var reader = new FileReader();
    reader.onload = function(e) {
      var wrap = document.getElementById('avatarWrap');
      var existing = wrap.querySelector('.avatar-img, .avatar-circle');
      var img = document.createElement('img');
      img.src = e.target.result;
      img.className = 'avatar-img avatar-preview-ring';
      img.id = 'avatarImg';
      if (existing) existing.replaceWith(img);
      else wrap.prepend(img);
    };
    reader.readAsDataURL(file);
    // Submit
    document.getElementById('avatarForm').submit();
  });
}

// Password strength
var pwInput = document.getElementById('new_password');
if (pwInput) {
  pwInput.addEventListener('input', function() {
    var pw = this.value, s = 0;
    if (pw.length >= 8) s++;
    if (/[A-Z]/.test(pw)) s++;
    if (/[a-z]/.test(pw)) s++;
    if (/[0-9]/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    var bar = document.getElementById('pw-bar');
    bar.className = 'pw-bar';
    if (s >= 4) bar.classList.add('pw-strong');
    else if (s >= 2) bar.classList.add('pw-medium');
    else if (s >= 1) bar.classList.add('pw-weak');
  });
}
</script>
</body>
</html>