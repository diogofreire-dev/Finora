<?php
/**
 * get_company_by_nif.php — devolve o nome da empresa de um NIF (usado no scan de faturas)
 */
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/nif_lookup.php';

header('Content-Type: application/json');

// Cada pesquisa pode ir a um site externo (nif.pt tem uma quota pequena), por isso
// limitamos quantas uma sessão pode fazer: 30 em cada 10 minutos.
const NIF_LOOKUPS_MAX    = 30;
const NIF_LOOKUPS_WINDOW = 600;

// Categorias antigas da base local (por número) → nome da categoria na Finora
const NIF_CATEGORY_NAMES = [1 => 'Compras', 2 => 'Alimentação', 3 => 'Transporte', 4 => 'Saúde',
                            5 => 'Entretenimento', 6 => 'Educação', 7 => 'Casa', 8 => 'Outros'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['found' => false, 'error' => 'method_not_allowed']);
    exit;
}

$nif = trim($_GET['nif'] ?? '');
if (!preg_match('/^\d{9}$/', $nif)) {
    echo json_encode(['found' => false, 'error' => 'nif_invalido']);
    exit;
}

$now = time();
$recent = array_filter($_SESSION['nif_lookups'] ?? [], fn($t) => $t > $now - NIF_LOOKUPS_WINDOW);
if (count($recent) >= NIF_LOOKUPS_MAX) {
    http_response_code(429);
    echo json_encode(['found' => false, 'error' => 'too_many_requests']);
    exit;
}
$recent[] = $now;
$_SESSION['nif_lookups'] = array_values($recent);
session_write_close(); // a pesquisa externa pode demorar; não bloqueia outros pedidos da sessão

$result = nif_lookup($nif, (int) $_SESSION['user_id']);

echo json_encode([
    'found'     => $result['success'],
    'nome'      => $result['success'] ? $result['nome'] : '',
    'categoria' => $result['categoria'] ?? (NIF_CATEGORY_NAMES[(int) ($result['id_categoria'] ?? 0)] ?? null),
    'source'    => $result['source'] ?? 'none',
]);
