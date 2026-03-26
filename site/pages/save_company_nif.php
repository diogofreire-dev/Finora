<?php
// site/pages/save_company_nif.php

require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$nif          = trim($_POST['nif'] ?? '');
$nome_empresa = trim($_POST['nome_empresa'] ?? '');

if (!preg_match('/^\d{9}$/', $nif) || $nome_empresa === '') {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO nif_empresas (nif, nome_empresa) VALUES (:nif, :nome) ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa)");
$success = $stmt->execute([':nif' => $nif, ':nome' => $nome_empresa]);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Erro ao guardar']);
}
