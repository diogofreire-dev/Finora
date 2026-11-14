<?php
// tests/stripe_signature_test.php — a verificação da assinatura dos webhooks do Stripe.
// É ela que impede alguém de mandar um "pagamento concluído" falso e ganhar o Pro de graça.
// Uso: php tests/stripe_signature_test.php   (sai com código 1 se algum caso falhar)
require_once __DIR__ . '/../site/helpers/stripe.php';

$secret  = 'whsec_teste_' . bin2hex(random_bytes(8));
$payload = '{"id":"evt_123","type":"customer.subscription.updated"}';
$now     = 1_800_000_000;
$sign    = fn(string $body, int $t, ?string $key = null) => 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $body, $key ?? $secret);

$cases = [
    'assinatura certa'                      => [true,  $payload, $sign($payload, $now)],
    'várias v1, uma certa (troca de segredo)'=> [true,  $payload, 't=' . $now . ',v1=' . str_repeat('0', 64) . ',v1=' . hash_hmac('sha256', $now . '.' . $payload, $secret)],
    'corpo alterado'                        => [false, str_replace('updated', 'deleted', $payload), $sign($payload, $now)],
    'segredo errado'                        => [false, $payload, $sign($payload, $now, 'whsec_outro')],
    'evento antigo (replay, 10 min)'        => [false, $payload, $sign($payload, $now - 600)],
    'do futuro (10 min)'                    => [false, $payload, $sign($payload, $now + 600)],
    'sem v1'                                => [false, $payload, 't=' . $now],
    'sem t'                                 => [false, $payload, 'v1=' . hash_hmac('sha256', $now . '.' . $payload, $secret)],
    'só v0 (esquema antigo, não aceite)'    => [false, $payload, 't=' . $now . ',v0=' . hash_hmac('sha256', $now . '.' . $payload, $secret)],
    'cabeçalho vazio'                       => [false, $payload, ''],
];

$fail = 0;
foreach ($cases as $name => [$want, $body, $header]) {
    $got = stripe_verify_signature($body, $header, $secret, $now);
    echo ($got === $want ? '✓ ' : '✗ ') . $name . "\n";
    if ($got !== $want) $fail = 1;
}

// Estados do Stripe que dão Pro
foreach (['active' => true, 'trialing' => true, 'past_due' => true, 'comp' => true,
          'canceled' => false, 'unpaid' => false, 'incomplete' => false, '' => false] as $st => $want) {
    if (plan_status_is_pro($st === '' ? null : $st) !== $want) { echo "✗ estado $st\n"; $fail = 1; }
}
echo $fail ? "Falhou.\n" : "Tudo certo.\n";
exit($fail);
