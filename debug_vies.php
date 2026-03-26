<?php
// debug_vies.php — apaga depois de testar
$nif = '501907372'; // Continente — deve retornar nome

echo "<h3>1. Teste file_get_contents</h3>";
$url = "https://ec.europa.eu/taxation_customs/vies/rest-api/ms/PT/vat/" . $nif;
$res = @file_get_contents($url);
if ($res === false) {
    echo "❌ file_get_contents bloqueado (allow_url_fopen=off ou firewall)<br>";
} else {
    echo "✅ file_get_contents ok<br>";
    $data = json_decode($res, true);
    echo "<pre>" . print_r($data, true) . "</pre>";
}

echo "<h3>2. Teste cURL</h3>";
if (!function_exists('curl_init')) {
    echo "❌ cURL não disponível<br>";
} else {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res2 = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) {
        echo "❌ cURL erro: $err<br>";
    } else {
        echo "✅ cURL ok<br>";
        $data2 = json_decode($res2, true);
        echo "<pre>" . print_r($data2, true) . "</pre>";
    }
}

echo "<h3>3. Tabela nif_empresas</h3>";
require_once __DIR__ . '/../../config/db.php';
try {
    $r = $pdo->query("SELECT COUNT(*) FROM nif_empresas");
    echo "✅ Tabela existe — " . $r->fetchColumn() . " registos<br>";
} catch (Exception $e) {
    echo "❌ Tabela não existe ou erro: " . $e->getMessage() . "<br>";
}
