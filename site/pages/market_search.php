<?php
// site/pages/market_search.php — sugestões de símbolos para o formulário de investimentos (JSON).
// Só para utilizadores com sessão; limite de pedidos em market_search(); só GET.
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/market.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(405); echo '[]'; exit; }
echo json_encode(market_search($pdo, (int) $_SESSION['user_id'], (string) ($_GET['q'] ?? '')), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
