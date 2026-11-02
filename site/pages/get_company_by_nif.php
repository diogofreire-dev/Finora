<?php
/**
 * get_company_by_nif.php — devolve o nome da empresa de um NIF (usado no scan de faturas)
 */
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/nif_lookup.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['found' => false, 'error' => 'method_not_allowed']);
    exit;
}

$nif = trim($_GET['nif'] ?? '');

if (!preg_match('/^\d{9}$/', $nif)) {
    echo json_encode(['found' => false, 'error' => 'nif_invalido']);
    exit;
}

$result = nif_lookup($nif);

$response = [
    'found'        => $result['success'],
    'nome_empresa' => $result['success'] ? $result['nome'] : '',
    'id_categoria' => $result['id_categoria'] ?? null,
    'source'       => $result['source']   ?? 'none',
    'fonte'        => $result['fonte']    ?? null,
    'verified'     => $result['verified'] ?? false,
];

echo json_encode($response);

