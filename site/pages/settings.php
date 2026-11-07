<?php
// site/pages/settings.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/EmailService.php';
require_once __DIR__ . '/../helpers/theme_helper.php';
require_once __DIR__ . '/../helpers/password_rules.php';
require_once __DIR__ . '/../helpers/notifications.php';
$uid = $_SESSION['user_id'] ?? null;

// Iguais ao login: errar a palavra-passe aqui conta para o mesmo bloqueio,
// senão quem roubasse uma sessão podia adivinhar a palavra-passe nesta página sem limite.
const SETTINGS_MAX_ATTEMPTS = 5;
const SETTINGS_LOCK_MINUTES = 15;
const CHANGE_COOLDOWN_DAYS  = 30;


// Confirma a palavra-passe atual com limite de tentativas. Devolve null se estiver certa,
// ou a mensagem de erro a mostrar.
function check_current_password(PDO $pdo, int $uid, string $password): ?string {
    $st = $pdo->prepare("SELECT password_hash, GREATEST(TIMESTAMPDIFF(SECOND, NOW(), login_locked_until), 0) AS lock_seconds
                         FROM users WHERE id = :uid");
    $st->execute([':uid' => $uid]);
    $row = $st->fetch();
    if ((int) $row['lock_seconds'] > 0) {
        return 'Demasiadas tentativas erradas. Tenta outra vez daqui a ' . (int) ceil($row['lock_seconds'] / 60) . ' min.';
    }
    if (password_verify($password, $row['password_hash'])) {
        $pdo->prepare("UPDATE users SET failed_login_attempts = 0 WHERE id = :uid")->execute([':uid' => $uid]);
        return null;
    }
    $pdo->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = :uid")->execute([':uid' => $uid]);
    $pdo->prepare("UPDATE users SET login_locked_until = NOW() + INTERVAL " . SETTINGS_LOCK_MINUTES . " MINUTE, failed_login_attempts = 0
                   WHERE id = :uid AND failed_login_attempts >= :max")
        ->execute([':uid' => $uid, ':max' => SETTINGS_MAX_ATTEMPTS]);
    return t('A palavra-passe atual está errada.');
}

$stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = :uid");
$stmt->execute([':uid' => $uid]);
$settings = $stmt->fetch();
if (!$settings) {
    $pdo->prepare("INSERT INTO user_settings (user_id, language, onboarded) VALUES (:uid, :lang, 0)")->execute([':uid' => $uid, ':lang' => current_lang()]);
    $settings = ['theme' => 'light', 'notifications' => 1];
}

$avatarDir = __DIR__ . '/../assets/avatars/';

// As contas de tempo são feitas no MySQL (o PHP e o MySQL estão em fusos diferentes)
$stmt = $pdo->prepare("
    SELECT username, email, created_at, last_password_change, avatar_path, google_id, pending_email,
           pending_email_expires > NOW() AS email_pending,
           GREATEST(" . CHANGE_COOLDOWN_DAYS . " - DATEDIFF(NOW(), last_password_change), 0) AS pw_wait,
           GREATEST(" . CHANGE_COOLDOWN_DAYS . " - DATEDIFF(NOW(), last_email_change), 0)    AS email_wait
    FROM users WHERE id = :uid
");
$stmt->execute([':uid' => $uid]);
$user = $stmt->fetch();

$hasPendingEmail = (bool) $user['email_pending'];
if ($user['pending_email'] && !$hasPendingEmail) {
    $pdo->prepare("UPDATE users SET pending_email = NULL, pending_email_token = NULL, pending_email_expires = NULL WHERE id = :uid")
        ->execute([':uid' => $uid]);
}
$daysUntilPasswordChange = (int) $user['pw_wait'];
$daysUntilEmailChange    = (int) $user['email_wait'];
$canChangePassword = $daysUntilPasswordChange === 0;
$canChangeEmail    = $daysUntilEmailChange === 0;

// ── Ações (POST → guarda mensagem → redirect → GET) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $flash  = ['danger', t('Ação desconhecida.')];
    $open   = null; // formulário a reabrir quando há erro

    try {
        if ($action === 'update_theme') {
            $theme = valid_theme($_POST['theme'] ?? '');
            $pdo->prepare("UPDATE user_settings SET theme = :t WHERE user_id = :uid")->execute([':t' => $theme, ':uid' => $uid]);
            $flash = ['success', $theme === 'dark' ? 'Tema escuro ativado.' : 'Tema claro ativado.'];
        }

        elseif ($action === 'update_language') {
            $code = $_POST['language'] ?? '';
            if (isset(LANGS[$code])) {
                $pdo->prepare("UPDATE user_settings SET language = :l WHERE user_id = :uid")->execute([':l' => $code, ':uid' => $uid]);
                set_lang($code);
                remember_lang($code);   // o login e a landing também passam a aparecer nesta língua
                $flash = ['success', t('Língua alterada.')];
            } else {
                $flash = ['danger', t('Escolhe uma língua da lista.')];
            }
        }
        elseif ($action === 'update_income') {
            // Vazio = não dizer; senão um número entre 0 e 10 milhões (o mesmo limite das boas-vindas)
            $raw = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim((string) ($_POST['monthly_income'] ?? '')));
            if ($raw === '') {
                $pdo->prepare('UPDATE user_settings SET monthly_income = NULL WHERE user_id = :uid')->execute([':uid' => $uid]);
                $flash = ['success', t('Rendimento removido.')];
            } elseif (is_numeric($raw) && (float) $raw >= 0 && (float) $raw <= 10000000) {
                $pdo->prepare('UPDATE user_settings SET monthly_income = :i WHERE user_id = :uid')->execute([':i' => round((float) $raw, 2), ':uid' => $uid]);
                $flash = ['success', t('Rendimento guardado.')];
            } else {
                $flash = ['danger', t('O rendimento tem de ser um número entre 0 e 10 000 000, ou ficar vazio.')];
            }
        }
        elseif ($action === 'update_currency') {
            // Só códigos da lista CURRENCIES: um valor inventado no pedido é recusado, não guardado
            $code = $_POST['currency'] ?? '';
            if (isset(CURRENCIES[$code])) {
                $pdo->prepare("UPDATE user_settings SET currency = :c WHERE user_id = :uid")->execute([':c' => $code, ':uid' => $uid]);
                set_user_currency($code);
                $flash = ['success', t('A moeda passou a {name} ({symbol}).', ['name' => t(CURRENCIES[$code][1]), 'symbol' => CURRENCIES[$code][0]])];
            } else {
                $flash = ['danger', t('Escolhe uma moeda da lista.')];
            }
        }
        elseif ($action === 'update_notifications') {
            $on = isset($_POST['notifications']) ? 1 : 0;
            $pdo->prepare("UPDATE user_settings SET notifications = :n WHERE user_id = :uid")->execute([':n' => $on, ':uid' => $uid]);
            $flash = ['success', $on ? t('Notificações ligadas.') : t('Notificações desligadas.')];
            $open  = 'notificacoes';
        }

        elseif ($action === 'update_notif_prefs') {
            // Só os tipos conhecidos (lista fixa) — qualquer outra chave no pedido é ignorada
            $prefs = ['budget_email' => isset($_POST['budget_email'])];
            foreach (array_keys(NOTIF_TYPES) as $t) $prefs[$t] = isset($_POST['t'][$t]);
            $pdo->prepare("UPDATE user_settings SET notif_prefs = :p WHERE user_id = :uid")->execute([':p' => json_encode($prefs), ':uid' => $uid]);
            $flash = ['success', t('Preferências das notificações guardadas.')];
            $open  = 'notificacoes';
        }

        elseif ($action === 'change_username') {
            $newUsername = trim($_POST['new_username'] ?? '');
            $len = mb_strlen($newUsername);
            if ($len < 3 || $len > 50) {
                $flash = ['danger', t('O nome tem de ter entre 3 e 50 caracteres.')];
                $open  = 'formUsername';
            } else {
                $pdo->prepare("UPDATE users SET username = :u WHERE id = :uid")->execute([':u' => $newUsername, ':uid' => $uid]);
                $_SESSION['username'] = $newUsername;
                $flash = ['success', t('Nome atualizado.')];
            }
        }

        elseif ($action === 'change_password') {
            $newPassword = $_POST['new_password'] ?? '';
            $errors = [];
            if (!$canChangePassword) {
                $errors[] = t('Só podes voltar a mudar a palavra-passe daqui a {n} dia(s).', ['n' => $daysUntilPasswordChange]);
            } elseif ($err = check_current_password($pdo, $uid, $_POST['current_password'] ?? '')) {
                $errors[] = $err;
            } else {
                if ($msg = password_problem_message($newPassword)) $errors[] = $msg;
                if ($newPassword !== ($_POST['confirm_password'] ?? '')) $errors[] = t('As duas palavras-passe novas não são iguais.');
                if ($newPassword === ($_POST['current_password'] ?? '')) $errors[] = t('A nova palavra-passe tem de ser diferente da atual.');
            }
            if ($errors) {
                $flash = ['danger', implode("\n", $errors)];
                $open  = 'formPassword';
            } else {
                $pdo->prepare("UPDATE users SET password_hash = :h, last_password_change = NOW() WHERE id = :uid")
                    ->execute([':h' => password_hash($newPassword, PASSWORD_DEFAULT), ':uid' => $uid]);
                // Novo ID de sessão: um ID antigo que alguém tenha copiado deixa de servir
                session_regenerate_id(true);
                try { (new EmailService())->sendPasswordChangedNotification($user['email'], $user['username']); } catch (Exception $e) {}
                $flash = ['success', t('Palavra-passe alterada. Enviámos um aviso para o teu email.')];
            }
        }

        elseif ($action === 'change_email') {
            $newEmail = strtolower(trim($_POST['new_email'] ?? ''));
            $errors = [];
            if (!$canChangeEmail)  $errors[] = t('Só podes voltar a mudar o email daqui a {n} dia(s).', ['n' => $daysUntilEmailChange]);
            elseif ($hasPendingEmail) $errors[] = t('Já tens uma alteração de email à espera de confirmação.');
            elseif ($err = check_current_password($pdo, $uid, $_POST['current_password_email'] ?? '')) $errors[] = $err;
            else {
                if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) $errors[] = t('Esse email não é válido.');
                elseif ($newEmail === strtolower($user['email'])) $errors[] = t('Esse já é o teu email.');
                else {
                    $st = $pdo->prepare("SELECT 1 FROM users WHERE email = :e AND id != :uid");
                    $st->execute([':e' => $newEmail, ':uid' => $uid]);
                    if ($st->fetch()) $errors[] = t('Esse email já pertence a outra conta.');
                }
            }
            if (!$errors) {
                // O link leva o token; a base de dados guarda só o hash (como na recuperação de palavra-passe)
                $token = bin2hex(random_bytes(32));
                $pdo->prepare("UPDATE users SET pending_email = :e, pending_email_token = :t, pending_email_expires = NOW() + INTERVAL 1 HOUR WHERE id = :uid")
                    ->execute([':e' => $newEmail, ':t' => hash('sha256', $token), ':uid' => $uid]);
                if ((new EmailService())->sendEmailChangeVerification($newEmail, $user['username'], $token)) {
                    $flash = ['success', t('Enviámos um link de confirmação para {email}. Abre-o na próxima hora.', ['email' => $newEmail])];
                } else {
                    $pdo->prepare("UPDATE users SET pending_email = NULL, pending_email_token = NULL, pending_email_expires = NULL WHERE id = :uid")
                        ->execute([':uid' => $uid]);
                    $errors[] = t('Não foi possível enviar o email de confirmação. Tenta outra vez.');
                }
            }
            if ($errors) {
                $flash = ['danger', implode("\n", $errors)];
                $open  = 'formEmail';
            }
        }

        elseif ($action === 'cancel_email_change') {
            $pdo->prepare("UPDATE users SET pending_email = NULL, pending_email_token = NULL, pending_email_expires = NULL WHERE id = :uid")
                ->execute([':uid' => $uid]);
            $flash = ['info', t('Alteração de email cancelada.')];
        }

        elseif ($action === 'unlink_google') {
            $pdo->prepare("UPDATE users SET google_id = NULL WHERE id = :uid")->execute([':uid' => $uid]);
            $flash = ['success', t('Conta Google desligada. Passas a entrar com email e palavra-passe.')];
        }

        elseif ($action === 'upload_avatar') {
            $file = $_FILES['avatar'] ?? null;
            $errors = [];
            $readers = [];
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = t('Não recebemos a imagem. Tenta outra vez.');
            } elseif (!extension_loaded('gd')) {
                error_log('[settings] Upload de avatar sem a extensão GD');
                $errors[] = t('O servidor não consegue processar imagens neste momento.');
            } else {
                // Só aceitamos os formatos que a GD deste servidor consegue ler (o XAMPP não lê WebP)
                $readers = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng',
                            'image/gif'  => 'imagecreatefromgif',  'image/webp' => 'imagecreatefromwebp'];
                $readers = array_filter($readers, 'function_exists');
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                $names = implode(', ', array_map(fn($m) => strtoupper(substr($m, 6)), array_keys($readers)));
                if (!isset($readers[$mime])) $errors[] = t('Formato não suportado. Usa {formats}.', ['formats' => $names]);
                if ($file['size'] > 3 * 1024 * 1024) $errors[] = t('A imagem não pode ter mais de 3 MB.');
                if (!is_dir($avatarDir) || !is_writable($avatarDir)) {
                    error_log("[settings] Pasta de avatares sem permissão de escrita: $avatarDir");
                    $errors[] = t('Não foi possível guardar a imagem. Tenta mais tarde.');
                }
            }
            if ($errors) {
                $flash = ['danger', implode("\n", $errors)];
            } else {
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
                    $pdo->prepare("UPDATE users SET avatar_path = :av WHERE id = :uid")
                        ->execute([':av' => 'assets/avatars/' . $uid . '.jpg', ':uid' => $uid]);
                    $flash = ['success', t('Foto de perfil atualizada.')];
                } else {
                    error_log('[settings] Falha ao ler ou gravar o avatar do utilizador ' . $uid);
                    $flash = ['danger', t('Não foi possível processar a imagem. Experimenta outra.')];
                }
            }
        }

        elseif ($action === 'remove_avatar') {
            foreach (glob($avatarDir . $uid . '.*') ?: [] as $oldPath) @unlink($oldPath);
            $pdo->prepare("UPDATE users SET avatar_path = NULL WHERE id = :uid")->execute([':uid' => $uid]);
            $flash = ['info', t('Foto de perfil removida.')];
        }

        elseif ($action === 'delete_account') {
            // Apagar é irreversível: pede outra vez a palavra-passe (com o mesmo limite de tentativas),
            // para que uma sessão esquecida aberta ou roubada não chegue para apagar a conta.
            // Contas Google podem não ter palavra-passe que conheçam: aí confirmam escrevendo o email.
            $confirm = (string) ($_POST['confirm_delete'] ?? '');
            $err = null;
            if (empty($_POST['understand'])) {
                $err = t('Marca a caixa a confirmar que percebes que não dá para voltar atrás.');
            } elseif ($user['google_id']) {
                if (mb_strtolower(trim($confirm)) !== mb_strtolower($user['email'])) $err = t('O email não corresponde ao da tua conta.');
            } else {
                $err = check_current_password($pdo, $uid, $confirm);
            }
            if ($err) {
                $flash = ['danger', $err];
                $open  = 'apagar-conta';
            } else {
                // As outras tabelas têm ON DELETE CASCADE: apagar o utilizador apaga tudo o que é dele.
                // A foto fica fora da base de dados, por isso é apagada à mão.
                foreach (glob($avatarDir . (int) $uid . '.*') ?: [] as $f) @unlink($f);
                $pdo->prepare("DELETE FROM users WHERE id = :uid")->execute([':uid' => $uid]);
                error_log('[settings] Conta ' . (int) $uid . ' apagada pelo próprio utilizador');
                try { (new EmailService())->sendAccountDeletedNotification($user['email'], $user['username']); } catch (Exception $e) {}

                // Termina a sessão por completo (dados, cookie e ID)
                $_SESSION = [];
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
                session_destroy();
                header('Location: ../index.php?conta=apagada');
                exit;
            }
        }
    } catch (Exception $e) {
        error_log('[settings] ' . $e->getMessage());
        $flash = ['danger', t('Não foi possível guardar. Tenta outra vez.')];
    }

    $_SESSION['flash_settings'] = [$flash, $open];
    header('Location: settings.php' . (in_array($open, ['notificacoes', 'apagar-conta'], true) ? '#' . $open : ''));
    exit;
}

