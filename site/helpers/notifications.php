<?php
// site/helpers/notifications.php — notificações na app (sino no cabeçalho + página de histórico).
//
// Sem cron: na primeira página que a pessoa abre em cada dia, notif_generate() verifica pagamentos,
// orçamentos, cartões, metas, poupanças, bolsa e o resumo do mês. Cada aviso tem uma dedupe_key
// (única por utilizador), por isso o mesmo aviso nunca entra duas vezes.
// Os links são decididos aqui, pelo tipo — nunca se guarda um URL vindo de fora.

require_once __DIR__ . '/savings.php';

// tipo => [rótulo nas Definições, ícone, cor, ligado por omissão]
const NOTIF_TYPES = [
    'payment_due'       => ['Pagamentos a vencer',            'calendar-event',      'cat-1', true],
    'payment_overdue'   => ['Pagamentos em atraso',           'exclamation-triangle', 'danger', true],
    'budget_warning'    => ['Orçamento a 80%',                'pie-chart',           'cat-4', true],
    'budget_exceeded'   => ['Orçamento ultrapassado',         'pie-chart-fill',      'danger', true],
    'card_over_limit'   => ['Cartão acima do limite',         'credit-card',         'danger', true],
    'goal_behind'       => ['Meta atrasada',                  'flag',                'cat-2', true],
    'goal_done'         => ['Meta cumprida',                  'trophy',              'cat-3', true],
    'savings_milestone' => ['Novo marco nas poupanças',       'piggy-bank',          'cat-3', true],
    'market_move'       => ['Bolsa: subida/descida forte',    'activity',            'cat-7', false],
    'month_summary'     => ['Resumo do mês anterior',         'calendar3',           'cat-1', true],
];
const NOTIF_MARKET_PCT = 5.0;      // variação do dia que conta como "forte"
const NOTIF_KEEP_DAYS  = 180;      // notificações lidas com mais de 6 meses são apagadas

function notif_prefs(PDO $pdo, int $uid): array {
    $st = $pdo->prepare('SELECT notifications, notif_prefs FROM user_settings WHERE user_id = :u');
    $st->execute([':u' => $uid]);
    $row   = $st->fetch() ?: ['notifications' => 1, 'notif_prefs' => null];
    $saved = json_decode((string) $row['notif_prefs'], true) ?: [];
    $out   = ['_on' => (bool) $row['notifications'], 'budget_email' => (bool) ($saved['budget_email'] ?? true)];
    foreach (NOTIF_TYPES as $t => $def) $out[$t] = isset($saved[$t]) ? (bool) $saved[$t] : $def[3];
    return $out;
}

