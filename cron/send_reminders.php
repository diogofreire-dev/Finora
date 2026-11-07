<?php
/**
 * CRON JOB: Enviar Lembretes de Pagamento por Email
 * 
 * Este script deve ser executado diariamente (ex: 09:00 da manhã)
 * 
 * Configuração no crontab (Linux/cPanel):
 * 0 9 * * * /usr/bin/php /caminho/para/cron/send_reminders.php
 * 
 * Para testar localmente:
 * php /caminho/para/cron/send_reminders.php
 */

// Só pode correr pela linha de comandos (cron), nunca por um pedido web
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// ===== CONFIGURAÇÃO =====
define('ROOT_PATH', __DIR__ . '/..');
require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/config/db.php';
require_once ROOT_PATH . '/config/EmailService.php';

// Log file para debug
$logFile = __DIR__ . '/send_reminders.log';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage; // Também imprimir no terminal
}

try {
    writeLog('=== CRON JOB INICIADO ===');
    
    $emailService = new EmailService();
    $remindersEnviados = 0;
    $erros = 0;
    
    // ===== PASSO 1: Buscar lembretes para enviar =====
    $query = $pdo->prepare('
        SELECT 
            pr.id,
            pr.user_id,
            pr.name,
            pr.amount,
            pr.category,
            pr.due_date,
            pr.notify_days_before,
            pr.notify_method,
            pr.last_notification_sent,
            c.name as card_name,
            u.email,
            u.username,
            s.currency,
            s.language,
            DATEDIFF(pr.due_date, CURDATE()) as days_until
        FROM payment_reminders pr
        JOIN users u ON u.id = pr.user_id
        LEFT JOIN user_settings s ON s.user_id = pr.user_id
        LEFT JOIN cards c ON c.id = pr.card_id
        WHERE pr.active = 1
        AND pr.due_date >= CURDATE()
        AND DATEDIFF(pr.due_date, CURDATE()) <= pr.notify_days_before
        AND (pr.last_notification_sent IS NULL 
             OR DATE(pr.last_notification_sent) < CURDATE())
        ORDER BY pr.due_date ASC
    ');
    
    $query->execute();
    $reminders = $query->fetchAll();
    
    writeLog("Total de lembretes a enviar: " . count($reminders));
    
    // ===== PASSO 2: Enviar notificações para cada lembrete =====
    foreach ($reminders as $reminder) {
        try {
            $daysUntil = (int)$reminder['days_until'];
            // Email e notificação na língua de cada utilizador (o cron corre sem sessão)
            set_lang($reminder['language'] ?? null);
            $notifyMethod = $reminder['notify_method'] ?? 'email';
            
            // Dados para o template
            $reminderData = [
                'name' => $reminder['name'],
                'amount' => $reminder['amount'],
                'currency' => valid_currency($reminder['currency']),
                'category' => $reminder['category'] ?? 'Sem categoria',
                'due_date' => date('d/m/Y', strtotime($reminder['due_date'])),
                'days_until' => $daysUntil,
                'card_name' => $reminder['card_name'] ?? null
            ];
            
            $enviado = false;
            
            // Enviar por email se solicitado
            if ($notifyMethod === 'email' || $notifyMethod === 'both') {
                try {
                    $emailEnviado = $emailService->sendPaymentReminder(
                        $reminder['email'],
                        $reminder['username'],
                        $reminderData
                    );
                    if ($emailEnviado) {
                        writeLog("Email enviado para {$reminder['username']} - {$reminder['name']} (vence em {$daysUntil} dias)");
                        $enviado = true;
                    }
                } catch (Exception $e) {
                    writeLog("Erro ao enviar email para {$reminder['username']}: " . $e->getMessage());
                }
            }
            
            // Criar notificação no site se solicitado
            if ($notifyMethod === 'site' || $notifyMethod === 'both') {
                try {
                    $notifQuery = $pdo->prepare('
                        INSERT INTO notifications (user_id, type, title, message, data, created_at)
                        VALUES (:uid, :type, :title, :message, :data, NOW())
                    ');
                    $notifQuery->execute([
                        ':uid' => $reminder['user_id'],
                        ':type' => 'payment_reminder',
                        ':title' => t('Lembrete de pagamento: {name}', ['name' => $reminder['name']]),
                        ':message' => t('O pagamento "{name}" ({amount}) vence em {n} dias', ['name' => $reminder['name'], 'amount' => fmt_money($reminder['amount'], 2, $reminderData['currency']), 'n' => $daysUntil]),
                        ':data' => json_encode([
                            'reminder_id' => $reminder['id'],
                            'name' => $reminder['name'],
                            'amount' => $reminder['amount'],
                            'due_date' => $reminder['due_date']
                        ])
                    ]);
                    writeLog("Notificação criada no site para {$reminder['username']} - {$reminder['name']}");
                    $enviado = true;
                } catch (Exception $e) {
                    writeLog("Erro ao criar notificação no site para {$reminder['username']}: " . $e->getMessage());
                }
            }
            
            if ($enviado) {
                // Atualizar last_notification_sent
                $updateQuery = $pdo->prepare('
                    UPDATE payment_reminders 
                    SET last_notification_sent = NOW() 
                    WHERE id = ?
                ');
                $updateQuery->execute([$reminder['id']]);
                
                $remindersEnviados++;
            } else {
                $erros++;
                writeLog("ERRO ao enviar qualquer notificação para {$reminder['username']} - {$reminder['name']}");
            }
        } catch (Exception $e) {
            $erros++;
            writeLog("EXCEÇÃO ao processar lembrete {$reminder['id']}: " . $e->getMessage());
        }
    }
    
    // ===== PASSO 3: Enviar alertas de orçamento (FASE 2) =====
    // Este código será adicionado na próxima fase
    
    writeLog("=== CRON JOB FINALIZADO ===");
    writeLog("Lembretes enviados: {$remindersEnviados}");
    writeLog("Erros: {$erros}");
    writeLog('');
    
} catch (Exception $e) {
    writeLog("✗ ERRO CRÍTICO: " . $e->getMessage());
    die("ERRO: " . $e->getMessage());
}
?>
