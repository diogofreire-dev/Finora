<?php
// site/auth/google_callback.php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/google_oauth.php';

// Auto-migração: adicionar coluna google_id se não existir
try { $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(128) NULL DEFAULT NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL DEFAULT NULL"); } catch (PDOException $e) {}

function googleError($msg) {
    $_SESSION['google_error'] = $msg;
    header('Location: login.php');
    exit;
}

// Verificar state anti-CSRF
$state = $_GET['state'] ?? '';
if (empty($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], $state)) {
    googleError('Sessão inválida. Tenta novamente.');
}
unset($_SESSION['google_oauth_state']);

// Verificar código
$code = $_GET['code'] ?? '';
if (empty($code)) {
    googleError('Login com Google cancelado.');
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
    googleError('Não foi possível comunicar com o Google. Tenta novamente.');
}

$tokenData = json_decode($tokenResponse, true);
$accessToken = $tokenData['access_token'] ?? '';
if (empty($accessToken)) {
    googleError('Falha ao obter token do Google. Tenta novamente.');
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
    googleError('Não foi possível obter informações do Google. Tenta novamente.');
}

$googleUser = json_decode($userInfoResponse, true);
$googleId   = $googleUser['id']             ?? '';
$googleEmail= strtolower($googleUser['email'] ?? '');
$googleName = $googleUser['name']           ?? '';
$googlePic  = $googleUser['picture']        ?? '';
$emailVerified = $googleUser['verified_email'] ?? false;

if (empty($googleId) || empty($googleEmail)) {
    googleError('Não foi possível obter o teu email do Google.');
}

// Só confiar no email se o Google o tiver verificado. Sem isto, uma conta Google criada
// com o email de outra pessoa (ainda por verificar) entrava na conta Finora dessa pessoa.
if ($emailVerified !== true) {
    googleError('O email da tua conta Google ainda não está verificado.');
}

// Verificar se já existe conta com este google_id ou email
$stmt = $pdo->prepare("SELECT id, username, email, google_id FROM users WHERE google_id = :gid OR email = :email LIMIT 1");
$stmt->execute([':gid' => $googleId, ':email' => $googleEmail]);
$user = $stmt->fetch();

// Modo vincular conta (chamado a partir das definições)
$linkMode = !empty($_SESSION['google_link_uid']);
$linkUid  = $linkMode ? (int)$_SESSION['google_link_uid'] : 0;
if ($linkMode) unset($_SESSION['google_link_uid']);

if ($linkMode) {
    // Verificar se este google_id já está noutro utilizador
    $stmt = $pdo->prepare("SELECT id FROM users WHERE google_id = :gid AND id != :uid LIMIT 1");
    $stmt->execute([':gid' => $googleId, ':uid' => $linkUid]);
    if ($stmt->fetch()) {
        $_SESSION['settings_error'] = 'Esta conta Google já está vinculada a outro utilizador.';
        header('Location: ../pages/settings.php');
        exit;
    }
    $pdo->prepare("UPDATE users SET google_id = :gid WHERE id = :uid")
        ->execute([':gid' => $googleId, ':uid' => $linkUid]);
    $_SESSION['settings_success'] = 'Conta Google vinculada com sucesso!';
    header('Location: ../pages/settings.php');
    exit;
}

if ($user) {
    // Utilizador já existe — atualizar google_id se ainda não estiver ligado
    if (empty($user['google_id'])) {
        $pdo->prepare("UPDATE users SET google_id = :gid WHERE id = :id")
            ->execute([':gid' => $googleId, ':id' => $user['id']]);
    }
    // Login direto
    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    header('Location: ../dashboard.php');
    exit;
} else {
    // Criar conta nova (sem palavra-passe, verificada pelo Google)
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, email_verified, google_id) VALUES (:u, :e, :p, 1, :gid)");
        $stmt->execute([
            ':u'   => $googleName ?: explode('@', $googleEmail)[0],
            ':e'   => $googleEmail,
            ':p'   => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            ':gid' => $googleId,
        ]);
        $newId = $pdo->lastInsertId();
        try {
            $pdo->prepare("INSERT INTO user_settings (user_id) VALUES (:uid)")->execute([':uid' => $newId]);
        } catch (PDOException $e) {}
        session_regenerate_id(true);
        $_SESSION['user_id']  = $newId;
        $_SESSION['username'] = $googleName ?: explode('@', $googleEmail)[0];
        header('Location: ../dashboard.php');
        exit;
    } catch (PDOException $e) {
        googleError('Erro ao criar conta. Tenta novamente.');
    }
}