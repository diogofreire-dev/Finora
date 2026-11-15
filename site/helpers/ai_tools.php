<?php
// site/helpers/ai_tools.php
// Ferramentas que o Assistente IA pode pedir para responder com números reais.
//
// Regras de segurança (ver docs/SEGURANCA_IA.md):
//  1. Só leitura. Nenhuma ferramenta escreve, apaga, envia emails ou chama outros sites: mesmo que o
//     modelo seja manipulado por texto escondido numa transação, não consegue fazer nada na conta.
//  2. A conta vem sempre da sessão ($uid), nunca dos argumentos. O modelo não tem como pedir dados
//     de outra pessoa: nenhum esquema tem user_id e argumentos desconhecidos são recusados.
//  3. Argumentos validados com regras fixas (tipos, intervalos, listas fechadas) e consultas preparadas.
//  4. Texto escrito pelo utilizador (descrições, nomes) volta limpo e curto, e o modelo é avisado de
//     que é só informação. Os valores vêm já formatados: o modelo copia-os, não faz contas.
require_once __DIR__ . '/format.php';
require_once __DIR__ . '/categories.php';
require_once __DIR__ . '/savings.php';

const AI_TOOL_MAX_ITEMS   = 15;    // transações devolvidas de cada vez
const AI_TOOL_MAX_DAYS    = 731;   // período máximo de um resumo (2 anos)
const AI_TOOL_MAX_AMOUNT  = 1000000;
const AI_TOOL_MAX_MONTHS  = 24;    // até quando se pode simular uma compra

// Categorias que o modelo pode usar como filtro
function ai_categories(): array {
    return array_values(array_filter(array_keys(CATEGORY_STYLES), fn($c) => $c !== 'Sem Categoria'));
}

// Texto vindo do utilizador ou de fora (QR, importações): sem caracteres de controlo nem < >,
// numa só linha e curto. Assim não consegue fingir ser uma mensagem nova nem fechar blocos.
function ai_text(?string $s, int $max = 60): string {
    $s = preg_replace('/[\x00-\x1F\x7F<>]/u', ' ', (string) $s);
    return mb_substr(trim(preg_replace('/\s+/u', ' ', $s)), 0, $max);
}

