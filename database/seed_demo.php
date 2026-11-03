<?php
// database/seed_demo.php — cria (ou recria) a conta de demonstração com ~4 meses de dados realistas.
//
// Uso (no terminal, na pasta do projeto):
//   /Applications/XAMPP/xamppfiles/bin/php database/seed_demo.php        (14 meses de dados)
//   /Applications/XAMPP/xamppfiles/bin/php database/seed_demo.php 6      (só 6 meses)
//
// - Só corre no terminal (a pasta database/ também está bloqueada na web pelo .htaccess).
// - Nunca toca noutras contas: apaga e volta a criar só demo@finora.test.
// - As datas são relativas a hoje (relógio do MySQL), por isso funciona em qualquer mês.
// - No fim mostra a palavra-passe (nova a cada execução).
// - Inclui poupanças (se a tabela savings existir: database/migration_savings.sql).

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../config/db.php';
$pdo->exec('SET NAMES utf8mb4');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

const DEMO_EMAIL = 'demo@finora.test';
mt_srand(2026);                                   // mesmos dados a cada execução

$today = new DateTimeImmutable((string) $pdo->query('SELECT CURDATE()')->fetchColumn());
$months = max(1, min(36, (int) ($argv[1] ?? 14)));   // 14 meses: dá para comparar com o ano anterior
$start = $today->modify('first day of this month')->modify('-' . ($months - 1) . ' months');
$monthStart = $today->modify('first day of this month');
$hasAnchor = (bool) $pdo->query("SHOW COLUMNS FROM payment_reminders LIKE 'anchor_day'")->fetch();

function rnd(float $min, float $max): float { return round($min + mt_rand() / mt_getrandmax() * ($max - $min), 2); }
function pick(array $a) { return $a[array_rand($a)]; }
function chance(float $p): bool { return mt_rand() / mt_getrandmax() < $p; }

