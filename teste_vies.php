<?php
// teste_vies.php - Teste simples da API VIES
$nif = '500961353'; // NIF do Continente

echo "<h1>Teste API VIES</h1>";
echo "<p>Testando NIF: $nif</p>";

$url = "https://ec.europa.eu/taxation_customs/vies/rest-api/ms/PT/vat/" . urlencode($nif);

echo "<h3>URL:</h3>";
echo "<code>$url</code><br><br>";

echo "<h3>1. Teste com file_get_contents:</h3>";
$response = @file_get_contents($url, false, stream_context_create([
    'http' => [
        'timeout' => 10,
        'header' => "Accept: application/json\r\nUser-Agent: FreeCard/1.0\r\n"
    ]
]));

if ($response === false) {
    echo "<span style='color: red;'>❌ FALHA - file_get_contents bloqueado ou timeout</span><br>";
    echo "Erro: " . error_get_last()['message'] ?? 'Desconhecido';
} else {
    echo "<span style='color: green;'>✅ SUCESSO - Resposta recebida</span><br>";
    $data = json_decode($response, true);
    echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
}

echo "<h3>2. Teste com cURL:</h3>";
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: FreeCard/1.0'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response2 = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        echo "<span style='color: red;'>❌ FALHA cURL - $err</span><br>";
    } else {
        echo "<span style='color: green;'>✅ SUCESSO cURL - HTTP $httpCode</span><br>";
        $data2 = json_decode($response2, true);
        echo "<pre>" . json_encode($data2, JSON_PRETTY_PRINT) . "</pre>";
    }
} else {
    echo "<span style='color: red;'>❌ cURL não disponível</span><br>";
}

echo "<h3>3. Teste direto no browser:</h3>";
echo "<a href='$url' target='_blank'>Clique aqui para testar no browser</a><br><br>";

echo "<h3>4. Configuração PHP:</h3>";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? '✅ Ativado' : '❌ Desativado') . "<br>";
echo "cURL disponível: " . (function_exists('curl_init') ? '✅ Sim' : '❌ Não') . "<br>";
?>