<?php
// site/helpers/plan.php
// Plano de cada conta: grátis ou Finora Pro. O auth.php lê o estado da subscrição na mesma consulta
// que confirma a sessão e define FINORA_PRO; aqui ficam as regras do que cada plano pode fazer.
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/paths.php';

const FREE_CARD_LIMIT  = 2;     // cartões ativos no plano grátis (os que já existem nunca são apagados)
const PRO_PRICE_CENTS  = 299;   // 2,99 € por mês, IVA incluído
const PRO_CURRENCY     = 'EUR'; // o preço é sempre em euros, seja qual for a moeda da conta

// Estados do Stripe que dão acesso ao Pro. past_due: o pagamento falhou e o Stripe está a tentar outra vez;
// o Pro continua durante essas tentativas e acaba quando a subscrição passa a canceled/unpaid.
// comp: Pro dado à mão (ex.: a conta demo), sem Stripe.
const PRO_STATUSES = ['active', 'trialing', 'past_due', 'comp'];

function plan_status_is_pro(?string $status): bool {
    return in_array($status, PRO_STATUSES, true);
}

function is_pro(): bool {
    return defined('FINORA_PRO') && FINORA_PRO === true;
}

// O que o Pro desbloqueia: chave → [ícone, nome, descrição]. A chave é a que as páginas passam a require_pro().
function pro_features(): array {
    return [
        'cards'       => ['credit-card-2-front', t('Cartões ilimitados'), t('No plano grátis tens até {n} cartões ativos.', ['n' => FREE_CARD_LIMIT])],
        'ai'          => ['stars',               t('Assistente IA'),       t('Pergunta o que quiseres sobre os teus gastos, com as tuas contas reais.')],
        'scan'        => ['qr-code-scan',        t('Ler faturas por QR'),  t('Aponta a câmara ao código da fatura e a despesa fica registada.')],
        'investments' => ['graph-up-arrow',      t('Investimentos'),       t('A tua carteira de ações, ETFs, cripto e PPR com o valor atualizado.')],
        'bolsa'       => ['activity',            t('Bolsa'),               t('Pesquisa títulos e acompanha as cotações.')],
        'reports'     => ['file-earmark-pdf',    t('Relatórios em PDF'),   t('Relatório completo e resumo, com os filtros que escolheres.')],
    ];
}

// Para páginas HTML: sem Pro, vai para a página do Finora Pro, que explica o que esta funcionalidade faz.
function require_pro(string $feature): void {
    if (is_pro()) return;
    $key = array_key_exists($feature, pro_features()) ? $feature : '';
    header('Location: ' . site_base_path() . '/pages/pro.php' . ($key !== '' ? '?feature=' . $key : ''));
    exit;
}

// Para pedidos JSON (fetch): 402 Payment Required, com uma mensagem que a página pode mostrar.
function require_pro_json(): void {
    if (is_pro()) return;
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(402);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'pro_required', 'message' => t('Esta funcionalidade é do Finora Pro.')], JSON_UNESCAPED_UNICODE);
    exit;
}

function active_card_count(PDO $pdo, int $uid, ?int $exceptCardId = null): int {
    $sql = 'SELECT COUNT(*) FROM cards WHERE user_id = :u AND active = 1';
    $params = [':u' => $uid];
    if ($exceptCardId) { $sql .= ' AND id <> :c'; $params[':c'] = $exceptCardId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

// Pode ter mais um cartão ativo? ($exceptCardId: o cartão que está a ser reativado não conta para o total)
function can_activate_card(PDO $pdo, int $uid, ?int $exceptCardId = null): bool {
    return is_pro() || active_card_count($pdo, $uid, $exceptCardId) < FREE_CARD_LIMIT;
}

function card_limit_message(): string {
    return t('O plano grátis tem até {n} cartões ativos. Desativa um ou passa para o Finora Pro.', ['n' => FREE_CARD_LIMIT]);
}

// Linha da conta na tabela subscriptions (ou null se nunca passou pelo Stripe)
function user_subscription(PDO $pdo, int $uid): ?array {
    $stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE user_id = :u');
    $stmt->execute([':u' => $uid]);
    return $stmt->fetch() ?: null;
}
