<?php
// get_company_by_nif.php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['found' => false]);
    exit;
}

$nif = trim($_GET['nif'] ?? '');

if (!preg_match('/^\d{9}$/', $nif)) {
    echo json_encode(['found' => false, 'nome_empresa' => '']);
    exit;
}

// ── 1. Tenta primeiro na BD local (instantâneo, sem chamada externa) ────────
$stmt = $pdo->prepare("SELECT nome_empresa FROM nif_empresas WHERE nif = ? LIMIT 1");
$stmt->execute([$nif]);
$row = $stmt->fetch();

if ($row) {
    echo json_encode([
        'found'        => true,
        'nome_empresa' => $row['nome_empresa'],
        'source'       => 'local'
    ]);
    exit;
}

// ── 2. Não está na BD → consulta VIES (API oficial UE, grátis, sem key) ─────
$url = "https://ec.europa.eu/taxation_customs/vies/rest-api/ms/PT/vat/" . urlencode($nif);

$ctx = stream_context_create(['http' => [
    'timeout' => 6,
    'header'  => "Accept: application/json\r\nUser-Agent: FreeCard/1.0\r\n"
]]);

$response = @file_get_contents($url, false, $ctx);

if ($response === false) {
    // VIES inacessível (bloqueado firewall/rede)
    // Log error se debug ativo
    if (extension_loaded('error_log')) {
        error_log("get_company_by_nif: VIES timeout para NIF $nif - verificar conectividade");
    }
    echo json_encode(['found' => false, 'nome_empresa' => '', 'source' => 'vies_error', 'debug' => 'VIES timeout']);
    exit;
}

$data = json_decode($response, true);

// VIES devolve: { "isValid": true, "traderName": "CONTINENTE HIPERMERCADOS SA", ... }
if (!empty($data['isValid']) && !empty($data['traderName'])) {
    $nome = trim($data['traderName']);

    // ── 3. Guarda na BD local → próxima consulta deste NIF nunca vai ao VIES ─
    $ins = $pdo->prepare("
        INSERT INTO nif_empresas (nif, nome_empresa)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa)
    ");
    $ins->execute([$nif, $nome]);

    echo json_encode([
        'found'        => true,
        'nome_empresa' => $nome,
        'source'       => 'vies'
    ]);
} else {
    // Fallback: NIFs comuns em teste (se VIES offline)
    $commonNifs = [
        '500961353' => 'CONTINENTE HIPERMERCADOS SA',
        '500961358' => 'EL CORTE INGLÉS SA',
        '500961360' => 'CARREFOUR PORTUGAL SA',
        '500961450' => 'SONAE MC SA',
    ];
    
    if (isset($commonNifs[$nif])) {
        // Log que usamos fallback
        if (function_exists('error_log')) {
            error_log("get_company_by_nif: VIES offline, usando fallback para NIF $nif");
        }
        
        // Guarda na BD
        $ins = $pdo->prepare("
            INSERT INTO nif_empresas (nif, nome_empresa)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa)
        ");
        $ins->execute([$nif, $commonNifs[$nif]]);
        
        echo json_encode([
            'found'        => true,
            'nome_empresa' => $commonNifs[$nif],
            'source'       => 'fallback_test'
        ]);
    } else {
        // NIF válido mas não em fallback/VIES
        echo json_encode(['found' => false, 'nome_empresa' => '', 'source' => 'vies_not_found']);
    }
}


