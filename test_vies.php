<?php
// Teste rápido da integração VIES
require_once 'config/db.php';

echo "=== TESTE VIES API E BD LOCAL ===\n\n";

// 1. Verificar tabela BD
echo "1. Verificando tabela nif_empresas...\n";
try {
    $stmt = $pdo->query("DESCRIBE nif_empresas");
    $cols = $stmt->fetchAll();
    echo "   ✓ Tabela existe com " . count($cols) . " colunas\n\n";
} catch (Exception $e) {
    echo "   ✗ ERRO: " . $e->getMessage() . "\n\n";
    echo "   Criando tabela...\n";
    $pdo->exec("
        CREATE TABLE nif_empresas (
            nif VARCHAR(9) PRIMARY KEY,
            nome_empresa VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "   ✓ Tabela criada\n\n";
}

// 2. Testar BD local
echo "2. Testando BD local (sem API)...\n";
$test_nif = "500961353"; // Continente
$stmt = $pdo->prepare("SELECT nome_empresa FROM nif_empresas WHERE nif = ? LIMIT 1");
$stmt->execute([$test_nif]);
$row = $stmt->fetch();
if ($row) {
    echo "   ✓ NIF $test_nif encontrado: " . $row['nome_empresa'] . "\n\n";
} else {
    echo "   - NIF $test_nif não está em cache (normal primeira vez)\n\n";
}

// 3. Testar VIES API
echo "3. Testando API VIES da UE...\n";
$url = "https://ec.europa.eu/taxation_customs/vies/rest-api/ms/PT/vat/" . urlencode($test_nif);
echo "   URL: " . substr($url, 0, 60) . "...\n";

$ctx = stream_context_create(['http' => [
    'timeout' => 6,
    'header'  => "Accept: application/json\r\nUser-Agent: FreeCard/1.0\r\n"
]]);

$response = @file_get_contents($url, false, $ctx);

if ($response === false) {
    echo "   ✗ VIES INACESSÍVEL (timeout ou erro de rede)\n\n";
} else {
    echo "   ✓ Resposta recebida\n";
    $data = json_decode($response, true);
    
    if (!empty($data['isValid']) && !empty($data['traderName'])) {
        echo "   ✓ NIF válido: " . $data['traderName'] . "\n\n";
        
        // Guardar na BD
        $ins = $pdo->prepare("
            INSERT INTO nif_empresas (nif, nome_empresa)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa)
        ");
        $ins->execute([$test_nif, $data['traderName']]);
        echo "   ✓ Guardado na BD\n\n";
    } else {
        echo "   ✗ Resposta inválida ou NIF não registado\n";
        echo "   Resposta: " . substr($response, 0, 200) . "...\n\n";
    }
}

// 4. Testar endpoint get_company_by_nif.php
echo "4. Testando endpoint get_company_by_nif.php...\n";
$test_url = "http://localhost:8000/site/pages/get_company_by_nif.php?nif=" . $test_nif;
echo "   URL: " . $test_url . "\n";

$response = @file_get_contents($test_url);
if ($response === false) {
    echo "   ✗ FALHA ao chamar endpoint (servidor não está running?)\n";
    echo "   Tente: php -S localhost:8000\n";
} else {
    echo "   ✓ Resposta recebida:\n";
    $data = json_decode($response, true);
    echo "   " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== FIM TESTE ===\n";
?>
