<?php
// site/pages/save_company_nif.php

require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$nif           = trim($_POST['nif'] ?? '');
$nome_empresa  = trim($_POST['nome_empresa'] ?? '');
$id_categoria  = !empty($_POST['id_categoria']) ? intval($_POST['id_categoria']) : null;

error_log("DEBUG save_company_nif: nif=$nif, nome=$nome_empresa, categoria=$id_categoria");

if (!preg_match('/^\d{9}$/', $nif) || $nome_empresa === '') {
    error_log("DEBUG: Validação falhou - nif=$nif, nome=$nome_empresa");
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO nif_empresas (nif, nome_empresa, id_categoria) 
    VALUES (:nif, :nome, :cat) 
    ON DUPLICATE KEY UPDATE 
        nome_empresa = VALUES(nome_empresa),
        id_categoria = VALUES(id_categoria)
");

try {
    $success = $stmt->execute([':nif' => $nif, ':nome' => $nome_empresa, ':cat' => $id_categoria]);
    error_log("DEBUG: Execute result: " . ($success ? 'true' : 'false'));
    
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        error_log("DEBUG: Erro no execute - " . json_encode($stmt->errorInfo()));
        echo json_encode(['success' => false, 'error' => 'Erro ao guardar']);
    }
} catch (Exception $e) {
    error_log("DEBUG: Exception - " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
