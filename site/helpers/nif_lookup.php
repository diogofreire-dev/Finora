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

// Consultar nif.pt
$url = "https://www.nif.pt/?json&nif=" . urlencode($nif);
$ctx = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'Mozilla/5.0']]);
$response = @file_get_contents($url, false, $ctx);

if ($response === false) {
    echo json_encode(['success' => false, 'error' => 'Não foi possível consultar o NIF']);
    exit;
}

$data = json_decode($response, true);

if (!$data || !isset($data['result']) || $data['result'] !== 'success' || empty($data['records'])) {
    echo json_encode(['success' => false, 'error' => 'NIF não encontrado']);
    exit;
}

$record = reset($data['records']);
$name = $record['title'] ?? '';

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Nome não disponível']);
    exit;
}

// Guardar em cache
$stmt = $pdo->prepare("INSERT INTO nif_cache (nif, name) VALUES (:nif, :name) ON DUPLICATE KEY UPDATE name = :name2, fetched_at = NOW()");
$stmt->execute([':nif' => $nif, ':name' => $name, ':name2' => $name]);

echo json_encode(['success' => true, 'name' => $name, 'source' => 'api']);
