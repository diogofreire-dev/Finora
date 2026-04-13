<?php
/**
 * Get Company by NIF - Production ready from verified implementation
 * Uses unified cache → nif.pt API → not found flow
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/nif_lookup.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['found' => false]);
    exit;
}

$nif = trim($_GET['nif'] ?? '');

if (!preg_match('/^\d{9}$/', $nif)) {
    echo json_encode([
        'found' => false, 
        'nome_empresa' => '', 
        'id_categoria' => null,
        'error' => 'NIF inválido'
    ]);
    exit;
}

// Unified lookup: cache → VIES → nif.pt → fallback
$result = nif_lookup($nif);

echo json_encode([
    'found' => $result['success'],
    'nome_empresa' => $result['success'] ? $result['nome'] : '',
    'id_categoria' => $result['id_categoria'] ?? null,
    'source' => $result['source'] ?? 'none',
    'fonte' => $result['fonte'] ?? null,
    'verified' => $result['verified'] ?? false
]);
?>

