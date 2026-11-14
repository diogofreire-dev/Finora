<?php
// site/webhooks/stripe.php — o Stripe avisa aqui quando uma subscrição começa, muda ou acaba.
// Não usa sessão nem CSRF (quem chama é o Stripe, não o browser): a prova de origem é a assinatura.
// Responde 2xx quando o evento fica tratado; com outro código o Stripe volta a tentar mais tarde.
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/stripe.php';

header('Content-Type: application/json');

function webhook_out(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['result' => $msg]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') webhook_out(405, 'method_not_allowed');
if (!stripe_configured()) webhook_out(503, 'not_configured');

$payload = file_get_contents('php://input', false, null, 0, 512 * 1024) ?: '';
$sig     = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
if (!stripe_verify_signature($payload, $sig, STRIPE_WEBHOOK_SECRET)) webhook_out(400, 'bad_signature');

$event = json_decode($payload, true);
$id    = $event['id'] ?? '';
$type  = (string) ($event['type'] ?? '');
$obj   = $event['data']['object'] ?? [];
if (!is_string($id) || !preg_match('/^evt_\w+$/', $id) || !is_array($obj)) webhook_out(400, 'bad_event');

// Já tratado? (o Stripe pode entregar o mesmo evento mais de uma vez)
$seen = $pdo->prepare('SELECT 1 FROM stripe_events WHERE id = :id');
$seen->execute([':id' => $id]);
if ($seen->fetchColumn()) webhook_out(200, 'duplicate');

try {
    switch ($type) {
        case 'checkout.session.completed':
            if (($obj['mode'] ?? '') === 'subscription' && !empty($obj['subscription'])) {
                $uid = ctype_digit((string) ($obj['client_reference_id'] ?? '')) ? (int) $obj['client_reference_id'] : null;
                stripe_sync_subscription($pdo, (string) $obj['subscription'], $uid);
            }
            break;
        case 'customer.subscription.created':
        case 'customer.subscription.updated':
        case 'customer.subscription.deleted':
            if (!empty($obj['id'])) stripe_sync_subscription($pdo, (string) $obj['id']);
            break;
        default:
            // Outros eventos não interessam; responder 200 para o Stripe não insistir
    }
    $pdo->prepare('INSERT IGNORE INTO stripe_events (id, type) VALUES (:id, :t)')->execute([':id' => $id, ':t' => substr($type, 0, 100)]);
} catch (Throwable $e) {
    error_log('[stripe webhook] ' . $type . ': ' . $e->getMessage());
    webhook_out(500, 'error');   // o Stripe volta a tentar
}
webhook_out(200, 'ok');
