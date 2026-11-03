<?php
/**
 * Budget Alert Helper
 * 
 * Verifica orçamentos e envia emails quando atingem limites
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/EmailService.php';

function checkAndSendBudgetAlerts($pdo, $userId, $email, $username) {
    // Gasto de cada orçamento SÓ no período em curso (mesma regra da página Orçamentos):
    // mensal = este mês, semanal = desde segunda, anual = este ano. Também respeita o cartão.
    // Antes somava tudo desde a criação do orçamento: ao fim de uns meses qualquer orçamento
    // mensal estava "excedido" para sempre e a percentagem passava os 999,99% da coluna (erro 500).
    $stmt = $pdo->prepare("
        SELECT b.id, b.name, b.amount AS limit_amount, b.category, p.period_start,
               COALESCE(SUM(t.amount), 0) AS amount_spent
        FROM budgets b
        JOIN (
            SELECT id, CASE period
                         WHEN 'weekly' THEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                         WHEN 'yearly' THEN MAKEDATE(YEAR(CURDATE()), 1)
                         ELSE DATE_FORMAT(CURDATE(), '%Y-%m-01')
                       END AS period_start
            FROM budgets WHERE user_id = :uid1
        ) p ON p.id = b.id
        LEFT JOIN transactions t ON t.user_id = b.user_id
             AND t.transaction_date BETWEEN p.period_start AND CURDATE()
             AND (b.category IS NULL OR t.category = b.category)
             AND (b.card_id IS NULL OR t.card_id = b.card_id)
        WHERE b.user_id = :uid2 AND b.active = 1
        GROUP BY b.id
    ");
    $stmt->execute([':uid1' => $userId, ':uid2' => $userId]);

    $alertsSent = 0;
    foreach ($stmt->fetchAll() as $budget) {
        // Um alerta com problema nunca pode deitar abaixo a página que o chamou
        try {
            $percentage = $budget['limit_amount'] > 0 ? $budget['amount_spent'] / $budget['limit_amount'] * 100 : 0;
            if ($percentage < 80) continue;
            $alertType = $percentage >= 100 ? 'exceeded' : 'warning';

            // Um alerta de cada tipo por período (no mês seguinte volta a poder avisar)
            $check = $pdo->prepare('SELECT 1 FROM budget_alerts WHERE budget_id = ? AND alert_type = ? AND triggered_at >= ? LIMIT 1');
            $check->execute([$budget['id'], $alertType, $budget['period_start']]);
            if ($check->fetch()) continue;

            // Regista primeiro: se o email falhar, não volta a tentar em cada visita ao dashboard
            $pdo->prepare('INSERT INTO budget_alerts (budget_id, user_id, alert_type, percentage, amount_spent, triggered_at)
                           VALUES (?, ?, ?, ?, ?, NOW())')
                ->execute([$budget['id'], $userId, $alertType, min(round($percentage, 2), 999.99), $budget['amount_spent']]);

            $sent = (new EmailService())->sendBudgetAlert($email, $username, [
                'name'         => $budget['name'],
                'limit'        => number_format($budget['limit_amount'], 2, ',', '.'),
                'amount_spent' => number_format($budget['amount_spent'], 2, ',', '.'),
                'percentage'   => round($percentage, 1),
                'category'     => $budget['category'] ?? 'Todas as categorias',
            ]);
            if ($sent) $alertsSent++;
        } catch (Throwable $e) {
            error_log('[budget_alert] orçamento ' . $budget['id'] . ': ' . $e->getMessage());
        }
    }
    return $alertsSent;
}

/**
 * Exemplo de uso no dashboard:
 * 
 * require_once __DIR__ . '/budget_alert_helper.php';
 * $alertsSent = checkAndSendBudgetAlerts($pdo, $_SESSION['user_id'], $userEmail, $userUsername);
 */
?>