// Insere se ainda não existir (a dedupe_key é única por utilizador: INSERT IGNORE não duplica)
function notif_add(PDO $pdo, int $uid, string $type, string $key, string $title, string $message, array $data = []): void {
    $pdo->prepare('INSERT IGNORE INTO notifications (user_id, type, dedupe_key, title, message, data) VALUES (:u, :t, :k, :ti, :m, :d)')
        ->execute([':u' => $uid, ':t' => $type, ':k' => mb_substr($key, 0, 80), ':ti' => mb_substr($title, 0, 255),
                   ':m' => mb_substr($message, 0, 1000), ':d' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

// Verificação diária (só corre uma vez por dia por utilizador)
function notif_generate(PDO $pdo, int $uid, bool $force = false): void {
    $prefs = notif_prefs($pdo, $uid);
    if (!$prefs['_on']) return;
    if (!$force) {
        // Marca o dia ANTES de verificar: dois pedidos ao mesmo tempo não fazem o trabalho duas vezes
        $upd = $pdo->prepare('UPDATE user_settings SET notif_checked_on = CURDATE()
                              WHERE user_id = :u AND (notif_checked_on IS NULL OR notif_checked_on < CURDATE())');
        $upd->execute([':u' => $uid]);
        if ($upd->rowCount() === 0) {
            $has = $pdo->prepare('SELECT 1 FROM user_settings WHERE user_id = :u');
            $has->execute([':u' => $uid]);
            if ($has->fetchColumn()) return;                                   // já verificado hoje
            $pdo->prepare('INSERT IGNORE INTO user_settings (user_id, notif_checked_on, language) VALUES (:u, CURDATE(), :lang)')->execute([':u' => $uid, ':lang' => current_lang()]);
        }
    }
    $on = fn(string $t) => !empty($prefs[$t]);
    $today = (string) $pdo->query('SELECT CURDATE()')->fetchColumn();

    // Pagamentos: a vencer (dentro dos dias de aviso) e em atraso
    if ($on('payment_due') || $on('payment_overdue')) {
        $st = $pdo->prepare("SELECT id, name, amount, due_date, DATEDIFF(due_date, CURDATE()) AS d, notify_days_before
                             FROM payment_reminders WHERE user_id = :u AND active = 1 AND COALESCE(paid, 0) = 0
                               AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
        $st->execute([':u' => $uid]);
        foreach ($st as $r) {
            $d = (int) $r['d'];
            if ($d < 0 && $on('payment_overdue')) {
                notif_add($pdo, $uid, 'payment_overdue', "late:{$r['id']}:{$r['due_date']}", t('{name} está em atraso', ['name' => $r['name']]),
                    t(abs($d) > 1 ? '{amount} venceram há {n} dias. Marca como pago quando pagares.' : '{amount} venceram há 1 dia. Marca como pago quando pagares.', ['amount' => fmt_money($r['amount']), 'n' => abs($d)]), ['reminder_id' => (int) $r['id']]);
            } elseif ($d >= 0 && $d <= (int) $r['notify_days_before'] && $on('payment_due')) {
                notif_add($pdo, $uid, 'payment_due', "due:{$r['id']}:{$r['due_date']}", t($d === 0 ? '{name} vence hoje' : ($d === 1 ? '{name} vence amanhã' : '{name} vence em {n} dias'), ['name' => $r['name'], 'n' => $d]),
                    t('{amount} a pagar.', ['amount' => fmt_money($r['amount'])]), ['reminder_id' => (int) $r['id']]);
            }
        }
    }

    // Orçamentos: gasto do período em curso (mesma regra da página Orçamentos)
    if ($on('budget_warning') || $on('budget_exceeded')) {
        $st = $pdo->prepare("SELECT b.id, b.name, b.amount, p.ps, COALESCE(SUM(t.amount), 0) AS spent
            FROM budgets b
            JOIN (SELECT id, CASE period WHEN 'weekly' THEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                                         WHEN 'yearly' THEN MAKEDATE(YEAR(CURDATE()), 1)
                                         ELSE DATE_FORMAT(CURDATE(), '%Y-%m-01') END AS ps
                  FROM budgets WHERE user_id = :u1) p ON p.id = b.id
            LEFT JOIN transactions t ON t.user_id = b.user_id AND t.transaction_date BETWEEN p.ps AND CURDATE()
                 AND (b.category IS NULL OR t.category = b.category) AND (b.card_id IS NULL OR t.card_id = b.card_id)
            WHERE b.user_id = :u2 AND b.active = 1 GROUP BY b.id");
        $st->execute([':u1' => $uid, ':u2' => $uid]);
        foreach ($st as $b) {
            if ($b['amount'] <= 0) continue;
            $pct = $b['spent'] / $b['amount'] * 100;
            if ($pct >= 100 && $on('budget_exceeded')) {
                notif_add($pdo, $uid, 'budget_exceeded', "bud100:{$b['id']}:{$b['ps']}", t('Passaste o orçamento "{name}"', ['name' => $b['name']]),
                    t('Já gastaste {amount} de {limit}.', ['amount' => fmt_money($b['spent']), 'limit' => fmt_money($b['amount'])]), ['budget_id' => (int) $b['id']]);
            } elseif ($pct >= 80 && $on('budget_warning')) {
                notif_add($pdo, $uid, 'budget_warning', "bud80:{$b['id']}:{$b['ps']}", t('Orçamento "{name}" a {pct}%', ['name' => $b['name'], 'pct' => floor($pct)]),
                    t('Restam {amount} até ao fim do período.', ['amount' => fmt_money($b['amount'] - $b['spent'])]), ['budget_id' => (int) $b['id']]);
            }
        }
    }

    // Cartões acima do limite (uma vez por mês)
    if ($on('card_over_limit')) {
        $st = $pdo->prepare('SELECT id, name, limit_amount, balance FROM cards WHERE user_id = :u AND active = 1 AND limit_amount > 0 AND balance > limit_amount');
        $st->execute([':u' => $uid]);
        foreach ($st as $c) {
            notif_add($pdo, $uid, 'card_over_limit', 'card:' . $c['id'] . ':' . substr($today, 0, 7), t('{name} passou o limite', ['name' => $c['name']]),
                t('Estás {amount} acima de {limit}.', ['amount' => fmt_money($c['balance'] - $c['limit_amount']), 'limit' => fmt_money($c['limit_amount'])]), ['card_id' => (int) $c['id']]);
        }
    }

    // Metas: atrasada (no máximo 1 aviso por mês) ou cumprida (uma vez)
    $byGoal = null;
    if ($on('goal_behind') || $on('goal_done')) {
        $byGoal = savings_by_goal($pdo, $uid);
        $st = $pdo->prepare('SELECT id, name, target_amount, target_date, created_at FROM future_goals WHERE user_id = :u');
        $st->execute([':u' => $uid]);
        foreach ($st as $g) {
            $saved = max($byGoal[(int) $g['id']] ?? 0, 0);
            if ($saved >= $g['target_amount'] && $on('goal_done')) {
                notif_add($pdo, $uid, 'goal_done', 'goal_done:' . $g['id'], t('Meta cumprida: {name}', ['name' => $g['name']]), t('Juntaste {amount}. Parabéns!', ['amount' => fmt_money($saved)]), ['goal_id' => (int) $g['id']]);
            } elseif ($saved < goal_expected_today($g, $today) && $on('goal_behind')) {
                $months = max(1, (strtotime($g['target_date']) - strtotime($today)) / (30 * 86400));
                notif_add($pdo, $uid, 'goal_behind', 'goal_late:' . $g['id'] . ':' . substr($today, 0, 7), t('A meta "{name}" está atrasada', ['name' => $g['name']]),
                    t('Junta {amount} por mês para chegares a tempo.', ['amount' => fmt_money(($g['target_amount'] - $saved) / $months, 0)]), ['goal_id' => (int) $g['id']]);
            }
        }
    }

    if ($on('savings_milestone')) notif_savings_milestone($pdo, $uid, $byGoal);

    // Bolsa: só olha para a cache (nunca faz pedidos à fonte aqui)
    if ($on('market_move')) {
        $st = $pdo->prepare('SELECT q.symbol, q.name, q.price_eur, q.prev_close_eur FROM watchlist w JOIN market_quotes q ON q.symbol = w.symbol
                             WHERE w.user_id = :u AND q.prev_close_eur > 0 AND DATE(q.fetched_at) = CURDATE()');
        $st->execute([':u' => $uid]);
        foreach ($st as $q) {
            $pct = ($q['price_eur'] - $q['prev_close_eur']) / $q['prev_close_eur'] * 100;
            if (abs($pct) < NOTIF_MARKET_PCT) continue;
            notif_add($pdo, $uid, 'market_move', 'mkt:' . $q['symbol'] . ':' . $today, t($pct > 0 ? '{name} sobe {pct}% hoje' : '{name} desce {pct}% hoje', ['name' => $q['name'] ?: $q['symbol'], 'pct' => fmt_num(abs($pct), 1)]),
                t('Está a {price}.', ['price' => fmt_eur($q['price_eur'])]), ['symbol' => $q['symbol']]);
        }
    }

    // Resumo do mês anterior (uma vez por mês)
    if ($on('month_summary')) {
        $st = $pdo->prepare("SELECT
              COALESCE(SUM(CASE WHEN transaction_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH) AND transaction_date < DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN amount END), 0) AS last,
              COALESCE(SUM(CASE WHEN transaction_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 2 MONTH) AND transaction_date < DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH) THEN amount END), 0) AS prev
            FROM transactions WHERE user_id = :u");
        $st->execute([':u' => $uid]);
        $m = $st->fetch();
        if ($m['last'] > 0) {
            $prevMonth = (new DateTimeImmutable($today))->modify('first day of last month');
            $name = month_name((int) $prevMonth->format('n'));
            $diff = $m['prev'] > 0 ? ($m['last'] - $m['prev']) / $m['prev'] * 100 : null;
            notif_add($pdo, $uid, 'month_summary', 'month:' . $prevMonth->format('Y-m'), t('O teu {month} em números', ['month' => $name]),
                $diff === null ? t('Gastaste {amount}.', ['amount' => fmt_money($m['last'])])
                               : t('Gastaste {amount} ({pct}% que no mês anterior).', ['amount' => fmt_money($m['last']), 'pct' => ($diff > 0 ? '+' : '−') . fmt_num(abs($diff), 0)]), ['month' => $prevMonth->format('Y-m')]);
        }
    }

    // Limpeza: lidas há mais de 6 meses
    $pdo->prepare('DELETE FROM notifications WHERE user_id = :u AND is_read = 1 AND created_at < NOW() - INTERVAL ' . NOTIF_KEEP_DAYS . ' DAY')->execute([':u' => $uid]);
}

// Marco nas poupanças: chamado na verificação diária e logo depois de "Pôr de lado"
function notif_savings_milestone(PDO $pdo, int $uid, ?array $byGoal = null): void {
    $total  = array_sum($byGoal ?? savings_by_goal($pdo, $uid));
    $passed = 0;
    foreach (SAVINGS_MILESTONES as $m) if ($total >= $m) $passed = $m;
    if ($passed > 0) {
        notif_add($pdo, $uid, 'savings_milestone', 'save_ms:' . $passed, t('Chegaste aos {amount} poupados!', ['amount' => fmt_money($passed, 0)]),
            t('O próximo marco é {amount}.', ['amount' => fmt_money(savings_next_milestone($total), 0)]), ['milestone' => $passed]);
    }
}

// Para onde leva cada notificação (caminho relativo à pasta site/)
function notif_link(array $n): string {
    $d = json_decode((string) ($n['data'] ?? ''), true) ?: [];
    return match ($n['type']) {
        'payment_due', 'payment_overdue'  => 'pages/reminders.php#calendario',
        'budget_warning', 'budget_exceeded' => 'pages/budgets.php',
        'card_over_limit'                 => 'pages/cards.php' . (isset($d['card_id']) ? '#card-' . (int) $d['card_id'] : ''),
        'goal_behind', 'goal_done'        => 'pages/futuro.php' . (isset($d['goal_id']) ? '#g' . (int) $d['goal_id'] : ''),
        'savings_milestone'               => 'pages/savings.php',
        'market_move'                     => 'pages/bolsa.php',
        'month_summary'                   => 'pages/analytics.php',
        default                           => 'pages/notifications.php',
    };
}

function notif_unread_count(PDO $pdo, int $uid): int {
    $st = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0');
    $st->execute([':u' => $uid]);
    return (int) $st->fetchColumn();
}

function notif_recent(PDO $pdo, int $uid, int $limit = 8): array {
    $st = $pdo->prepare('SELECT id, type, title, message, data, is_read, created_at, TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS mins
                         FROM notifications WHERE user_id = :u ORDER BY is_read ASC, created_at DESC, id DESC LIMIT ' . (int) $limit);
    $st->execute([':u' => $uid]);
    return $st->fetchAll();
}

function notif_ago(int $mins): string {
    if ($mins < 1)    return t('agora');
    if ($mins < 60)   return t('há {n} min', ['n' => $mins]);
    if ($mins < 1440) return t('há {n} h', ['n' => intdiv($mins, 60)]);
    $d = intdiv($mins, 1440);
    return $d === 1 ? t('ontem') : t('há {n} dias', ['n' => $d]);
}
