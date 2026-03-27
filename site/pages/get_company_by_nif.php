<?php
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit;
}

$nif = trim($_GET['nif'] ?? '');
if (!preg_match('/^\d{9}$/', $nif)) {
    echo json_encode(['found' => false, 'nome_empresa' => '', 'id_categoria' => null]);
    exit;
}

// 1. BD local
$stmt = $pdo->prepare("SELECT nome_empresa, id_categoria FROM nif_empresas WHERE nif = ? LIMIT 1");
$stmt->execute([$nif]);
$row = $stmt->fetch();

if ($row) {
    echo json_encode([
        'found'        => true,
        'nome_empresa' => $row['nome_empresa'],
        'id_categoria' => $row['id_categoria'],
        'source'       => 'local'
    ]);
    exit;
}

// 2. VIES (grátis, sem key)
$url = "https://ec.europa.eu/taxation_customs/vies/rest-api/ms/PT/vat/" . urlencode($nif);
$ctx = stream_context_create(['http' => ['timeout' => 6, 'header' => "Accept: application/json\r\n"]]);
$res = @file_get_contents($url, false, $ctx);
$data = $res ? json_decode($res, true) : null;

if (!empty($data['isValid']) && !empty($data['traderName'])) {
    $nome = trim($data['traderName']);
    $pdo->prepare("INSERT INTO nif_empresas (nif, nome_empresa) VALUES (?,?) ON DUPLICATE KEY UPDATE nome_empresa=VALUES(nome_empresa)")
        ->execute([$nif, $nome]);
    echo json_encode(['found' => true, 'nome_empresa' => $nome, 'id_categoria' => null, 'source' => 'vies']);
} else {
    echo json_encode(['found' => false, 'nome_empresa' => '', 'id_categoria' => null]);
}
?>


