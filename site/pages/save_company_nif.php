<?php
/**
 * Save Company NIF - Uses unified helper for cache consistency
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/nif_lookup.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$nif          = trim($_POST['nif'] ?? '');
$nome_empresa = trim($_POST['nome_empresa'] ?? '');
$id_categoria = !empty($_POST['id_categoria']) ? intval($_POST['id_categoria']) : null;

if (!preg_match('/^\d{9}$/', $nif) || empty($nome_empresa)) {
    echo json_encode(['success' => false, 'error' => 'NIF inválido ou nome vazio']);
    exit;
}

$success = save_company_manual($nif, $nome_empresa, $id_categoria);

echo json_encode([
    'success' => $success,
    'message' => $success ? 'Empresa salva com sucesso' : 'Erro ao salvar'
]);
?>

