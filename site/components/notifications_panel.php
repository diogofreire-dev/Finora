<?php
/**
 * PAINEL DE NOTIFICAÇÕES
 * Mostra as notificações não lidas do utilizador
 * 
 * Uso: include __DIR__ . '/notifications_panel.php';
 * Variáveis necessárias: $pdo, $_SESSION['user_id']
 */

if (!isset($pdo) || !isset($_SESSION['user_id'])) {
    return;
}

$uid = $_SESSION['user_id'];

// Buscar notificações não lidas
$notificationsQuery = $pdo->prepare('
    SELECT id, type, title, message, data, created_at
    FROM notifications
    WHERE user_id = :uid AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 10
');
$notificationsQuery->execute([':uid' => $uid]);
$notifications = $notificationsQuery->fetchAll();

if (empty($notifications)) {
    return;
}

// HTML das notificações
?>
<div class="notifications-container" style="position: fixed; top: 80px; right: 20px; z-index: 1000; max-width: 400px;">
    <?php foreach ($notifications as $notif): ?>
        <div class="notification-toast" style="
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideInRight 0.3s ease-out;
            color: var(--text-primary);
        ">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div style="flex: 1;">
                    <strong style="display: block; margin-bottom: 5px;">
                        <?php if ($notif['type'] === 'payment_reminder'): ?>
                            <?= htmlspecialchars($notif['title']) ?>
                        <?php elseif ($notif['type'] === 'budget_alert'): ?>
                            <?= htmlspecialchars($notif['title']) ?>
                        <?php else: ?>
                            <?= htmlspecialchars($notif['title']) ?>
                        <?php endif; ?>
                    </strong>
                    <p style="margin: 0; font-size: 0.9em; color: var(--text-secondary);">
                        <?= htmlspecialchars($notif['message']) ?>
                    </p>
                    <small style="color: var(--text-secondary); margin-top: 5px; display: block;">
                        <?= date('H:i', strtotime($notif['created_at'])) ?>
                    </small>
                </div>
                <button class="btn-close" style="
                    background: none;
                    border: none;
                    cursor: pointer;
                    color: var(--text-secondary);
                    font-size: 1.2em;
                    padding: 0;
                    margin-left: 10px;
                " onclick="dismissNotification(<?= $notif['id'] ?>)">
                
                </button>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
@keyframes slideInRight {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.notification-toast {
    transition: all 0.3s ease;
}

.notification-toast:hover {
    transform: translateX(-5px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.2);
}
</style>

<script>
function dismissNotification(notificationId) {
    const element = event.target.closest('.notification-toast');
    element.style.animation = 'slideInRight 0.3s ease-out reverse';
    
    setTimeout(() => {
        fetch('../helpers/mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': <?= json_encode(csrf_token()) ?>,
            },
            body: 'notification_id=' + notificationId
        }).then(() => {
            element.remove();
        });
    }, 300);
}
</script>
<?php
