<?php
// site/pages/debug_company_lookup.php
// Arquivo para debug do lookup de empresa

require_once __DIR__ . '/../../config/db.php';

echo "<h2>🔍 Debug: Lookup de Empresa por NIF</h2>";
echo "<hr>";

// Teste 1: Verificar se a tabela existe
echo "<h3>1️⃣ Verificar tabela nif_empresas</h3>";
try {
    $stmt = $pdo->query("DESCRIBE nif_empresas");
    $cols = $stmt->fetchAll();
    echo "<pre>";
    print_r($cols);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erro: " . $e->getMessage() . "</p>";
}

// Teste 2: Dados na BD
echo "<h3>2️⃣ Dados na BD (últimas 5 linhas)</h3>";
try {
    $stmt = $pdo->query("SELECT * FROM nif_empresas ORDER BY nif DESC LIMIT 5");
    $rows = $stmt->fetchAll();
    echo "<pre>";
    print_r($rows);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Erro: " . $e->getMessage() . "</p>";
}

// Teste 3: Simular chamada GET
echo "<h3>3️⃣ Simular chamada GET para get_company_by_nif.php</h3>";
if ($_GET['test_nif'] ?? false) {
    $nif = trim($_GET['test_nif']);
    if (preg_match('/^\d{9}$/', $nif)) {
        echo "<p>Testando NIF: <strong>$nif</strong></p>";
        
        // Buscar na BD local
        $stmt = $pdo->prepare("SELECT nome_empresa, id_categoria FROM nif_empresas WHERE nif = ? LIMIT 1");
        $stmt->execute([$nif]);
        $row = $stmt->fetch();
        
        if ($row) {
            $response = [
                'found'        => true,
                'nome_empresa' => $row['nome_empresa'],
                'id_categoria' => $row['id_categoria'],
                'source'       => 'local'
            ];
            echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        } else {
            echo "<p>❌ NIF não encontrado localmente. Tentando VIES...</p>";
            
            // Tentar VIES
            $url = "https://ec.europa.eu/taxation_customs/vies/rest-api/ms/PT/vat/" . urlencode($nif);
            $ctx = stream_context_create(['http' => ['timeout' => 6, 'header' => "Accept: application/json\r\n"]]);
            $res = @file_get_contents($url, false, $ctx);
            
            if ($res) {
                $data = json_decode($res, true);
                echo "<p>Resposta VIES:</p>";
                echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            } else {
                echo "<p style='color:red;'>❌ Erro ao conectar VIES</p>";
            }
        }
    } else {
        echo "<p style='color:red;'>❌ NIF inválido</p>";
    }
}

echo "<hr>";
echo "<h3>📝 Testar NIF</h3>";
echo "<form method='get'>";
echo "<input type='text' name='test_nif' placeholder='Exemplo: 123456789' maxlength='9' required>";
echo "<button type='submit' class='btn btn-primary'>Testar</button>";
echo "</form>";
?>