$pdo->beginTransaction();
try {
    // ── 1. Apagar a conta demo anterior (e tudo o que lhe pertence) ─────────
    $old = $pdo->prepare('SELECT id FROM users WHERE email = :e');
    $old->execute([':e' => DEMO_EMAIL]);
    if ($oldId = $old->fetchColumn()) {
        $pdo->prepare('DELETE rh FROM reminder_history rh JOIN payment_reminders r ON r.id = rh.reminder_id WHERE r.user_id = :u')->execute([':u' => $oldId]);
        $hasSavings = (bool) $pdo->query("SHOW TABLES LIKE 'savings'")->fetch();
        if ($hasSavings) $pdo->prepare('DELETE FROM savings WHERE user_id = :u')->execute([':u' => $oldId]);
        foreach (['transactions', 'payment_reminders', 'budgets', 'future_goals', 'investments', 'cards', 'user_settings'] as $t) {
            $pdo->prepare("DELETE FROM $t WHERE user_id = :u")->execute([':u' => $oldId]);
        }
        $pdo->prepare('DELETE FROM users WHERE id = :u')->execute([':u' => $oldId]);
    }

    // ── 2. Utilizador ───────────────────────────────────────────────────────
    $password = 'Demo-' . bin2hex(random_bytes(4)) . '!9';
    $pdo->prepare('INSERT INTO users (username, email, password_hash, email_verified, created_at) VALUES (:n, :e, :p, 1, :c)')
        ->execute([':n' => 'Marta Demo', ':e' => DEMO_EMAIL, ':p' => password_hash($password, PASSWORD_DEFAULT), ':c' => $start->format('Y-m-d 09:00:00')]);
    $uid = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO user_settings (user_id, theme, notifications) VALUES (:u, "light", 1)')->execute([':u' => $uid]);

    // ── 3. Cartões ──────────────────────────────────────────────────────────
    $cards = [];
    $addCard = $pdo->prepare('INSERT INTO cards (user_id, name, bank, color, limit_amount, balance, active, created_at) VALUES (:u, :n, :b, :c, :l, 0, 1, :d)');
    foreach ([
        'ordenado' => ['Conta ordenado', 'cgd', 'green', 0],
        'credito'  => ['Crédito BCP', 'millennium', 'indigo', 1500],
        'revolut'  => ['Revolut', 'revolut', 'purple', 0],
        'gold'     => ['Santander Gold', 'santander', 'red', 1000],
    ] as $key => [$name, $bank, $color, $limit]) {
        $addCard->execute([':u' => $uid, ':n' => $name, ':b' => $bank, ':c' => $color, ':l' => $limit, ':d' => $start->format('Y-m-d 09:05:00')]);
        $cards[$key] = (int) $pdo->lastInsertId();
    }

    // ── 4. Transações ───────────────────────────────────────────────────────
    // [categoria, descrições, mínimo, máximo, cartões possíveis]
    $habits = [
        'super'   => ['Alimentação', ['Continente', 'Pingo Doce', 'Lidl', 'Mercadona', 'Aldi'], 14, 92, ['ordenado', 'credito']],
        'cafe'    => ['Alimentação', ['Café Central', 'Padaria Portuguesa', 'Starbucks', 'Pastelaria Versailles'], 1.2, 7.5, ['revolut', 'ordenado']],
        'almoco'  => ['Alimentação', ['Almoço — Tasca do Zé', 'Sushi Time', 'H3', 'Honorato', 'Vitaminas'], 9, 28, ['revolut', 'credito']],
        'gasol'   => ['Transporte', ['Galp', 'BP', 'Repsol', 'Prio'], 38, 68, ['credito', 'gold']],
        'uber'    => ['Transporte', ['Uber', 'Bolt'], 6, 19, ['revolut']],
        'compras' => ['Compras', ['Zara', 'Worten', 'Amazon', 'FNAC', 'Primark', 'Decathlon'], 12, 135, ['credito', 'gold', 'revolut']],
        'casa'    => ['Casa', ['IKEA', 'Leroy Merlin', 'Continente — casa'], 9, 85, ['credito']],
        'saude'   => ['Saúde', ['Farmácia Saúde', 'Wells', 'Consulta — CUF'], 5, 60, ['ordenado', 'credito']],
        'lazer'   => ['Entretenimento', ['Cinema NOS', 'Concerto — Coliseu', 'Bowling', 'Steam'], 7, 45, ['revolut', 'gold']],
        'estudo'  => ['Educação', ['Udemy', 'Livraria Bertrand', 'Coursera'], 10, 32, ['revolut']],
        'outros'  => ['Outros', ['CTT', 'Presente de anos', 'Lavandaria'], 4, 40, ['ordenado', 'revolut']],
    ];
    // probabilidade por dia (dias úteis / fim de semana)
    $odds = ['super' => [.28, .45], 'cafe' => [.6, .3], 'almoco' => [.35, .2], 'gasol' => [.12, .08], 'uber' => [.06, .18],
             'compras' => [.06, .16], 'casa' => [.02, .06], 'saude' => [.05, .03], 'lazer' => [.04, .3], 'estudo' => [.02, .02], 'outros' => [.04, .05]];
    // Pagamentos fixos do mês: [dia, descrição, categoria, valor (ou [min,max]), cartão]
    $fixed = [
        [1,  'Renda', 'Casa', 650, 'ordenado'],
        [2,  'Passe Navegante', 'Transporte', 40, 'ordenado'],
        [5,  'Ginásio Fitness Hut', 'Saúde', 29.90, 'credito'],
        [8,  'Spotify', 'Entretenimento', 7.99, 'revolut'],
        [10, 'EDP — Luz', 'Casa', [48, 72], 'ordenado'],
        [15, 'Netflix', 'Entretenimento', 13.99, 'revolut'],
        [18, 'EPAL — Água', 'Casa', [19, 26], 'ordenado'],
        [22, 'MEO — Internet', 'Casa', 39.99, 'ordenado'],
    ];

    $tx = [];
    for ($d = $start; $d <= $today; $d = $d->modify('+1 day')) {
        $weekend = (int) $d->format('N') >= 6;
        foreach ($odds as $key => [$pWeek, $pWeekend]) {
            if (!chance($weekend ? $pWeekend : $pWeek)) continue;
            [$cat, $names, $min, $max, $cardKeys] = $habits[$key];
            $tx[] = [$d->format('Y-m-d'), pick($names), $cat, rnd($min, $max), $cards[pick($cardKeys)]];
        }
        foreach ($fixed as [$day, $desc, $cat, $amount, $cardKey]) {
            if ((int) $d->format('j') !== $day) continue;
            $tx[] = [$d->format('Y-m-d'), $desc, $cat, is_array($amount) ? rnd(...$amount) : $amount, $cards[$cardKey]];
        }
    }
    $addTx = $pdo->prepare('INSERT INTO transactions (user_id, card_id, amount, description, category, transaction_date, created_at)
                            VALUES (:u, :c, :a, :d, :cat, :dt, :created)');
    foreach ($tx as [$date, $desc, $cat, $amount, $cardId]) {
        $addTx->execute([':u' => $uid, ':c' => $cardId, ':a' => $amount, ':d' => $desc, ':cat' => $cat, ':dt' => $date, ':created' => "$date 12:00:00"]);
    }

    // Saldo = o que foi gasto ESTE mês em cada cartão (como se o extrato anterior estivesse pago).
    // A app soma cada gasto ao saldo para sempre; com meses de dados, os cartões passavam o limite.
    $pdo->prepare('UPDATE cards c SET balance = (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t
                                                 WHERE t.card_id = c.id AND t.transaction_date >= :m)
                   WHERE c.user_id = :u')->execute([':m' => $monthStart->format('Y-m-d'), ':u' => $uid]);

    // ── 5. Orçamentos ───────────────────────────────────────────────────────
    $addBudget = $pdo->prepare('INSERT INTO budgets (user_id, name, amount, period, category, start_date, active) VALUES (:u, :n, :a, "monthly", :c, :s, 1)');
    foreach ([['Supermercado e restaurantes', 420, 'Alimentação'], ['Carro e transportes', 190, 'Transporte'],
              ['Lazer', 90, 'Entretenimento'], ['Compras', 160, 'Compras']] as [$n, $a, $c]) {
        $addBudget->execute([':u' => $uid, ':n' => $n, ':a' => $a, ':c' => $c, ':s' => $start->format('Y-m-d')]);
    }

    // ── 6. Lembretes (+ histórico do que já foi pago este mês) ─────────────
    $cols = 'user_id, name, amount, category, card_id, due_date, recurrence, active, paid, notify_days_before, notify_method, next_due_date' . ($hasAnchor ? ', anchor_day' : '');
    $vals = ':u, :n, :a, :c, :card, :due, :rec, 1, 0, 3, "site", :next' . ($hasAnchor ? ', :anchor' : '');
    $addRem  = $pdo->prepare("INSERT INTO payment_reminders ($cols) VALUES ($vals)");
    $addHist = $pdo->prepare('INSERT INTO reminder_history (reminder_id, paid_date, amount) VALUES (:r, :d, :a)');
    // Próxima data para um mensal no dia $day (o deste mês se ainda não passou)
    $nextMonthly = function (int $day) use ($today): DateTimeImmutable {
        $m = $today->modify('first day of this month');
        $d = $m->setDate((int) $m->format('Y'), (int) $m->format('n'), min($day, (int) $m->format('t')));
        if ($d < $today) { $m = $m->modify('+1 month'); $d = $m->setDate((int) $m->format('Y'), (int) $m->format('n'), min($day, (int) $m->format('t'))); }
        return $d;
    };
    $reminders = [
        ['Renda', 650, 'Casa', 'ordenado', 'monthly', 1],
        ['Ginásio', 29.90, 'Saúde', 'credito', 'monthly', 5],
        ['Spotify', 7.99, 'Entretenimento', 'revolut', 'monthly', 8],
        ['Luz (EDP)', 60, 'Casa', 'ordenado', 'monthly', 10],
        ['Netflix', 13.99, 'Entretenimento', 'revolut', 'monthly', 15],
        ['Água (EPAL)', 23, 'Casa', 'ordenado', 'monthly', 18],
        ['Internet (MEO)', 39.99, 'Casa', 'ordenado', 'monthly', 22],
        ['Fatura cartão BCP', 300, 'Outros', 'ordenado', 'monthly', 31],
    ];
    foreach ($reminders as [$n, $a, $c, $cardKey, $rec, $day]) {
        $due = $nextMonthly($day);
        $addRem->execute([':u' => $uid, ':n' => $n, ':a' => $a, ':c' => $c, ':card' => $cards[$cardKey], ':due' => $due->format('Y-m-d'), ':next' => $due->format('Y-m-d'), ':rec' => $rec]
                         + ($hasAnchor ? [':anchor' => $day] : []));
        // Se o dia deste mês já passou, fica registado como pago
        $thisMonth = $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('n'), min($day, (int) $monthStart->format('t')));
        if ($thisMonth < $today) $addHist->execute([':r' => $pdo->lastInsertId(), ':d' => $thisMonth->format('Y-m-d'), ':a' => $a]);
    }
    // Um anual e um em atraso, para se ver no calendário
    $addRem->execute([':u' => $uid, ':n' => 'Seguro do carro', ':a' => 312, ':c' => 'Transporte', ':card' => $cards['credito'],
                      ':due' => $today->modify('+19 days')->format('Y-m-d'), ':next' => $today->modify('+19 days')->format('Y-m-d'), ':rec' => 'yearly'] + ($hasAnchor ? [':anchor' => (int) $today->modify('+19 days')->format('j')] : []));
    $addRem->execute([':u' => $uid, ':n' => 'IUC', ':a' => 142, ':c' => 'Transporte', ':card' => $cards['ordenado'],
                      ':due' => $today->modify('-2 days')->format('Y-m-d'), ':next' => $today->modify('-2 days')->format('Y-m-d'), ':rec' => 'once'] + ($hasAnchor ? [':anchor' => (int) $today->modify('-2 days')->format('j')] : []));

    // ── 7. Metas e investimentos ───────────────────────────────────────────
    // As metas começam no início dos dados (assim a linha de "onde devias estar" faz sentido)
    $addGoal = $pdo->prepare('INSERT INTO future_goals (user_id, name, target_amount, monthly_income, target_date, created_at) VALUES (:u, :n, :t, :i, :d, :c)');
    $addGoal->execute([':u' => $uid, ':n' => 'Viagem ao Japão', ':t' => 3200, ':i' => 2450, ':d' => $today->modify('+10 months')->format('Y-m-d'), ':c' => $start->format('Y-m-d 10:00:00')]);
    $japan = (int) $pdo->lastInsertId();
    $addGoal->execute([':u' => $uid, ':n' => 'Fundo de emergência', ':t' => 5000, ':i' => 2450, ':d' => $today->modify('+18 months')->format('Y-m-d'), ':c' => $start->format('Y-m-d 10:00:00')]);
    $emergency = (int) $pdo->lastInsertId();

    // Poupanças: todos os meses ao dia 3 (Japão um pouco abaixo do ritmo, emergência em dia) + 2 levantamentos
    if ((bool) $pdo->query("SHOW TABLES LIKE 'savings'")->fetch()) {
        $addSave = $pdo->prepare('INSERT INTO savings (user_id, goal_id, amount, note, saved_on) VALUES (:u, :g, :a, :n, :d)');
        $i = 0;
        for ($m = $start; $m <= $today; $m = $m->modify('+1 month'), $i++) {
            $day = $m->setDate((int) $m->format('Y'), (int) $m->format('n'), 3);
            if ($day > $today) break;
            $d = $day->format('Y-m-d');
            $addSave->execute([':u' => $uid, ':g' => $japan,     ':a' => 110, ':n' => 'Para o Japão', ':d' => $d]);
            $addSave->execute([':u' => $uid, ':g' => $emergency, ':a' => 160, ':n' => 'Fundo de emergência', ':d' => $d]);
            $addSave->execute([':u' => $uid, ':g' => null, ':a' => rnd(30, 90), ':n' => 'Sobrou do ordenado', ':d' => $day->modify('+24 days')->format('Y-m-d') <= $today->format('Y-m-d') ? $day->modify('+24 days')->format('Y-m-d') : $d]);
            if ($i === 4) $addSave->execute([':u' => $uid, ':g' => null, ':a' => -150, ':n' => 'Reparação do carro', ':d' => $day->modify('+12 days')->format('Y-m-d')]);
            if ($i === 9) $addSave->execute([':u' => $uid, ':g' => null, ':a' => -60, ':n' => 'Prenda de anos', ':d' => $day->modify('+8 days')->format('Y-m-d')]);
        }
    }

    $addInv = $pdo->prepare('INSERT INTO investments (user_id, name, type, ticker, quantity, buy_price, current_price, price_updated_at, created_at)
                             VALUES (:u, :n, :t, :k, :q, :b, :c, NOW(), :d)');
    foreach ([['Vanguard FTSE All-World', 'etf', 'VWCE.DE', 14, 132.40, 132.40], ['Bitcoin', 'cripto', 'BTC', 0.0125, 54200, 61850],
              ['PPR Optimize', 'ppr', null, 1, 2400, 2538], ['Apple', 'acoes', 'AAPL', 4, 182.10, 171.40]] as [$n, $t, $k, $q, $b, $c]) {
        $addInv->execute([':u' => $uid, ':n' => $n, ':t' => $t, ':k' => $k, ':q' => $q, ':b' => $b, ':c' => $c, ':d' => $start->format('Y-m-d 10:00:00')]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Erro: nada foi alterado.\n" . $e->getMessage() . "\n");
    exit(1);
}

$n = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE user_id = :u');
$n->execute([':u' => $uid]);
echo "Conta demo criada (id $uid) com {$n->fetchColumn()} transações desde {$start->format('d/m/Y')}.\n";
echo "  Email:         " . DEMO_EMAIL . "\n";
echo "  Palavra-passe: $password\n";
