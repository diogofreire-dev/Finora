<?php
// teste_lookup.php - Teste direto da função lookupNIF
require_once 'config/db.php';

$nif = isset($_GET['nif']) ? trim($_GET['nif']) : '500961353'; // Continente por padrão

echo "<h1>Teste Lookup NIF</h1>";
echo "<form method='get'>";
echo "<input type='text' name='nif' value='$nif' placeholder='Digite NIF'>";
echo "<button type='submit'>Testar</button>";
echo "</form>";

echo "<h3>Testando NIF: $nif</h3>";

// Simular a função lookupNIF
function testarLookupNIF($nif) {
    global $pdo;

    if (!preg_match('/^\d{9}$/', $nif)) {
        return ['success' => false, 'message' => 'NIF inválido'];
    }

    // Verificar cache local
    $stmt = $pdo->prepare("SELECT nome_empresa, fonte FROM nif_empresas_cache WHERE nif = ? LIMIT 1");
    $stmt->execute([$nif]);
    $cache = $stmt->fetch();

    if ($cache) {
        return [
            'success' => true,
            'nome' => $cache['nome_empresa'],
            'fonte' => 'cache (' . $cache['fonte'] . ')'
        ];
    }

    // Consultar VIES
    $viesResult = testarConsultarVIES($nif);

    if ($viesResult['success']) {
        // Guardar no cache
        $stmt = $pdo->prepare("INSERT INTO nif_empresas_cache (nif, nome_empresa, fonte) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa)");
        $stmt->execute([$nif, $viesResult['nome'], $viesResult['fonte'] ?? 'vies']);

        return [
            'success' => true,
            'nome' => $viesResult['nome'],
            'fonte' => $viesResult['fonte'] ?? 'vies'
        ];
    } else {
        return ['success' => false, 'message' => 'Empresa não encontrada', 'error' => $viesResult['error'] ?? 'unknown'];
    }
}

function testarConsultarVIES($nif) {
    $url = "https://ec.europa.eu/taxation_customs/vies/rest-api/ms/PT/vat/" . urlencode($nif);

    echo "<h4>URL VIES:</h4><code>$url</code><br><br>";

    // Primeiro tentar com cURL
    if (function_exists('curl_init')) {
        echo "<h4>Testando com cURL...</h4>";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: FreeCard/1.0'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        echo "HTTP Code: $httpCode<br>";
        if ($err) {
            echo "<span style='color: red;'>Erro cURL: $err</span><br>";
            return testarAPIPortugal($nif);
        }

        if ($httpCode !== 200) {
            echo "<span style='color: red;'>Erro HTTP: $httpCode</span><br>";
            return testarAPIPortugal($nif);
        }

        echo "<span style='color: green;'>✅ cURL funcionou!</span><br>";
    } else {
        echo "<h4>cURL não disponível, tentando file_get_contents...</h4>";
        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'header' => "Accept: application/json\r\nUser-Agent: FreeCard/1.0\r\n"
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            echo "<span style='color: red;'>Erro file_get_contents</span><br>";
            return testarAPIPortugal($nif);
        }
        echo "<span style='color: green;'>✅ file_get_contents funcionou!</span><br>";
    }

    echo "<h4>Resposta:</h4><pre>$response</pre>";

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<span style='color: red;'>Erro JSON: " . json_last_error_msg() . "</span><br>";
        return testarAPIPortugal($nif);
    }

    if (isset($data['isValid']) && $data['isValid'] && isset($data['traderName'])) {
        return [
            'success' => true,
            'nome' => trim($data['traderName']),
            'fonte' => 'vies'
        ];
    }

    echo "<span style='color: orange;'>VIES não encontrou, tentando fallback...</span><br>";
    return testarAPIPortugal($nif);
}

function testarAPIPortugal($nif) {
    echo "<h4>Testando API Portugal (fallback)...</h4>";

    $nifsConhecidos = [
        '500961353' => 'CONTINENTE HIPERMERCADOS SA',
        '501907372' => 'MCDONALD\'S PORTUGAL LDA',
        '500961358' => 'EL CORTE INGLÉS SA',
        '500961360' => 'CARREFOUR PORTUGAL SA',
        '500961450' => 'SONAE MC SA',
        '503157082' => 'JUMBO - SUPERMERCADOS LDA',
        '509801534' => 'REPSOL PORTUGAL SA',
        '510000001' => 'GALP ENERGIA SA',
        '515000001' => 'EDP COMERCIAL SA'
    ];

    if (isset($nifsConhecidos[$nif])) {
        echo "<span style='color: green;'>✅ Encontrado no fallback!</span><br>";
        return [
            'success' => true,
            'nome' => $nifsConhecidos[$nif],
            'fonte' => 'fallback'
        ];
    }

    echo "<span style='color: red;'>❌ Não encontrado em lugar nenhum</span><br>";
    return ['success' => false, 'error' => 'not_found'];
}

// Executar teste
$resultado = testarLookupNIF($nif);

echo "<h3>Resultado Final:</h3>";
echo "<pre>" . json_encode($resultado, JSON_PRETTY_PRINT) . "</pre>";
?>