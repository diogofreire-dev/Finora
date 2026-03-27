<?php
require_once __DIR__ . '/config/db.php';

echo "=== Test: Simulando chamada GET do JavaScript ===\n\n";

// Usar um NIF que EXISTE na BD
$test_nif = '503789372'; // STAPLES

echo "Testando NIF: $test_nif\n\n";

// 1. Simular o que get_company_by_nif.php faz
echo "1. BD Local:\n";
$stmt = $pdo->prepare("SELECT nome_empresa, id_categoria FROM nif_empresas WHERE nif = ? LIMIT 1");
$stmt->execute([$test_nif]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "   ✅ Encontrado: " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    $response = [
        'found' => true,
        'nome_empresa' => $row['nome_empresa'],
        'id_categoria' => $row['id_categoria'],
        'source' => 'local'
    ];
} else {
    echo "   ❌ Não encontrado na BD\n";
    $response = ['found' => false, 'nome_empresa' => '', 'id_categoria' => null];
}

echo "\n2. Resposta JSON que o JavaScript receberia:\n";
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// 2. Verificar o endpoint real
echo "\n3. Verificando arquivo get_company_by_nif.php:\n";
if (file_exists(__DIR__ . '/site/pages/get_company_by_nif.php')) {
    echo "   ✅ Arquivo existe\n";
    
    // Simular uma chamada GET
    ob_start();
    $_GET['nif'] = $test_nif;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    include __DIR__ . '/site/pages/get_company_by_nif.php';
    $output = ob_get_clean();
    
    echo "\n   Saída do endpoint:\n";
    echo "   " . trim($output) . "\n";
} else {
    echo "   ❌ Arquivo não encontrado\n";
}

echo "\nFim do teste.\n";
?>
