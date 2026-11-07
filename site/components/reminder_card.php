<?php
// site/reminder_card.php
// Template reutilizável para mostrar um card de lembrete
// Variável $r deve estar disponível no contexto
?>
<div class="reminder-card <?=$r['status']?> <?=!$r['active'] ? 'opacity-50' : ''?>">
  <div class="d-flex justify-content-between align-items-start mb-3">
    <div class="flex-grow-1">
      <h5 class="mb-1">
        <?=htmlspecialchars($r['name'])?>
        <?php if ($r['recurrence'] !== 'once'): ?>
          <span class="recurrence-badge">
            <i class="bi bi-arrow-repeat"></i>
            <?php
              echo match($r['recurrence']) {
                'weekly' => 'Semanal',
                'monthly' => 'Mensal',
                'yearly' => 'Anual',
                default => ''
              };
            ?>
          </span>
        <?php endif; ?>
      </h5>
      <div class="d-flex gap-2 align-items-center flex-wrap mt-2">
        <span class="status-badge <?=$r['status']?>">
          <i class="bi bi-<?=match($r['status']) {
            'overdue' => 'exclamation-triangle',
            'upcoming' => 'clock-history',
            default => 'calendar-check'
          }?>"></i>
          <?php
            $dueDate = new DateTime($r['due_date']);
            $today = new DateTime();
            $diff = $today->diff($dueDate);
            
            if ($r['status'] === 'overdue') {
              echo $diff->days . ' dia(s) atrasado';
            } elseif ($r['status'] === 'upcoming') {
              echo 'Vence em ' . $diff->days . ' dia(s)';
            } else {
              echo 'Vence: ' . $dueDate->format('d/m/Y');
            }
          ?>
        </span>
        
        <?php
          $notifyMethod = $r['notify_method'] ?? 'email';
          
          // Definir ícone e label baseado no método de notificação
          [$notifyIcon, $notifyLabel] = match($notifyMethod) {
            'email' => ['<i class="bi bi-envelope"></i>', 'Email'],
            'site' => ['<i class="bi bi-bell"></i>', 'Site'],
            'both' => ['<i class="bi bi-envelope-plus"></i>', 'Email+Site'],
            default => ['<i class="bi bi-envelope"></i>', 'Email']
          };
        ?>
        <span class="badge bg-info"><?= $notifyIcon ?> <?= $notifyLabel ?></span>
        
        <?php if ($r['category']): ?>
          <span class="badge bg-secondary"><?=htmlspecialchars($r['category'])?></span>
        <?php endif; ?>
        
        <?php if ($r['card_name']): ?>
          <small class="text-muted">
            <i class="bi bi-credit-card"></i> <?=htmlspecialchars($r['card_name'])?>
          </small>
        <?php else: ?>
          <small class="text-muted">
            <i class="bi bi-cash"></i> Dinheiro
          </small>
        <?php endif; ?>
      </div>
    </div>
    <div class="text-end ms-3">
      <h4 class="mb-0 text-danger"><?= fmt_money($r['amount']) ?></h4>
      <?php if ($r['last_paid_date']): ?>
        <small class="text-muted">Último: <?=date('d/m', strtotime($r['last_paid_date']))?></small>
      <?php endif; ?>
    </div>
  </div>
  
  <div class="d-flex gap-2 justify-content-between flex-wrap">
    <div class="d-flex gap-2 flex-wrap reminder-actions">
      <?php if ($r['active'] && ($r['status'] === 'overdue' || $r['status'] === 'upcoming')): ?>
        <button type="button" class="btn btn-sm btn-success" onclick="openMarkPaidModal(<?=$r['id']?>)">
          <i class="bi bi-check-circle"></i> Marcar como Pago
        </button>
      <?php endif; ?>

      <button type="button" class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?=htmlspecialchars(json_encode($r))?>)">
        <i class="bi bi-pencil"></i> Editar
      </button>

      <form method="post" class="d-inline"><?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="reminder_id" value="<?=$r['id']?>">
        <button type="submit" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-<?=$r['active'] ? 'pause' : 'play'?>-circle"></i>
          <?=$r['active'] ? 'Desativar' : 'Ativar'?>
        </button>
      </form>
    </div>

    <form method="post" class="d-inline" onsubmit="return confirm('Tens a certeza? O histórico será mantido.');"><?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="reminder_id" value="<?=$r['id']?>">
      <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
        <i class="bi bi-trash"></i>
      </button>
    </form>
  </div>
</div>