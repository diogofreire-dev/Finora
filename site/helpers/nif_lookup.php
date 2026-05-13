<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$nif = trim($_GET['nif'] ?? '');

if (!preg_match('/^\d{9}$/', $nif)) {
    echo json_encode(['success' => false, 'error' => 'NIF inválido']);
    exit;
}

// Verificar cache local primeiro
$stmt = $pdo->prepare("SELECT name FROM nif_cache WHERE nif = :nif");
$stmt->execute([':nif' => $nif]);
$cached = $stmt->fetch();

if ($cached) {
    echo json_encode(['success' => true, 'name' => $cached['name'], 'source' => 'cache']);
    exit;
}

// Consultar nif.pt com headers completos para evitar bloqueio
$url = "https://www.nif.pt/?json&nif=" . urlencode($nif) . "&q=" . urlencode($nif);

$opts = [
    'http' => [
        'timeout'        => 8,
        'method'         => 'GET',
        'header'         => implode("\r\n", [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: pt-PT,pt;q=0.9,en;q=0.8',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Referer: https://www.nif.pt/',
        ]),
        'ignore_errors' => true,
    ],
    'ssl' => [
        'verify_peer'      => false,
        'verify_peer_name' => false,
    ],
];

$ctx      = stream_context_create($opts);
$response = @file_get_contents($url, false, $ctx);

// Log para debug (apenas erros)
if ($response === false) {
    error_log("[nif_lookup] Falha ao contactar nif.pt para NIF $nif");
    echo json_encode(['success' => false, 'error' => 'Não foi possível consultar a API do NIF']);
    exit;
}

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("[nif_lookup] Resposta inválida da API para NIF $nif: " . substr($response, 0, 200));
    echo json_encode(['success' => false, 'error' => 'Resposta inválida da API']);
    exit;
}

// A API nif.pt pode retornar 'result' => 'success' ou ter estrutura diferente
$name = null;

if (!empty($data['records'])) {
    $record = reset($data['records']);
    $name   = $record['title'] ?? $record['name'] ?? null;
} elseif (!empty($data['title'])) {
    $name = $data['title'];
} elseif (!empty($data['name'])) {
    $name = $data['name'];
}

if (empty($name)) {
    error_log("[nif_lookup] NIF $nif não encontrado. Resposta: " . substr($response, 0, 300));
    echo json_encode(['success' => false, 'error' => 'NIF não encontrado na base de dados']);
    exit;
}

$name = trim($name);

// Guardar em cache
try {
    $stmt = $pdo->prepare("INSERT INTO nif_cache (nif, name) VALUES (:nif, :name) ON DUPLICATE KEY UPDATE name = :name2, fetched_at = NOW()");
    $stmt->execute([':nif' => $nif, ':name' => $name, ':name2' => $name]);
} catch (PDOException $e) {
    error_log("[nif_lookup] Erro ao guardar cache para NIF $nif: " . $e->getMessage());
}

echo json_encode(['success' => true, 'name' => $name, 'source' => 'api']);