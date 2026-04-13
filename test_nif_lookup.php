<?php
/**
 * NIF Lookup Test Suite - Run directly in browser
 */
require_once 'config/db.php';
require_once 'site/helpers/nif_lookup.php';

echo '<h1>🧪 NIF Lookup Tests</h1>';

$test_nifs = ['500961353', '501907372', '999999999']; // Continente, McD, Invalid

foreach ($test_nifs as $nif) {
    echo "<h3>Test NIF: <code>$nif</code></h3>";
    $result = nif_lookup($nif);
    echo '<pre>' . json_encode($result, JSON_PRETTY_PRINT) . '</pre>';
    
    // Endpoint test
    echo "<p><a href='site/pages/get_company_by_nif.php?nif=$nif'>GET Endpoint</a></p>";
    echo "<hr>";
}

// Manual save test form
echo '<h3>Manual Save Test</h3>
<form method="POST" action="site/pages/save_company_nif.php">
    NIF: <input name="nif" value="123456789" pattern="\\d{9}" required><br>
    Nome: <input name="nome_empresa" value="Test Empresa Manual" required><br>
    Categoria: <input name="id_categoria" type="number" value="1"><br>
    <button>Save & Test</button>
</form>';

echo '<script>
fetch("site/pages/get_company_by_nif.php?nif=500961353").then(r=>r.json()).then(console.log);
</script>';
?>