[[$messageType, $message], $openForm] = $_SESSION['flash_settings'] ?? [['', ''], null];
unset($_SESSION['flash_settings']);

$currentTheme = valid_theme($settings['theme'] ?? 'light');
$avatarFile   = $user['avatar_path'] ? __DIR__ . '/../' . $user['avatar_path'] : null;
$avatarUrl    = $avatarFile && is_file($avatarFile) ? '../' . $user['avatar_path'] . '?v=' . filemtime($avatarFile) : null;
$initial      = mb_strtoupper(mb_substr($user['username'], 0, 1));
$fmtDate      = fn($d) => date('d/m/Y', strtotime($d));
$months       = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$since        = strtotime($user['created_at']);
?>
<!doctype html>
<html lang="<?= html_lang() ?>" data-theme="<?= $currentTheme ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= te('Definições - Finora') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="../assets/apple-touch-icon.png">
  <link rel="stylesheet" href="../css/sidebar.css?v=7">
  <style>

    .st-flash { display: flex; gap: 10px; align-items: flex-start; padding: 12px 16px; border-radius: 12px; margin-bottom: 22px; font-size: 14px; border: 1px solid transparent; }
    .st-flash.success { background: #ecfdf3; border-color: #abefc6; color: #067647; }
    .st-flash.danger  { background: #fef3f2; border-color: #fecdca; color: #b42318; }
    .st-flash.info    { background: var(--fn-paper); border-color: var(--fn-line); color: var(--fn-text); }
    [data-theme="dark"] .st-flash.success { background: rgba(117,224,167,.1); border-color: rgba(117,224,167,.25); color: #75e0a7; }
    [data-theme="dark"] .st-flash.danger  { background: rgba(253,162,155,.1); border-color: rgba(253,162,155,.25); color: #fda29b; }

    /* Cada secção: título à esquerda, cartão à direita */

    .st-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 20px; }
    .st-card > * + * { border-top: 1px solid var(--fn-line); }
    .st-row .btn-s, .st-row form { flex-shrink: 0; }
    .st-currency { width: auto; min-width: 210px; flex-shrink: 0; }
    .st-label { font-size: 15px; font-weight: 600; color: var(--fn-text); margin: 0; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .st-sub { font-size: 14px; color: var(--fn-muted); margin: 2px 0 0; overflow-wrap: anywhere; }
    .st-sub b { color: var(--fn-text); font-weight: 600; }

    .st-tag { font-size: 12px; font-weight: 600; padding: 2px 9px; border-radius: 999px; display: inline-flex; align-items: center; gap: 4px; }
    .st-tag.ok      { background: #ecfdf3; color: #067647; }
    .st-tag.wait    { background: #fffaeb; color: #b54708; }
    .st-tag.locked  { background: var(--fn-paper); color: var(--fn-muted); }
    [data-theme="dark"] .st-tag.ok   { background: rgba(117,224,167,.12); color: #75e0a7; }
    [data-theme="dark"] .st-tag.wait { background: rgba(254,200,75,.12); color: #fedf89; }

    /* Perfil */
    .st-profile { display: flex; align-items: center; gap: 18px; padding: 22px 20px; }
    .st-avatar img, .st-avatar .letter { box-shadow: 0 0 0 3px var(--fn-surface); }
    .st-avatar { position: relative; width: 72px; height: 72px; flex-shrink: 0; }
    .st-avatar img, .st-avatar .letter { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; display: grid; place-items: center; }
    .st-avatar .letter { background: var(--fn-ink); color: var(--fn-gold); font-family: var(--fn-font-brand); font-size: 30px; font-weight: 700; }
    [data-theme="dark"] .st-avatar .letter { background: var(--fn-gold-soft); }
    .st-profile .who { flex: 1; min-width: 0; }
    .st-profile .who b { display: block; font-size: 18px; color: var(--fn-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .st-profile .acts { display: flex; gap: 8px; flex-wrap: wrap; }
    .st-profile .acts form { margin: 0; }

    /* Formulários que abrem dentro do cartão */
    .st-form { padding: 4px 20px 20px; border-top: 0 !important; }
    .st-form .form-label { font-size: 14px; font-weight: 600; margin-bottom: 6px; color: var(--fn-text); }
    .st-form .form-control { min-height: 44px; border-radius: 10px; border: 1px solid var(--fn-line); background: var(--fn-surface); color: var(--fn-text); }
    .st-form .form-control:focus { border-color: var(--fn-accent); box-shadow: 0 0 0 3px rgba(var(--fn-accent-rgb), .18); background: var(--fn-surface); color: var(--fn-text); }
    .st-form .hint { font-size: 13px; color: var(--fn-muted); margin-top: 6px; }
    .st-form .acts { display: flex; gap: 8px; justify-content: flex-end; }
    .st-form .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .pw-meter { display: grid; grid-template-columns: repeat(5, 1fr); gap: 4px; margin-top: 8px; }
    .pw-meter i { height: 4px; border-radius: 2px; background: var(--fn-line); transition: background .2s; }
    .pw-meter[data-score="1"] i:nth-child(-n+1),
    .pw-meter[data-score="2"] i:nth-child(-n+2) { background: #d92d20; }
    .pw-meter[data-score="3"] i:nth-child(-n+3),
    .pw-meter[data-score="4"] i:nth-child(-n+4) { background: #dc6803; }
    .pw-meter[data-score="5"] i { background: #079455; }

    /* Tema: miniaturas da app */
    .st-themes { display: grid; grid-template-columns: repeat(2, minmax(0, 220px)); gap: 16px; padding: 20px; }
    .st-theme { position: relative; margin: 0; cursor: pointer; }
    .st-theme input { position: absolute; opacity: 0; pointer-events: none; }
    .st-theme .mini { display: block; border-radius: 12px; border: 2px solid var(--fn-line); overflow: hidden; aspect-ratio: 16 / 10; transition: border-color .15s, box-shadow .15s; }
    .st-theme:hover .mini { border-color: var(--fn-muted); }
    .st-theme input:checked + .mini { border-color: var(--fn-accent); box-shadow: 0 0 0 3px rgba(var(--fn-accent-rgb), .2); }
    .st-theme input:focus-visible + .mini { outline: 2px solid var(--fn-gold); outline-offset: 3px; }
    .mini { --bg: #f6f7fb; --bar: #ffffff; --card: #ffffff; --line: #e3e7ef; --ink: #14213d; }
    .mini.dark { --bg: #0e1420; --bar: #141c2b; --card: #172033; --line: #26314a; --ink: #e8ecf5; }
    .mini svg { display: block; width: 100%; height: 100%; }
    .st-theme .name { display: flex; align-items: center; gap: 8px; margin-top: 8px; font-size: 14px; font-weight: 600; color: var(--fn-text); }
    .st-theme .name i { color: var(--fn-muted); }
    .st-theme input:checked ~ .name i { color: var(--fn-accent); }

    /* Interruptor (checkbox verdadeira com aspeto de switch) */

    .st-google { width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--fn-line); display: grid; place-items: center; flex-shrink: 0; background: #fff; }
    .st-foot { margin-top: 8px; font-size: 13px; color: var(--fn-muted); text-align: right; }

    @media (prefers-reduced-motion: reduce) { .st-switch, .st-switch::after, .pw-meter i { transition: none; } }
    @media (max-width: 760px) {
    }
    @media (max-width: 520px) {
      .st-profile { flex-wrap: wrap; }
      .st-profile .acts { width: 100%; }
      .st-row { flex-wrap: wrap; }
      #notifForm { flex-wrap: nowrap; }
      .st-form .grid2 { grid-template-columns: 1fr; }
      .st-themes { gap: 10px; padding: 16px; }
    }
    /* Preferências das notificações */
    .st-prefs { padding: 4px 8px 10px; border-top: 1px solid var(--fn-line); }
    .st-pref { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 12px; cursor: pointer; margin: 0; font-size: 15px; color: var(--fn-text); }
    .st-pref:hover { background: var(--fn-paper); }
    .st-pref small { display: block; font-size: 13px; color: var(--fn-muted); }
    .st-pref .app-notif-ico { width: 32px; height: 32px; border-radius: 10px; font-size: 14px; }
    /* Apagar conta */
    .st-check { display: flex; gap: 10px; align-items: flex-start; font-size: 14px; color: var(--fn-text); cursor: pointer; }
    .st-check input { width: 18px; height: 18px; margin-top: 2px; accent-color: #d92d20; flex-shrink: 0; }
    .st-form .st-sub a, .st-foot a { color: inherit; text-underline-offset: 3px; }
    #apagar-conta { scroll-margin-top: 90px; }
  </style>
  <link rel="stylesheet" href="../css/finora.css?v=10">
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
<div class="pg-wrap">

  <div class="pg-head"><h1><?= te('Definições') ?></h1></div>

  <?php if ($message && $openForm !== 'apagar-conta'): ?>
    <?php $icon = ['success' => 'check-circle', 'danger' => 'exclamation-circle'][$messageType] ?? 'info-circle'; ?>
    <div class="st-flash <?= htmlspecialchars($messageType) ?>" role="<?= $messageType === 'danger' ? 'alert' : 'status' ?>">
      <i class="bi bi-<?= $icon ?>"></i><span><?= nl2br(htmlspecialchars($message)) ?></span>
    </div>
  <?php endif; ?>

  <!-- PERFIL -->
  <section class="pg-card st-card" aria-labelledby="sec-perfil" style="--tone:var(--cat-1)">
    <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-person"></i></span><div><h2 id="sec-perfil"><?= te('Perfil') ?></h2><p><?= te('O nome e a foto que aparecem no topo da Finora.') ?></p></div></header>
      <div class="st-profile">
        <div class="st-avatar">
          <?php if ($avatarUrl): ?>
            <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= te('A tua foto de perfil') ?>">
          <?php else: ?>
            <span class="letter" aria-hidden="true"><?= htmlspecialchars($initial) ?></span>
          <?php endif; ?>
        </div>
        <div class="who">
          <b><?= htmlspecialchars($user['username']) ?></b>
          <span class="st-sub"><?= htmlspecialchars($user['email']) ?></span>
        </div>
        <div class="acts">
          <form method="post" enctype="multipart/form-data" id="avatarForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upload_avatar">
            <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/gif" hidden>
            <label for="avatarInput" class="btn-s" style="margin:0"><i class="bi bi-camera"></i> <?= $avatarUrl ? 'Mudar foto' : 'Adicionar foto' ?></label>
          </form>
          <?php if ($avatarUrl): ?>
            <form method="post" onsubmit="return confirm(<?= htmlspecialchars(tj('Remover a foto de perfil?')) ?>)">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove_avatar">
              <button type="submit" class="btn-s btn-s-danger" aria-label="<?= te('Remover foto de perfil') ?>"><i class="bi bi-trash3"></i></button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <div class="st-row">
        <span class="pg-ico sm" style="--tone:var(--cat-5)" aria-hidden="true"><i class="bi bi-person-badge"></i></span>
        <div class="pg-grow">
          <p class="st-label"><?= te('Nome') ?></p>
          <p class="st-sub"><?= htmlspecialchars($user['username']) ?></p>
        </div>
        <button type="button" class="btn-s" data-bs-toggle="collapse" data-bs-target="#formUsername" aria-label="<?= te('Alterar nome') ?>" aria-expanded="<?= $openForm === 'formUsername' ? 'true' : 'false' ?>" aria-controls="formUsername">
          <i class="bi bi-pencil"></i> <?= te('Alterar') ?>
        </button>
      </div>
      <div class="collapse st-form <?= $openForm === 'formUsername' ? 'show' : '' ?>" id="formUsername">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_username">
          <label class="form-label" for="new_username"><?= te('Novo nome') ?></label>
          <input type="text" id="new_username" name="new_username" class="form-control mb-3" value="<?= htmlspecialchars($user['username']) ?>" minlength="3" maxlength="50" required autocomplete="name">
          <div class="acts">
            <button type="button" class="btn-s" data-bs-toggle="collapse" data-bs-target="#formUsername"><?= te('Cancelar') ?></button>
            <button type="submit" class="btn-s btn-s-primary"><?= te('Guardar nome') ?></button>
          </div>
        </form>
      </div>
  </section>

  <!-- SEGURANÇA -->
  <section class="pg-card st-card" aria-labelledby="sec-seg" style="--tone:var(--cat-3)">
    <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-shield-lock"></i></span><div><h2 id="sec-seg"><?= te('Acesso à conta') ?></h2><p><?= te('A palavra-passe e o email só podem mudar uma vez a cada 30 dias.') ?></p></div></header>
      <div class="st-row">
        <span class="pg-ico sm" style="--tone:var(--cat-4)" aria-hidden="true"><i class="bi bi-key"></i></span>
        <div class="pg-grow">
          <p class="st-label">Palavra-passe
            <?php if (!$canChangePassword): ?><span class="st-tag locked"><i class="bi bi-lock"></i> Daqui a <?= $daysUntilPasswordChange ?> dia(s)</span><?php endif; ?>
          </p>
          <p class="st-sub"><?= te('Última alteração:') ?> <?= $user['last_password_change'] ? $fmtDate($user['last_password_change']) : te('nunca') ?></p>
        </div>
        <button type="button" class="btn-s" data-bs-toggle="collapse" data-bs-target="#formPassword" aria-label="<?= te('Alterar palavra-passe') ?>" aria-expanded="<?= $openForm === 'formPassword' ? 'true' : 'false' ?>" aria-controls="formPassword" <?= $canChangePassword ? '' : 'disabled' ?>>
          <i class="bi bi-key"></i> <?= te('Alterar') ?>
        </button>
      </div>
      <div class="collapse st-form <?= $openForm === 'formPassword' ? 'show' : '' ?>" id="formPassword">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_password">
          <label class="form-label" for="current_password"><?= te('Palavra-passe atual') ?></label>
          <input type="password" id="current_password" name="current_password" class="form-control mb-3" required autocomplete="current-password">
          <div class="grid2 mb-3">
            <div>
              <label class="form-label" for="new_password"><?= te('Nova palavra-passe') ?></label>
              <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password" aria-describedby="pwHint">
              <div class="pw-meter" id="pwMeter" data-score="0" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
            </div>
            <div>
              <label class="form-label" for="confirm_password"><?= te('Repetir a nova') ?></label>
              <input type="password" id="confirm_password" name="confirm_password" class="form-control" required autocomplete="new-password">
            </div>
          </div>
          <p class="hint" id="pwHint"><?= te('8 caracteres ou mais, com maiúsculas, minúsculas, números e símbolos.') ?></p>
          <div class="acts">
            <button type="button" class="btn-s" data-bs-toggle="collapse" data-bs-target="#formPassword"><?= te('Cancelar') ?></button>
            <button type="submit" class="btn-s btn-s-primary"><?= te('Mudar palavra-passe') ?></button>
          </div>
        </form>
      </div>

      <div class="st-row">
        <span class="pg-ico sm" style="--tone:var(--cat-1)" aria-hidden="true"><i class="bi bi-envelope"></i></span>
        <div class="pg-grow">
          <p class="st-label">Email
            <?php if ($hasPendingEmail): ?><span class="st-tag wait"><i class="bi bi-clock-history"></i> <?= te('À espera de confirmação') ?></span>
            <?php elseif (!$canChangeEmail): ?><span class="st-tag locked"><i class="bi bi-lock"></i> Daqui a <?= $daysUntilEmailChange ?> dia(s)</span><?php endif; ?>
          </p>
          <p class="st-sub"><?= htmlspecialchars($user['email']) ?><?php if ($hasPendingEmail): ?> → <b><?= htmlspecialchars($user['pending_email']) ?></b><?php endif; ?></p>
        </div>
        <?php if ($hasPendingEmail): ?>
          <form method="post" style="margin:0">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel_email_change">
            <button type="submit" class="btn-s"><?= te('Cancelar pedido') ?></button>
          </form>
        <?php else: ?>
          <button type="button" class="btn-s" data-bs-toggle="collapse" data-bs-target="#formEmail" aria-label="<?= te('Alterar email') ?>" aria-expanded="<?= $openForm === 'formEmail' ? 'true' : 'false' ?>" aria-controls="formEmail" <?= $canChangeEmail ? '' : 'disabled' ?>>
            <i class="bi bi-envelope"></i> <?= te('Alterar') ?>
          </button>
        <?php endif; ?>
      </div>
      <?php if (!$hasPendingEmail): ?>
      <div class="collapse st-form <?= $openForm === 'formEmail' ? 'show' : '' ?>" id="formEmail">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_email">
          <div class="grid2 mb-2">
            <div>
              <label class="form-label" for="new_email"><?= te('Novo email') ?></label>
              <input type="email" id="new_email" name="new_email" class="form-control" required autocomplete="email">
            </div>
            <div>
              <label class="form-label" for="current_password_email"><?= te('Palavra-passe atual') ?></label>
              <input type="password" id="current_password_email" name="current_password_email" class="form-control" required autocomplete="current-password">
            </div>
          </div>
          <p class="hint mb-3"><?= te('Enviamos um link para o novo email. A mudança só acontece quando o abrires.') ?></p>
          <div class="acts">
            <button type="button" class="btn-s" data-bs-toggle="collapse" data-bs-target="#formEmail"><?= te('Cancelar') ?></button>
            <button type="submit" class="btn-s btn-s-primary"><?= te('Enviar link') ?></button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <div class="st-row">
        <div style="display:flex;gap:14px;align-items:center;min-width:0">
          <span class="st-google" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.964 10.707A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.707V4.961H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.039l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.961L3.964 6.293C4.672 4.169 6.656 3.58 9 3.58z"/></svg>
          </span>
          <div>
            <p class="st-label">Google
              <?php if ($user['google_id']): ?><span class="st-tag ok"><i class="bi bi-check-circle"></i> <?= te('Ligada') ?></span><?php endif; ?>
            </p>
            <p class="st-sub"><?= $user['google_id'] ? te('Podes entrar com a tua conta Google.') : te('Liga-a para entrares sem escrever a palavra-passe.') ?></p>
          </div>
        </div>
        <?php if ($user['google_id']): ?>
          <form method="post" style="margin:0" onsubmit="return confirm(<?= htmlspecialchars(tj('Desligar a conta Google? Passas a entrar só com email e palavra-passe.')) ?>)">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="unlink_google">
            <button type="submit" class="btn-s btn-s-danger"><?= te('Desligar') ?></button>
          </form>
        <?php else: ?>
          <a href="../auth/google_redirect.php?link=1" class="btn-s"><i class="bi bi-link-45deg"></i> <?= te('Ligar') ?></a>
        <?php endif; ?>
      </div>
  </section>

  <!-- APARÊNCIA -->
  <section class="pg-card st-card" aria-labelledby="sec-apar" style="--tone:var(--cat-7)">
    <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-palette"></i></span><div><h2 id="sec-apar"><?= te('Aparência, língua e moeda') ?></h2><p><?= te('Fica guardado assim que escolhes.') ?></p></div></header>
      <form method="post" id="themeForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_theme">
        <fieldset class="st-themes">
          <legend class="visually-hidden"><?= te('Tema') ?></legend>
          <?php foreach (['light' => [t('Claro'), 'sun'], 'dark' => [t('Escuro'), 'moon-stars']] as $val => [$label, $ico]): ?>
            <label class="st-theme">
              <input type="radio" name="theme" value="<?= $val ?>" <?= $currentTheme === $val ? 'checked' : '' ?>>
              <span class="mini <?= $val ?>" aria-hidden="true">
                <svg viewBox="0 0 160 100" preserveAspectRatio="none">
                  <rect width="160" height="100" fill="var(--bg)"/>
                  <rect width="160" height="16" fill="var(--bar)"/>
                  <rect x="8" y="5" width="22" height="6" rx="3" fill="var(--ink)"/>
                  <rect x="128" y="4" width="24" height="8" rx="4" fill="#d99a1e"/>
                  <rect x="8" y="24" width="88" height="40" rx="6" fill="var(--card)" stroke="var(--line)"/>
                  <rect x="16" y="32" width="40" height="5" rx="2.5" fill="var(--ink)" opacity=".45"/>
                  <rect x="16" y="42" width="60" height="9" rx="3" fill="var(--ink)"/>
                  <rect x="104" y="24" width="48" height="40" rx="6" fill="var(--card)" stroke="var(--line)"/>
                  <rect x="112" y="48" width="8" height="10" rx="2" fill="#2a78d6"/>
                  <rect x="124" y="38" width="8" height="20" rx="2" fill="#eb6834"/>
                  <rect x="136" y="44" width="8" height="14" rx="2" fill="#1baf7a"/>
                  <rect x="8" y="72" width="144" height="20" rx="6" fill="var(--card)" stroke="var(--line)"/>
                  <rect x="16" y="79" width="70" height="6" rx="3" fill="var(--ink)" opacity=".45"/>
                </svg>
              </span>
              <span class="name"><i class="bi bi-<?= $ico ?>"></i> <?= $label ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>
        <noscript><div class="st-row"><button type="submit" class="btn-s btn-s-primary"><?= te('Guardar tema') ?></button></div></noscript>
      </form>
      <form method="post" id="languageForm" class="st-row">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_language">
        <span class="pg-ico sm" style="--tone:var(--cat-1)" aria-hidden="true"><i class="bi bi-translate"></i></span>
        <div class="pg-grow">
          <label class="st-label" for="language"><?= te('Língua') ?></label>
          <p class="st-sub" id="languageHelp"><?= te('Os nomes que escreves (cartões, transações, metas) ficam como os escreveste.') ?></p>
        </div>
        <select class="form-select st-currency" id="language" name="language" aria-describedby="languageHelp">
          <?php foreach (LANGS as $code => [$name]): ?>
            <option value="<?= $code ?>" lang="<?= LANGS[$code][1] ?>" <?= current_lang() === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn-s"><?= te('Guardar') ?></button></noscript>
      </form>
      <form method="post" id="currencyForm" class="st-row">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_currency">
        <span class="pg-ico sm" style="--tone:var(--cat-3)" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
        <div class="pg-grow">
          <label class="st-label" for="currency"><?= te('Moeda') ?></label>
          <p class="st-sub" id="currencyHelp"><?= te('Os valores aparecem nesta moeda em toda a app. Não são convertidos: muda só o símbolo.') ?></p>
        </div>
        <select class="form-select st-currency" id="currency" name="currency" aria-describedby="currencyHelp">
          <?php foreach (CURRENCIES as $code => [$sym, $name]): ?>
            <option value="<?= $code ?>" <?= user_currency() === $code ? 'selected' : '' ?>><?= htmlspecialchars(t($name) . " ($sym)") ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn-s"><?= te('Guardar') ?></button></noscript>
      </form>
      <form method="post" class="st-row">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_income">
        <span class="pg-ico sm" style="--tone:var(--cat-4)" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
        <div class="pg-grow">
          <label class="st-label" for="monthlyIncome"><?= te('Rendimento mensal (opcional)') ?></label>
          <p class="st-sub" id="incomeHelp"><?= te('Líquido, já sem impostos. Usado para prever as metas.') ?></p>
        </div>
        <input type="text" inputmode="decimal" class="form-control st-currency" id="monthlyIncome" name="monthly_income" style="min-width:0;width:130px"
               value="<?= isset($settings['monthly_income']) ? htmlspecialchars((string) (float) $settings['monthly_income']) : '' ?>" placeholder="—" aria-describedby="incomeHelp">
        <button type="submit" class="btn-s"><?= te('Guardar') ?></button>
      </form>

  </section>

  <?php $np = notif_prefs($pdo, (int) $uid); ?>
  <section class="pg-card" id="notificacoes" style="--tone: var(--cat-4)" aria-labelledby="sec-notif">
    <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-bell"></i></span><div><h2 id="sec-notif"><?= te('Notificações') ?></h2><p><?= te('O que aparece no sino do cabeçalho. A Finora verifica uma vez por dia.') ?></p></div></header>
      <form method="post" id="notifForm" class="st-row">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_notifications">
        <span class="pg-ico sm" style="--tone:var(--cat-4)" aria-hidden="true"><i class="bi bi-bell"></i></span>
        <div class="pg-grow">
          <label class="st-label" for="notifications"><?= te('Notificações') ?></label>
          <p class="st-sub" id="notifHelp"><?= te('Desliga para deixares de receber todos os avisos (no sino e por email).') ?></p>
        </div>
        <input type="checkbox" role="switch" class="st-switch" id="notifications" name="notifications" aria-describedby="notifHelp" <?= !empty($settings['notifications']) ? 'checked' : '' ?>>
        <noscript><button type="submit" class="btn-s"><?= te('Guardar') ?></button></noscript>
      </form>
      <?php if (!empty($settings['notifications'])): ?>
      <form method="post" id="notifPrefsForm" class="st-prefs">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_notif_prefs">
        <?php foreach (NOTIF_TYPES as $t => [$tLabel, $tIcon, $tTone]): ?>
          <label class="st-pref">
            <span class="app-notif-ico tone-<?= $tTone ?>" aria-hidden="true"><i class="bi bi-<?= $tIcon ?>"></i></span>
            <span class="pg-grow"><?= te($tLabel) ?></span>
            <input type="checkbox" role="switch" class="st-switch" name="t[<?= $t ?>]" <?= $np[$t] ? 'checked' : '' ?>>
          </label>
        <?php endforeach; ?>
        <label class="st-pref">
          <span class="app-notif-ico tone-cat-1" aria-hidden="true"><i class="bi bi-envelope"></i></span>
          <span class="pg-grow"><?= te('Também por email: alertas de orçamento') ?><small><?= te('Os lembretes de pagamento têm o seu próprio "Método" em cada lembrete.') ?></small></span>
          <input type="checkbox" role="switch" class="st-switch" name="budget_email" <?= $np['budget_email'] ? 'checked' : '' ?>>
        </label>
        <noscript><div class="st-row"><button type="submit" class="btn-s btn-s-primary"><?= te('Guardar preferências') ?></button></div></noscript>
      </form>
      <?php endif; ?>
  </section>

  <!-- OS TEUS DADOS (RGPD: exportar, saber o que guardamos, apagar) -->
  <section class="pg-card st-card" id="os-teus-dados" aria-labelledby="sec-dados" style="--tone:var(--cat-8)">
    <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-shield-check"></i></span><div><h2 id="sec-dados"><?= te('Os teus dados') ?></h2><p><?= te('Leva os teus dados contigo ou apaga a conta de vez.') ?></p></div></header>
      <div class="st-row">
        <span class="pg-ico sm" style="--tone:var(--cat-1)" aria-hidden="true"><i class="bi bi-box-arrow-up"></i></span>
        <div class="pg-grow">
          <p class="st-label"><?= te('Exportar') ?></p>
          <p class="st-sub"><?= te('Transações em CSV ou relatório em PDF.') ?></p>
        </div>
        <a class="btn-s" href="dados.php"><i class="bi bi-download"></i> <?= te('Exportar') ?></a>
      </div>
      <div class="st-row">
        <span class="pg-ico sm" style="--tone:var(--cat-3)" aria-hidden="true"><i class="bi bi-file-earmark-lock"></i></span>
        <div class="pg-grow">
          <p class="st-label"><?= te('Privacidade') ?></p>
          <p class="st-sub"><?= te('O que guardamos, com quem partilhamos e durante quanto tempo.') ?></p>
        </div>
        <a class="btn-s" href="../privacidade.php">Ler</a>
      </div>
      <div class="st-row" id="apagar-conta">
        <span class="pg-ico sm" style="--tone:var(--cat-8)" aria-hidden="true"><i class="bi bi-trash3"></i></span>
        <div class="pg-grow">
          <p class="st-label"><?= te('Apagar conta') ?></p>
          <p class="st-sub"><?= te('Apaga a conta e tudo o que registaste. Não dá para voltar atrás.') ?></p>
        </div>
        <button type="button" class="btn-s btn-s-danger" data-bs-toggle="collapse" data-bs-target="#formDelete" aria-expanded="<?= $openForm === 'apagar-conta' ? 'true' : 'false' ?>" aria-controls="formDelete">
          <i class="bi bi-trash3"></i> <?= te('Apagar') ?>
        </button>
      </div>
      <div class="collapse st-form <?= $openForm === 'apagar-conta' ? 'show' : '' ?>" id="formDelete">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_account">
          <?php if ($message && $openForm === 'apagar-conta'): ?>
            <div class="st-flash danger" role="alert"><i class="bi bi-exclamation-circle"></i><span><?= htmlspecialchars($message) ?></span></div>
          <?php endif; ?>
          <p class="st-sub mb-3"><?= te('Vão ser apagados os teus cartões, transações, orçamentos, lembretes, poupanças, metas, investimentos e notificações. Se quiseres uma cópia,') ?> <a href="dados.php"><?= te('exporta primeiro') ?></a>.</p>
          <?php if ($user['google_id']): ?>
            <label class="form-label" for="confirm_delete"><?= te('Escreve o teu email para confirmar') ?></label>
            <input type="email" id="confirm_delete" name="confirm_delete" class="form-control mb-3" required autocomplete="off" placeholder="<?= htmlspecialchars($user['email']) ?>">
          <?php else: ?>
            <label class="form-label" for="confirm_delete"><?= te('Palavra-passe') ?></label>
            <input type="password" id="confirm_delete" name="confirm_delete" class="form-control mb-3" required autocomplete="current-password">
          <?php endif; ?>
          <label class="st-check mb-3"><input type="checkbox" name="understand" value="1" required> <?= te('Percebo que a conta e os dados são apagados para sempre.') ?></label>
          <div class="acts">
            <button type="button" class="btn-s" data-bs-toggle="collapse" data-bs-target="#formDelete"><?= te('Cancelar') ?></button>
            <button type="submit" class="btn-s btn-s-danger"><i class="bi bi-trash3"></i> <?= te('Apagar a minha conta') ?></button>
          </div>
        </form>
      </div>
  </section>

  <p class="st-foot"><a href="../privacidade.php"><?= te('Privacidade') ?></a> · <?= te('Membro desde {date}', ['date' => fmt_date_long($since, true)]) ?> · Finora 2.0.0</p>

</div>
</div>
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Tema: mostra logo e grava
document.getElementById('language').addEventListener('change', () => document.getElementById('languageForm').submit());
document.getElementById('currency').addEventListener('change', () => document.getElementById('currencyForm').submit());
document.querySelectorAll('#themeForm input[name="theme"]').forEach(r => r.addEventListener('change', () => {
  document.documentElement.setAttribute('data-theme', r.value);
  document.getElementById('themeForm').submit();
}));

// Notificações: grava ao mudar qualquer interruptor
document.getElementById('notifications').addEventListener('change', () => document.getElementById('notifForm').submit());
document.getElementById('notifPrefsForm')?.addEventListener('change', (e) => e.currentTarget.submit());

// Foto: envia assim que escolhes o ficheiro
document.getElementById('avatarInput').addEventListener('change', function () {
  if (this.files[0]) document.getElementById('avatarForm').submit();
});

// Força da nova palavra-passe (só visual; quem decide é o servidor)
const pw = document.getElementById('new_password');
if (pw) pw.addEventListener('input', () => {
  const v = pw.value;
  const score = [v.length >= 8, /[A-Z]/.test(v), /[a-z]/.test(v), /[0-9]/.test(v), /[^A-Za-z0-9]/.test(v)].filter(Boolean).length;
  document.getElementById('pwMeter').dataset.score = v ? score : 0;
});
</script>
</body>
</html>
