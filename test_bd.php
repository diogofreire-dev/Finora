<?php
require_once __DIR__ . '/config/db.php';

echo "=== Verificando BD ===\n\n";

// 1. Verificar tabela
echo "1. Estrutura de nif_empresas:\n";
$stmt = $pdo->query("DESCRIBE nif_empresas");
foreach ($stmt->fetchAll() as $col) {
    echo "   - {$col['Field']} ({$col['Type']})\n";
}

// 2. Verificar dados
echo "\n2. Dados na tabela:\n";
$stmt = $pdo->query("SELECT COUNT(*) as total FROM nif_empresas");
$count = $stmt->fetch()['total'];
echo "   Total de linhas: $count\n";

if ($count > 0) {
    echo "\n   Últimas 3 registos:\n";
    $stmt = $pdo->query("SELECT * FROM nif_empresas ORDER BY id DESC LIMIT 3");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "   " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    echo "   (tabela vazia)\n";
}

// 3. Testar API com um NIF
echo "\n3. Testando get_company_by_nif.php:\n";
$test_nifs = ['510282735', '123456789', '999999999'];

foreach ($test_nifs as $nif) {
    echo "\n   NIF: $nif\n";
    
    // Busca diretamente
    $stmt = $pdo->prepare("SELECT nome_empresa, id_categoria FROM nif_empresas WHERE nif = ? LIMIT 1");
    $stmt->execute([$nif]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        echo "   → Encontrado na BD: " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "   → Não encontrado na BD\n";
    }
}

echo "\n";
?>
