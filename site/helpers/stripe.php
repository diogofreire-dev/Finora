<?php
// site/helpers/stripe.php
// Pedidos à API do Stripe sem SDK: são poucos (checkout, portal, ler/cancelar subscrição) e assim
// fica à vista o que o SDK faz por nós, incluindo a verificação da assinatura dos webhooks.
// A chave secreta fica em config/stripe.php (fora do git) e nunca sai do servidor.
require_once __DIR__ . '/plan.php';

if (is_file(__DIR__ . '/../../config/stripe.php')) require_once __DIR__ . '/../../config/stripe.php';

const STRIPE_API = 'https://api.stripe.com/v1';
const STRIPE_SIGNATURE_TOLERANCE = 300; // segundos: um evento assinado há mais tempo é recusado (replay)

function stripe_configured(): bool {
    return defined('STRIPE_SECRET_KEY') && preg_match('/^(sk|rk)_(test|live)_\w{10,}$/', STRIPE_SECRET_KEY)
        && defined('STRIPE_WEBHOOK_SECRET') && str_starts_with(STRIPE_WEBHOOK_SECRET, 'whsec_');
}

function stripe_test_mode(): bool {
    return defined('STRIPE_SECRET_KEY') && str_contains(STRIPE_SECRET_KEY, '_test_');
}

/**
 * Pedido à API. $params vai como formulário (é o formato do Stripe): ['a' => ['b' => 1]] → a[b]=1.
 * Devolve o JSON da resposta; lança RuntimeException se o Stripe responder com erro.
 * $idempotencyKey: o Stripe devolve a mesma resposta se o pedido for repetido (ex.: duplo clique).
 */
function stripe_request(string $method, string $path, array $params = [], ?string $idempotencyKey = null): array {
    if (!defined('STRIPE_SECRET_KEY')) throw new RuntimeException('Stripe não configurado');
    $url = STRIPE_API . $path;
    $ch  = curl_init();
    $headers = ['Authorization: Bearer ' . STRIPE_SECRET_KEY];
    if ($idempotencyKey) $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
    ];
    if ($method === 'GET') {
        if ($params) $url .= '?' . http_build_query($params);
    } else {
        $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
    }
    $opts[CURLOPT_URL] = $url;
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    $json = is_string($body) ? json_decode($body, true) : null;
    if ($code < 200 || $code >= 300 || !is_array($json)) {
        $msg = $json['error']['message'] ?? ($err ?: 'HTTP ' . $code);
        throw new RuntimeException("Stripe $method $path: $msg");
    }
    return $json;
}

/**
 * Confirma que o pedido veio mesmo do Stripe. O cabeçalho Stripe-Signature traz
 * t=<timestamp>,v1=<HMAC-SHA256 de "t.corpo" com o segredo do webhook>.
 * Sem esta verificação, qualquer pessoa podia mandar um "pagamento concluído" falso e ganhar o Pro.
 */
function stripe_verify_signature(string $payload, string $header, string $secret, ?int $now = null): bool {
    $timestamp = null; $signatures = [];
    foreach (explode(',', $header) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 't' && ctype_digit($v)) $timestamp = (int) $v;
        if ($k === 'v1' && $v !== '') $signatures[] = $v;
    }
    if ($timestamp === null || !$signatures) return false;
    if (abs(($now ?? time()) - $timestamp) > STRIPE_SIGNATURE_TOLERANCE) return false;

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) return true;   // comparação em tempo constante
    }
    return false;
}

// Endereço base da app para os links de volta do Stripe. Usa o APP_URL da configuração quando existe
// (o cabeçalho Host vem do cliente e não deve decidir para onde o Stripe manda o utilizador).
function app_base_url(): string {
    if (!defined('APP_URL') && is_file(__DIR__ . '/../../config/brevo.php')) require_once __DIR__ . '/../../config/brevo.php';
    if (defined('APP_URL')) return rtrim(APP_URL, '/');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . site_base_path();
}

/**
 * Guarda o estado de uma subscrição do Stripe na conta certa.
 * A conta vem do customer já ligado a ela; só se o customer for novo é que se usa o user_id
 * que a própria Finora pôs nos metadados ao criar o checkout.
 */
function stripe_store_subscription(PDO $pdo, array $sub, ?int $uidHint = null): ?int {
    $customer = is_array($sub['customer'] ?? null) ? ($sub['customer']['id'] ?? null) : ($sub['customer'] ?? null);
    if (!$customer || empty($sub['id'])) return null;

    $stmt = $pdo->prepare('SELECT user_id FROM subscriptions WHERE stripe_customer_id = :c');
    $stmt->execute([':c' => $customer]);
    $uid = $stmt->fetchColumn();
    if ($uid === false) {
        $uid = $uidHint ?: (int) ($sub['metadata']['user_id'] ?? 0);
        if ($uid <= 0) return null;
        $exists = $pdo->prepare('SELECT 1 FROM users WHERE id = :u');
        $exists->execute([':u' => $uid]);
        if (!$exists->fetchColumn()) return null;   // conta apagada entretanto
    }
    $uid = (int) $uid;

    // Nas versões recentes da API o fim do período está em cada item da subscrição
    $end = $sub['current_period_end'] ?? ($sub['items']['data'][0]['current_period_end'] ?? null);
    $pdo->prepare('INSERT INTO subscriptions (user_id, stripe_customer_id, stripe_subscription_id, status, current_period_end, cancel_at_period_end)
                   VALUES (:u, :c, :s, :st, :e, :x)
                   ON DUPLICATE KEY UPDATE stripe_customer_id = VALUES(stripe_customer_id), stripe_subscription_id = VALUES(stripe_subscription_id),
                       status = VALUES(status), current_period_end = VALUES(current_period_end), cancel_at_period_end = VALUES(cancel_at_period_end)')
        ->execute([':u' => $uid, ':c' => $customer, ':s' => $sub['id'], ':st' => substr((string) ($sub['status'] ?? 'none'), 0, 32),
                   ':e' => $end ? date('Y-m-d H:i:s', (int) $end) : null, ':x' => !empty($sub['cancel_at_period_end']) ? 1 : 0]);
    return $uid;
}

// Lê a subscrição ao Stripe (estado atual, não o do evento) e guarda-a.
// Os eventos podem chegar fora de ordem; perguntar o estado atual evita guardar um estado antigo.
function stripe_sync_subscription(PDO $pdo, string $subscriptionId, ?int $uidHint = null): ?int {
    if (!preg_match('/^sub_\w+$/', $subscriptionId)) return null;
    return stripe_store_subscription($pdo, stripe_request('GET', '/subscriptions/' . $subscriptionId), $uidHint);
}