// Esquemas no formato de "function calling" (OpenAI/Groq). Nenhum tem user_id.
function ai_tool_definitions(): array {
    $date = ['type' => 'string', 'description' => 'Data no formato AAAA-MM-DD'];
    $cats = ['type' => 'string', 'enum' => ai_categories()];
    $tool = fn(string $name, string $desc, array $props = [], array $req = []) => ['type' => 'function', 'function' => [
        'name' => $name, 'description' => $desc,
        'parameters' => ['type' => 'object', 'properties' => (object) $props, 'required' => $req, 'additionalProperties' => false],
    ]];
    return [
        $tool('spending_summary', 'Total gasto entre duas datas, por categoria, e comparação com o período anterior do mesmo tamanho.',
              ['from' => $date, 'to' => $date], ['from', 'to']),
        $tool('search_transactions', 'Procura transações por texto na descrição, categoria e/ou datas. Devolve no máximo ' . AI_TOOL_MAX_ITEMS . ', mais o total encontrado.',
              ['query' => ['type' => 'string', 'description' => 'Texto a procurar na descrição (máx. 40 caracteres)'],
               'category' => $cats, 'from' => $date, 'to' => $date,
               'sort' => ['type' => 'string', 'enum' => ['recent', 'largest']],
               'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => AI_TOOL_MAX_ITEMS]]),
        $tool('budgets_status', 'Orçamentos ativos: limite, quanto já foi gasto no período atual e quanto falta.'),
        $tool('goals_status', 'Metas de poupança: valor, já guardado, prazo, quanto é preciso por mês, e a média de gastos e o rendimento mensal.'),
        $tool('cards_status', 'Cartões ativos: limite, saldo usado, disponível e gasto deste mês.'),
        $tool('simulate_purchase', 'Simula uma compra num mês futuro: se o dinheiro que sobra por mês (depois das metas) chega até lá, e o efeito no orçamento da categoria.',
              ['amount' => ['type' => 'number', 'description' => 'Valor da compra'],
               'month' => ['type' => 'string', 'description' => 'Mês da compra, AAAA-MM'],
               'category' => $cats], ['amount', 'month']),
    ];
}

/**
 * Valida os argumentos de uma ferramenta. Devolve [args limpos, null] ou [null, mensagem de erro].
 * Os erros voltam ao modelo (não ao utilizador), para ele corrigir o pedido.
 */
function ai_validate_args(string $name, $raw): array {
    $defs = [];
    foreach (ai_tool_definitions() as $d) $defs[$d['function']['name']] = $d['function']['parameters'];
    if (!isset($defs[$name])) return [null, "ferramenta desconhecida: $name"];
    if ($raw === null || $raw === '' || $raw === []) $raw = [];
    if (!is_array($raw)) return [null, 'os argumentos têm de ser um objeto JSON'];

    $props = (array) $defs[$name]['properties'];
    foreach (array_keys($raw) as $k) {
        if (!array_key_exists($k, $props)) return [null, "argumento não permitido: " . ai_text((string) $k, 30)];
    }
    foreach ($defs[$name]['required'] as $k) {
        if (!array_key_exists($k, $raw)) return [null, "falta o argumento $k"];
    }

    $today = date('Y-m-d');
    $out = [];
    foreach ($raw as $k => $v) {
        $p = $props[$k];
        if (isset($p['enum'])) {
            if (!in_array($v, $p['enum'], true)) return [null, "$k inválido"];
            $out[$k] = $v;
        } elseif ($k === 'from' || $k === 'to') {
            $d = is_string($v) ? DateTime::createFromFormat('!Y-m-d', $v) : false;
            if (!$d || $d->format('Y-m-d') !== $v || $v < '2000-01-01') return [null, "$k tem de ser uma data AAAA-MM-DD"];
            if ($k === 'from' && $v > $today) return [null, 'o período ainda não começou: não há gastos registados no futuro'];
            $out[$k] = min($v, $today);   // o fim de um período que já começou fica em hoje
        } elseif ($k === 'month') {
            if (!is_string($v) || !preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $v)) return [null, 'month tem de ser AAAA-MM'];
            $diff = ((int) substr($v, 0, 4) - (int) date('Y')) * 12 + (int) substr($v, 5, 2) - (int) date('n');
            if ($diff < 0 || $diff > AI_TOOL_MAX_MONTHS) return [null, 'month tem de estar entre este mês e daqui a ' . AI_TOOL_MAX_MONTHS . ' meses'];
            $out[$k] = $v;
        } elseif ($k === 'amount') {
            if (!is_int($v) && !is_float($v)) return [null, 'amount tem de ser um número'];
            if ($v <= 0 || $v > AI_TOOL_MAX_AMOUNT) return [null, 'amount fora do intervalo'];
            $out[$k] = round((float) $v, 2);
        } elseif ($k === 'limit') {
            if (!is_int($v)) return [null, 'limit tem de ser um inteiro'];
            $out[$k] = max(1, min(AI_TOOL_MAX_ITEMS, $v));
        } elseif ($k === 'query') {
            if (!is_string($v)) return [null, 'query tem de ser texto'];
            $q = ai_text($v, 40);
            if ($q !== '') $out[$k] = $q;
        }
    }
    if (isset($out['from'], $out['to'])) {
        if ($out['from'] > $out['to']) return [null, 'from tem de ser antes de to'];
        if ((strtotime($out['to']) - strtotime($out['from'])) / 86400 > AI_TOOL_MAX_DAYS) return [null, 'período demasiado longo (máx. 2 anos)'];
    }
    return [$out, null];
}

// Corre uma ferramenta para a conta $uid. Devolve sempre um array (o erro também é informação para o modelo).
function ai_run_tool(PDO $pdo, int $uid, string $name, $rawArgs): array {
    [$args, $err] = ai_validate_args($name, $rawArgs);
    if ($err !== null) return ['error' => $err];
    try {
        switch ($name) {
            case 'spending_summary':    return ai_tool_spending($pdo, $uid, $args['from'], $args['to']);
            case 'search_transactions': return ai_tool_search($pdo, $uid, $args);
            case 'budgets_status':      return ai_tool_budgets($pdo, $uid);
            case 'goals_status':        return ai_tool_goals($pdo, $uid);
            case 'cards_status':        return ai_tool_cards($pdo, $uid);
            case 'simulate_purchase':   return ai_tool_simulate($pdo, $uid, $args['amount'], $args['month'], $args['category'] ?? null);
        }
    } catch (Throwable $e) {
        error_log("[ai_tools] $name: " . $e->getMessage());
    }
    return ['error' => 'não foi possível obter estes dados'];
}

function ai_tool_spending(PDO $pdo, int $uid, string $from, string $to): array {
    $days     = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
    $prevTo   = date('Y-m-d', strtotime("$from -1 day"));
    $prevFrom = date('Y-m-d', strtotime("$prevTo -" . ($days - 1) . ' days'));

    $sum = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS n FROM transactions
                          WHERE user_id = :u AND transaction_date BETWEEN :a AND :b');
    $sum->execute([':u' => $uid, ':a' => $from, ':b' => $to]);   $cur  = $sum->fetch();
    $sum->execute([':u' => $uid, ':a' => $prevFrom, ':b' => $prevTo]); $prev = $sum->fetch();

    $cat = $pdo->prepare("SELECT COALESCE(category, 'Outros') AS c, SUM(amount) AS t, COUNT(*) AS n FROM transactions
                          WHERE user_id = :u AND transaction_date BETWEEN :a AND :b GROUP BY c ORDER BY t DESC LIMIT 10");
    $cat->execute([':u' => $uid, ':a' => $from, ':b' => $to]);
    $total = (float) $cur['total'];
    $byCat = [];
    foreach ($cat as $r) $byCat[] = ['category' => ai_text($r['c'], 30), 'total' => fmt_money($r['t']), 'transactions' => (int) $r['n'],
                                     'share' => fmt_num($total > 0 ? $r['t'] / $total * 100 : 0) . '%'];

    $diff = $total - (float) $prev['total'];
    return [
        'period' => "$from a $to ($days dias)", 'total' => fmt_money($total), 'transactions' => (int) $cur['n'],
        'daily_average' => fmt_money($days > 0 ? $total / $days : 0), 'by_category' => $byCat,
        'previous_period' => ['period' => "$prevFrom a $prevTo", 'total' => fmt_money($prev['total']),
            'difference' => ($diff >= 0 ? 'mais ' : 'menos ') . fmt_money(abs($diff))
                . ((float) $prev['total'] > 0 ? ' (' . ($diff >= 0 ? '+' : '−') . fmt_num(abs($diff) / $prev['total'] * 100) . '%)' : '')],
    ];
}

function ai_tool_search(PDO $pdo, int $uid, array $a): array {
    $where = ['t.user_id = :u']; $p = [':u' => $uid];
    if (isset($a['query'])) {
        // % e _ são curingas do LIKE: escapados para procurar o texto tal como foi escrito
        $where[] = "t.description LIKE :q ESCAPE '!'";
        $p[':q'] = '%' . strtr($a['query'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }
    if (isset($a['category'])) { $where[] = 't.category = :c'; $p[':c'] = $a['category']; }
    if (isset($a['from']))     { $where[] = 't.transaction_date >= :a'; $p[':a'] = $a['from']; }
    if (isset($a['to']))       { $where[] = 't.transaction_date <= :b'; $p[':b'] = $a['to']; }
    $w = implode(' AND ', $where);

    $tot = $pdo->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(t.amount), 0) AS s FROM transactions t WHERE $w");
    $tot->execute($p); $t = $tot->fetch();

    $order = ($a['sort'] ?? 'recent') === 'largest' ? 't.amount DESC, t.transaction_date DESC' : 't.transaction_date DESC, t.id DESC';
    $limit = (int) ($a['limit'] ?? 10);   // já validado: 1–15
    $stmt = $pdo->prepare("SELECT t.transaction_date, COALESCE(t.category, 'Outros') AS category, t.description, t.amount, c.name AS card
                           FROM transactions t LEFT JOIN cards c ON c.id = t.card_id AND c.user_id = t.user_id
                           WHERE $w ORDER BY $order LIMIT $limit");
    $stmt->execute($p);
    $items = [];
    foreach ($stmt as $r) $items[] = ['date' => $r['transaction_date'], 'category' => ai_text($r['category'], 30),
                                      'description' => ai_text($r['description']), 'amount' => fmt_money($r['amount']),
                                      'card' => $r['card'] !== null ? ai_text($r['card'], 40) : null];
    return ['found' => (int) $t['n'], 'sum_of_found' => fmt_money($t['s']), 'shown' => count($items), 'items' => $items];
}

function ai_tool_budgets(PDO $pdo, int $uid): array {
    // Mesmo cálculo da página Orçamentos (período atual de cada orçamento)
    $stmt = $pdo->prepare("SELECT b.name, b.amount, b.period, b.category,
        COALESCE(SUM(CASE WHEN b.period='monthly' AND t.transaction_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01') AND t.transaction_date <= CURDATE() THEN t.amount
                          WHEN b.period='weekly'  AND t.transaction_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND t.transaction_date <= CURDATE() THEN t.amount
                          WHEN b.period='yearly'  AND YEAR(t.transaction_date) = YEAR(CURDATE()) AND t.transaction_date <= CURDATE() THEN t.amount ELSE 0 END), 0) AS spent
        FROM budgets b LEFT JOIN transactions t ON t.user_id = b.user_id AND (b.category IS NULL OR t.category = b.category) AND (b.card_id IS NULL OR t.card_id = b.card_id)
        WHERE b.user_id = :u AND b.active = 1 GROUP BY b.id ORDER BY b.name LIMIT 20");
    $stmt->execute([':u' => $uid]);
    $names = ['monthly' => 'mês', 'weekly' => 'semana', 'yearly' => 'ano'];
    $items = [];
    foreach ($stmt as $b) {
        $left = $b['amount'] - $b['spent'];
        $items[] = ['name' => ai_text($b['name'], 40), 'category' => $b['category'] ? ai_text($b['category'], 30) : 'todas',
                    'period' => $names[$b['period']] ?? 'mês', 'limit' => fmt_money($b['amount']), 'spent' => fmt_money($b['spent']),
                    'remaining' => $left >= 0 ? fmt_money($left) : 'ultrapassado em ' . fmt_money(-$left),
                    'used' => fmt_num($b['amount'] > 0 ? $b['spent'] / $b['amount'] * 100 : 0) . '%'];
    }
    return ['budgets' => $items];
}

// Média de gastos por mês nos últimos 6 meses completos e rendimento mensal (das Definições)
function ai_monthly_basics(PDO $pdo, int $uid): array {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) / 6 FROM transactions WHERE user_id = :u
                           AND transaction_date >= DATE_FORMAT(CURDATE() - INTERVAL 6 MONTH, '%Y-%m-01')
                           AND transaction_date <  DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $stmt->execute([':u' => $uid]);
    $avg = (float) $stmt->fetchColumn();
    $inc = $pdo->prepare('SELECT monthly_income FROM user_settings WHERE user_id = :u');
    $inc->execute([':u' => $uid]);
    $income = $inc->fetchColumn();
    if ($income === false || $income === null) {
        // Sem rendimento nas Definições: usa o que foi indicado ao criar as metas (como a página Metas)
        $g = $pdo->prepare('SELECT MAX(monthly_income) FROM future_goals WHERE user_id = :u AND monthly_income > 0');
        $g->execute([':u' => $uid]);
        $income = $g->fetchColumn();
    }
    return [$avg, $income !== false && $income !== null ? (float) $income : null];
}

// Metas por cumprir: quanto falta e quanto é preciso juntar por mês até ao prazo
function ai_goals(PDO $pdo, int $uid): array {
    $stmt = $pdo->prepare('SELECT id, name, target_amount, target_date, created_at FROM future_goals WHERE user_id = :u ORDER BY target_date LIMIT 20');
    $stmt->execute([':u' => $uid]);
    $saved = savings_by_goal($pdo, $uid);
    $out = [];
    foreach ($stmt as $g) {
        $have   = $saved[(int) $g['id']] ?? 0.0;
        $left   = max(0, (float) $g['target_amount'] - $have);
        $months = max(1, (int) ceil((strtotime($g['target_date']) - time()) / (86400 * 30.44)));
        $out[] = ['name' => ai_text($g['name'], 40), 'target' => (float) $g['target_amount'], 'saved' => $have, 'left' => $left,
                  'deadline' => $g['target_date'], 'months_left' => $months, 'per_month' => $left > 0 ? $left / $months : 0.0];
    }
    return $out;
}

function ai_tool_goals(PDO $pdo, int $uid): array {
    [$avg, $income] = ai_monthly_basics($pdo, $uid);
    $goals = ai_goals($pdo, $uid);
    $need  = array_sum(array_column($goals, 'per_month'));
    return [
        'average_monthly_spending_6m' => fmt_money($avg),
        'monthly_income' => $income !== null ? fmt_money($income) : 'não definido (o utilizador pode indicá-lo nas Definições)',
        'left_each_month_before_goals' => $income !== null ? fmt_money($income - $avg) : null,
        'needed_each_month_for_all_goals' => fmt_money($need),
        'goals' => array_map(fn($g) => ['name' => $g['name'], 'target' => fmt_money($g['target']), 'saved' => fmt_money($g['saved']),
            'missing' => fmt_money($g['left']), 'deadline' => $g['deadline'], 'months_left' => $g['months_left'],
            'needed_per_month' => fmt_money($g['per_month']), 'done' => $g['left'] <= 0], $goals),
    ];
}

function ai_tool_cards(PDO $pdo, int $uid): array {
    $stmt = $pdo->prepare("SELECT c.name, c.limit_amount, c.balance, COALESCE(SUM(t.amount), 0) AS month
                           FROM cards c LEFT JOIN transactions t ON t.card_id = c.id AND t.user_id = c.user_id
                                AND t.transaction_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                           WHERE c.user_id = :u AND c.active = 1 GROUP BY c.id ORDER BY c.name LIMIT 20");
    $stmt->execute([':u' => $uid]);
    $items = [];
    foreach ($stmt as $c) $items[] = ['name' => ai_text($c['name'], 40),
        'limit' => $c['limit_amount'] > 0 ? fmt_money($c['limit_amount']) : 'sem limite',
        'used' => fmt_money($c['balance']),
        'available' => $c['limit_amount'] > 0 ? fmt_money($c['limit_amount'] - $c['balance']) : null,
        'spent_this_month' => fmt_money($c['month'])];
    return ['cards' => $items];
}

/**
 * Compra de $amount no mês $month. Conta feita aqui, não pelo modelo:
 *   sobra de um mês normal = rendimento − média de gastos − o que as metas pedem por mês;
 *   sobra deste mês        = rendimento − (o que já gastei, ou a média se ainda for menos) − metas;
 *   até à compra junta-se a sobra deste mês + a sobra normal de cada mês seguinte.
 * Assim, se este mês já gastei mais do que o costume, a compra não aparece como cabendo à custa disso.
 */
function ai_tool_simulate(PDO $pdo, int $uid, float $amount, string $month, ?string $category): array {
    [$avg, $income] = ai_monthly_basics($pdo, $uid);
    $goals  = ai_goals($pdo, $uid);
    $need   = array_sum(array_column($goals, 'per_month'));
    $months = ((int) substr($month, 0, 4) - (int) date('Y')) * 12 + (int) substr($month, 5, 2) - (int) date('n') + 1;

    $out = ['purchase' => fmt_money($amount) . ' em ' . $month, 'months_to_prepare' => $months,
            'average_monthly_spending_6m' => fmt_money($avg), 'needed_each_month_for_goals' => fmt_money($need)];
    if ($income === null) {
        $out['verdict'] = 'sem rendimento mensal definido: não dá para saber se a compra cabe. Sugere indicar o rendimento nas Definições.';
    } else {
        $spent = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = :u
                                AND transaction_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND transaction_date <= CURDATE()");
        $spent->execute([':u' => $uid]);
        $spentNow  = (float) $spent->fetchColumn();
        $free      = $income - $avg - $need;
        $thisMonth = $income - max($spentNow, $avg) - $need;
        $ready     = $thisMonth + $free * ($months - 1);
        $out['spent_this_month_so_far'] = fmt_money($spentNow);
        $out['left_this_month_after_goals'] = fmt_money($thisMonth);
        $out['monthly_income'] = fmt_money($income);
        $out['left_each_month_after_goals'] = fmt_money($free);
        $out['saved_by_then_without_touching_goals'] = fmt_money(max(0, $ready));
        if ($free <= 0 && $ready < $amount) $out['verdict'] = 'não cabe sem atrasar as metas: com os gastos e as metas atuais não sobra nada por mês';
        elseif ($ready >= $amount) $out['verdict'] = 'cabe sem atrasar as metas';
        else                       $out['verdict'] = 'não cabe sem atrasar as metas: faltam ' . fmt_money($amount - $ready)
                                       . '; a este ritmo chega daqui a ' . (int) ceil(($amount - max(0, $thisMonth)) / $free) . ' meses';
    }
    if ($category !== null) {
        $b = $pdo->prepare("SELECT name, amount FROM budgets WHERE user_id = :u AND active = 1 AND period = 'monthly' AND category = :c LIMIT 1");
        $b->execute([':u' => $uid, ':c' => $category]);
        if ($row = $b->fetch()) {
            $avgCat = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) / 6 FROM transactions WHERE user_id = :u AND category = :c
                                     AND transaction_date >= DATE_FORMAT(CURDATE() - INTERVAL 6 MONTH, '%Y-%m-01')
                                     AND transaction_date <  DATE_FORMAT(CURDATE(), '%Y-%m-01')");
            $avgCat->execute([':u' => $uid, ':c' => $category]);
            $usual = (float) $avgCat->fetchColumn();
            $out['budget'] = ['name' => ai_text($row['name'], 40), 'limit' => fmt_money($row['amount']),
                'usual_spending_in_category' => fmt_money($usual), 'with_purchase' => fmt_money($usual + $amount),
                'exceeds' => $usual + $amount > $row['amount']];
        }
    }
    return $out;
}
