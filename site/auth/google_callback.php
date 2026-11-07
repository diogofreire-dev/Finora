<?php
// site/auth/google_callback.php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/google_oauth.php';
require_once __DIR__ . '/../helpers/i18n.php';

// As colunas google_id e avatar_path vêm de database/migration_users_profile.sql
// (antes cada login com Google tentava fazer ALTER TABLE à base de dados).

function googleError($msg) {
    $_SESSION['google_error'] = $msg;
    header('Location: login.php');
    exit;
}

// Verificar state anti-CSRF
$state = (string) ($_GET['state'] ?? '');
if (empty($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], $state)) {
    googleError(t('Sessão inválida. Tenta novamente.'));
}
unset($_SESSION['google_oauth_state']);

// Verificar código
$code = (string) ($_GET['code'] ?? '');
if (empty($code)) {
    googleError(t('Login com Google cancelado.'));
}

// Trocar código por token
$tokenResponse = @file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query([
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
    ],
]));

if (!$tokenResponse) {
    // Tentar com cURL como fallback
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $tokenResponse = curl_exec($ch);
    curl_close($ch);
}

if (!$tokenResponse) {
    googleError(t('Não foi possível comunicar com o Google. Tenta novamente.'));
}

$tokenData = json_decode($tokenResponse, true);
$accessToken = $tokenData['access_token'] ?? '';
if (empty($accessToken)) {
    googleError(t('Falha ao obter token do Google. Tenta novamente.'));
}

// Obter informações do utilizador
$userInfoResponse = @file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo', false, stream_context_create([
    'http' => ['header' => "Authorization: Bearer $accessToken\r\n"],
]));

if (!$userInfoResponse) {
    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $accessToken"],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $userInfoResponse = curl_exec($ch);
    curl_close($ch);
}

if (!$userInfoResponse) {
    googleError(t('Não foi possível obter informações do Google. Tenta novamente.'));
}

$googleUser = json_decode($userInfoResponse, true);
$googleId   = $googleUser['id']             ?? '';
$googleEmail= strtolower($googleUser['email'] ?? '');
$googleName = $googleUser['name']           ?? '';
$googlePic  = $googleUser['picture']        ?? '';
$emailVerified = $googleUser['verified_email'] ?? false;

if (empty($googleId) || empty($googleEmail)) {
    googleError(t('Não foi possível obter o teu email do Google.'));
}

// Só confiar no email se o Google o tiver verificado. Sem isto, uma conta Google criada
// com o email de outra pessoa (ainda por verificar) entrava na conta Finora dessa pessoa.
if ($emailVerified !== true) {
    googleError(t('O email da tua conta Google ainda não está verificado.'));
}

// Nome mostrado na app: sem caracteres de controlo e no máximo 50 (tamanho da coluna)
$displayName = trim(preg_replace('/\s+/u', ' ', preg_replace('/\p{C}/u', '', $googleName) ?? ''));
if (mb_strlen($displayName) < 3) $displayName = explode('@', $googleEmail)[0];
$displayName = mb_substr($displayName, 0, 50);

// Mensagem para a página de definições (mesmo formato que settings.php lê)
function settingsFlash(string $type, string $text): void {
    $_SESSION['flash_settings'] = [[$type, $text], null];
    header('Location: ../pages/settings.php');
    exit;
}

// Modo ligar conta (chamado a partir das definições)
$linkUid = (int) ($_SESSION['google_link_uid'] ?? 0);
unset($_SESSION['google_link_uid']);

if ($linkUid && $linkUid === (int) ($_SESSION['user_id'] ?? 0)) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE google_id = :gid AND id != :uid LIMIT 1');
    $stmt->execute([':gid' => $googleId, ':uid' => $linkUid]);
    if ($stmt->fetch()) settingsFlash('danger', t('Esta conta Google já está ligada a outro utilizador.'));
    $pdo->prepare('UPDATE users SET google_id = :gid WHERE id = :uid')
        ->execute([':gid' => $googleId, ':uid' => $linkUid]);
    settingsFlash('success', t('Conta Google ligada. Já podes entrar com ela.'));
}

// Primeiro pela conta Google já ligada; só depois pelo email
$stmt = $pdo->prepare('SELECT id, username, google_id, email_verified FROM users WHERE google_id = :gid LIMIT 1');
$stmt->execute([':gid' => $googleId]);
$user = $stmt->fetch();
if (!$user) {
    $stmt = $pdo->prepare('SELECT id, username, google_id, email_verified FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $googleEmail]);
    $user = $stmt->fetch();
}

if ($user) {
    if (empty($user['google_id'])) {
        if (!$user['email_verified']) {
            // Conta criada com este email mas nunca confirmada: quem a criou pode não ser o dono
            // do email e saber a palavra-passe. O Google provou quem é o dono, por isso a
            // palavra-passe antiga é apagada (pre-account takeover).
            $pdo->prepare('UPDATE users SET google_id = :gid, email_verified = 1, verification_token = NULL, verification_expires = NULL,
                                  password_hash = :p, password_reset_token = NULL, password_reset_expires = NULL
                           WHERE id = :id')
                ->execute([':gid' => $googleId, ':p' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), ':id' => $user['id']]);
        } else {
            $pdo->prepare('UPDATE users SET google_id = :gid WHERE id = :id')
                ->execute([':gid' => $googleId, ':id' => $user['id']]);
        }
    }
    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    header('Location: ../dashboard.php');
    exit;
}

// Conta nova (sem palavra-passe utilizável, email já confirmado pelo Google)
try {
    $pdo->prepare('INSERT INTO users (username, email, password_hash, email_verified, google_id) VALUES (:u, :e, :p, 1, :gid)')
        ->execute([
            ':u'   => $displayName,
            ':e'   => $googleEmail,
            ':p'   => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            ':gid' => $googleId,
        ]);
    $newId = $pdo->lastInsertId();
    try {
        // A conta nova fica na língua em que a pessoa estava a ver o site
        $pdo->prepare('INSERT INTO user_settings (user_id, language) VALUES (:uid, :lang)')->execute([':uid' => $newId, ':lang' => current_lang()]);
    } catch (PDOException $e) {}
    session_regenerate_id(true);
    $_SESSION['user_id']  = $newId;
    $_SESSION['username'] = $displayName;
    header('Location: ../dashboard.php');
    exit;
} catch (PDOException $e) {
    error_log('[google_callback] ' . $e->getMessage());
    googleError(t('Não foi possível criar a conta. Tenta outra vez.'));
}
