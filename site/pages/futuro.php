<?php
// site/pages/futuro.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

// Criar tabela se não existir
$pdo->exec("CREATE TABLE IF NOT EXISTS future_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    target_amount DECIMAL(10,2) NOT NULL,
    monthly_income DECIMAL(10,2) NOT NULL DEFAULT 0,
    target_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$message = ''; $messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name          = trim($_POST['name'] ?? '');
        $targetAmount  = floatval($_POST['target_amount'] ?? 0);
        $monthlyIncome = floatval($_POST['monthly_income'] ?? 0);
        $targetDate    = $_POST['target_date'] ?? '';
        if (strlen($name) >= 2 && $targetAmount > 0 && $monthlyIncome > 0 && $targetDate) {
            try {
                $stmt = $pdo->prepare("INSERT INTO future_goals (user_id, name, target_amount, monthly_income, target_date) VALUES (:uid,:name,:amount,:income,:date)");
                $stmt->execute([':uid'=>$uid,':name'=>$name,':amount'=>$targetAmount,':income'=>$monthlyIncome,':date'=>$targetDate]);
                $message = 'Objetivo criado com sucesso!'; $messageType = 'success';
            } catch (PDOException $e) { $message = 'Erro ao criar objetivo.'; $messageType = 'danger'; }
        } else { $message = 'Preenche todos os campos corretamente.'; $messageType = 'danger'; }
    }
    if ($action === 'delete') {
        $gid = intval($_POST['goal_id'] ?? 0);
        $pdo->prepare("DELETE FROM future_goals WHERE id=:id AND user_id=:uid")->execute([':id'=>$gid,':uid'=>$uid]);
        $message = 'Objetivo removido.'; $messageType = 'info';
    }
    if ($action === 'update') {
        $gid           = intval($_POST['goal_id'] ?? 0);
        $name          = trim($_POST['name'] ?? '');
        $targetAmount  = floatval($_POST['target_amount'] ?? 0);
        $monthlyIncome = floatval($_POST['monthly_income'] ?? 0);
        $targetDate    = $_POST['target_date'] ?? '';
        if ($gid && strlen($name) >= 2 && $targetAmount > 0 && $monthlyIncome > 0 && $targetDate) {
            try {
                $stmt = $pdo->prepare("UPDATE future_goals SET name=:name, target_amount=:amount, monthly_income=:income, target_date=:date WHERE id=:id AND user_id=:uid");
                $stmt->execute([':name'=>$name,':amount'=>$targetAmount,':income'=>$monthlyIncome,':date'=>$targetDate,':id'=>$gid,':uid'=>$uid]);
                $message = 'Objetivo actualizado!'; $messageType = 'success';
            } catch (PDOException $e) { $message = 'Erro ao actualizar objetivo.'; $messageType = 'danger'; }
        } else { $message = 'Preenche todos os campos corretamente.'; $messageType = 'danger'; }
    }
}

$stmt = $pdo->prepare("SELECT * FROM future_goals WHERE user_id=:uid ORDER BY target_date ASC");
$stmt->execute([':uid'=>$uid]); $goals = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT YEAR(transaction_date) as y, MONTH(transaction_date) as m, SUM(amount) as total
    FROM transactions WHERE user_id=:uid AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY y, m ORDER BY y DESC, m DESC
");
$stmt->execute([':uid'=>$uid]); $monthlySpending = $stmt->fetchAll();
$avgMonthlySpend = count($monthlySpending) > 0 ? array_sum(array_column($monthlySpending,'total')) / count($monthlySpending) : 0;

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=:uid AND YEAR(transaction_date)=YEAR(CURDATE()) AND MONTH(transaction_date)=MONTH(CURDATE())");
$stmt->execute([':uid'=>$uid]); $thisMonthSpend = floatval($stmt->fetchColumn());

function getFutureMessage($goal, $avgMonthlySpend) {
    $monthsLeft       = max(1, (strtotime($goal['target_date']) - time()) / (30*24*3600));
    $monthlySavings   = $goal['monthly_income'] - $avgMonthlySpend;
    $projectedSavings = $monthlySavings * $monthsLeft;
    $target = floatval($goal['target_amount']);
    $ratio  = $target > 0 ? $projectedSavings / $target : 0;
    if ($monthlySavings <= 0) return ['tone'=>'danger','title'=>'Estás a gastar mais do que ganhas','msg'=>'Com os teus hábitos actuais, vou chegar à data limite com €'.number_format($projectedSavings,2,',','.').' poupados. Precisas de cortar pelo menos €'.number_format(abs($monthlySavings)+($target/$monthsLeft),0,',','.').' por mês.','projected'=>$projectedSavings];
    if ($ratio >= 1.1)  return ['tone'=>'success','title'=>'Vais conseguir — e ainda sobra!','msg'=>'Parabéns! Se mantiveres este ritmo, vou chegar com €'.number_format($projectedSavings,2,',','.').' — €'.number_format($projectedSavings-$target,2,',','.').' a mais do que precisas.','projected'=>$projectedSavings];
    if ($ratio >= 0.85) return ['tone'=>'warning','title'=>'Estás quase lá, mas cuidado','msg'=>'No caminho certo, mas qualquer mês mau pode deitar tudo a perder. Projectas €'.number_format($projectedSavings,2,',','.').' de €'.number_format($target,2,',','.').' necessários.','projected'=>$projectedSavings];
    $extra = ($target - $projectedSavings) / $monthsLeft;
    return ['tone'=>'warning','title'=>'Assim não chegamos lá','msg'=>'Com os gastos actuais, vou chegar com apenas €'.number_format($projectedSavings,2,',','.').' de €'.number_format($target,2,',','.').' que precisamos. Tens de poupar mais €'.number_format($extra,0,',','.').' por mês.','projected'=>$projectedSavings];
}

$activePage='futuro'; $sidebarPrefix='../';
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Eu do Futuro — FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <style>
    body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background-color:var(--bg-primary); color:var(--text-primary); }
    .futuro-wrapper { max-width:820px; margin:0 auto; padding:0 16px 60px; }
    .page-header { display:flex; align-items:center; gap:14px; margin-bottom:28px; padding-top:8px; }
    .page-header-icon { width:42px; height:42px; border-radius:12px; background:var(--bg-secondary); border:1px solid var(--border-color); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
    .page-header h2 { font-size:20px; font-weight:600; margin:0 0 2px; }
    .page-header p  { font-size:13px; color:var(--text-secondary); margin:0; }
    .a-group { margin-bottom:20px; }
    .group-label { font-size:11px; font-weight:600; letter-spacing:.07em; text-transform:uppercase; color:var(--text-secondary); margin:0 0 8px 4px; }
    .a-card { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; overflow:hidden; }
    .a-card-body { padding:20px; }
    .a-card-title { font-size:14px; font-weight:600; color:var(--text-primary); margin:0 0 16px; display:flex; align-items:center; gap:8px; }
    .a-card-title i { color:#3498db; font-size:16px; }
    .metrics-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
    .metric { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:12px; padding:16px; }
    .metric-label { font-size:11px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
    .metric-value { font-size:22px; font-weight:700; color:var(--text-primary); line-height:1; }
    .metric-value.blue  { color:#3498db; }
    .metric-value.green { color:#1abc9c; }
    .metric-value.amber { color:#f39c12; }
    .metric-value.red   { color:#e74c3c; }
    .metric-sub { font-size:11px; color:var(--text-secondary); margin-top:4px; }
    .goal-message { border-radius:10px; padding:14px 16px; margin:14px 0; font-size:13px; line-height:1.6; }
    .goal-message.success { background:rgba(26,188,156,.08); border-color:#1abc9c; }
    .goal-message.warning { background:rgba(243,156,18,.08); border-color:#f39c12; }
    .goal-message.danger  { background:rgba(231,76,60,.08);  border-color:#e74c3c; }
    .goal-message .gm-title { font-weight:700; font-size:13px; margin-bottom:4px; }
    .goal-message.success .gm-title { color:#1abc9c; }
    .goal-message.warning .gm-title { color:#d68910; }
    .goal-message.danger  .gm-title { color:#e74c3c; }
    .goal-progress-bar  { height:6px; border-radius:3px; background:var(--border-color); overflow:hidden; margin:6px 0; }
    .goal-progress-fill { height:100%; border-radius:3px; transition:width .6s ease; }
    .goal-stats { display:flex; gap:20px; flex-wrap:wrap; margin-top:10px; padding-top:10px; border-top:1px solid var(--border-color); }
    .goal-stat  { font-size:11px; color:var(--text-secondary); }
    .goal-stat strong { display:block; font-size:15px; font-weight:700; color:var(--text-primary); }
    .btn-icon { background:none; border:none; color:var(--text-secondary); cursor:pointer; padding:4px 6px; border-radius:6px; font-size:14px; transition:color .15s,background .15s; }
    .btn-icon:hover { color:#e74c3c; background:rgba(231,76,60,.08); }
    .filter-label { font-size:11px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.05em; display:block; margin-bottom:5px; }
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button { -webkit-appearance:none; margin:0; }
    input[type=number] { -moz-appearance:textfield; appearance:textfield; }
    .filter-input { font-size:13px; padding:8px 12px; border-radius:9px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); width:100%; }
    .filter-input:focus { outline:none; border-color:#3498db; }
    .btn-primary-s { font-size:13px; font-weight:500; padding:9px 20px; border-radius:9px; border:none; background:#3498db; color:#fff; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
    .btn-primary-s:hover { background:#2980b9; }
    .empty-state { text-align:center; padding:48px 20px; }
    .empty-state i { font-size:48px; color:var(--border-color); display:block; margin-bottom:12px; }
    .empty-state h5 { font-size:15px; color:var(--text-secondary); margin-bottom:6px; }
    .empty-state p  { font-size:13px; color:var(--text-secondary); }
    [data-theme="dark"] .filter-input { background:#1a1d29; color:#ecf0f1; border-color:#34495e; }
    @media(max-width:640px) { .metrics-grid { grid-template-columns:1fr 1fr; } }
  </style>
</head>
<body>
<div class="app-layout">
<?php include __DIR__ . '/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="futuro-wrapper">

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-hourglass-split"></i></div>
    <div>
      <h2>Metas</h2>
      <p>Define um objetivo e descobre o que os teus hábitos actuais vão produzir</p>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?=$messageType?> alert-dismissible fade show" role="alert">
      <?=$message?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($avgMonthlySpend > 0): ?>
  <div class="a-group">
    <p class="group-label">Os teus números</p>
    <div class="metrics-grid">
      <div class="metric">
        <p class="metric-label">Média mensal (6m)</p>
        <p class="metric-value blue">€<?=number_format($avgMonthlySpend,2,',','.')?></p>
        <p class="metric-sub">gasto por mês</p>
      </div>
      <div class="metric">
        <p class="metric-label">Este mês</p>
        <p class="metric-value <?=$thisMonthSpend > $avgMonthlySpend ? 'red' : 'green'?>">€<?=number_format($thisMonthSpend,2,',','.')?></p>
        <p class="metric-sub"><?=$thisMonthSpend > $avgMonthlySpend ? 'acima da média' : 'abaixo da média'?></p>
      </div>
      <div class="metric">
        <p class="metric-label">Objetivos activos</p>
        <p class="metric-value"><?=count($goals)?></p>
        <p class="metric-sub">em acompanhamento</p>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="a-group">
    <p class="group-label">Os teus objetivos</p>
    <?php if (empty($goals)): ?>
    <div class="a-card">
      <div class="empty-state">
        <i class="bi bi-hourglass"></i>
        <h5>Ainda não tens objetivos</h5>
        <p>Cria o primeiro abaixo e descobre o que o teu eu do futuro vai dizer.</p>
      </div>
    </div>
    <?php else: ?>
    <?php foreach ($goals as $g):
      $fm         = getFutureMessage($g, $avgMonthlySpend);
      $monthsLeft = max(0, round((strtotime($g['target_date']) - time()) / (30*24*3600)));
      $projPct    = $g['target_amount'] > 0 ? min(100, (abs($fm['projected']) / $g['target_amount']) * 100) : 0;
      $barColor   = $fm['tone'] === 'success' ? '#1abc9c' : ($fm['tone'] === 'warning' ? '#f39c12' : '#e74c3c');
    ?>
    <div class="a-card" style="margin-bottom:12px">
      <div class="a-card-body">
        <div style="display:flex;align-items:flex-start;justify-content:space-between">
          <div>
            <div style="font-size:15px;font-weight:600;color:var(--text-primary)"><?=htmlspecialchars($g['name'])?></div>
            <div style="font-size:12px;color:var(--text-secondary);margin-top:2px">Prazo: <?=date('d/m/Y', strtotime($g['target_date']))?> &middot; <?=$monthsLeft?> mes<?=$monthsLeft==1?'':'es'?> restante<?=$monthsLeft==1?'':'s'?></div>
          </div>
          <div style="display:flex;gap:4px">
            <button type="button" class="btn-icon" onclick="openEditModal(<?=$g['id']?>, '<?=htmlspecialchars(addslashes($g['name']))?>', '<?=$g['target_amount']?>', '<?=$g['monthly_income']?>', '<?=$g['target_date']?>')"><i class="bi bi-pencil"></i></button>
            <form method="post" style="display:inline"><?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="goal_id" value="<?=$g['id']?>">
              <button type="submit" class="btn-icon" onclick="return confirm('Remover este objetivo?')"><i class="bi bi-trash3"></i></button>
            </form>
          </div>
        </div>
        <div class="goal-message <?=$fm['tone']?>">
          <div class="gm-title"><?=$fm['title']?></div><?=$fm['msg']?>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-secondary);margin-top:12px">
          <span>Projeção actual</span><span><?=number_format($projPct,0)?>% do objetivo</span>
        </div>
        <div class="goal-progress-bar">
          <div class="goal-progress-fill" style="width:<?=$projPct?>%;background:<?=$barColor?>"></div>
        </div>
        <div class="goal-stats">
          <div class="goal-stat"><strong>€<?=number_format($g['target_amount'],2,',','.')?></strong>Objetivo</div>
          <div class="goal-stat"><strong>€<?=number_format($fm['projected'],2,',','.')?></strong>Projeção</div>
          <div class="goal-stat"><strong>€<?=number_format($g['monthly_income'],2,',','.')?>/mês</strong>Rendimento</div>
          <div class="goal-stat"><strong>€<?=number_format($g['monthly_income']-$avgMonthlySpend,2,',','.')?>/mês</strong>Poupança actual</div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="a-group">
    <p class="group-label">Novo objetivo</p>
    <div class="a-card">
      <div class="a-card-body">
        <p class="a-card-title"><i class="bi bi-plus-circle"></i> Criar objetivo</p>
        <form method="post"><?= csrf_field() ?>
          <input type="hidden" name="action" value="create">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="filter-label">Nome do objetivo</label>
              <input type="text" name="name" class="filter-input" placeholder="ex: Férias de Verão, Portátil novo…" required>
            </div>
            <div class="col-md-6">
              <label class="filter-label">Quanto queres poupar (€)</label>
              <input type="number" name="target_amount" class="filter-input" step="0.01" min="1" placeholder="ex: 2000" required>
            </div>
            <div class="col-md-6">
              <label class="filter-label">Rendimento mensal líquido (€)</label>
              <input type="number" name="monthly_income" class="filter-input" step="0.01" min="1" placeholder="ex: 1200" required>
              <p style="font-size:11px;color:var(--text-secondary);margin:4px 0 0">Usado para calcular o que podes poupar por mês</p>
            </div>
            <div class="col-md-6">
              <label class="filter-label">Data limite</label>
              <input type="date" name="target_date" class="filter-input" min="<?=date('Y-m-d')?>" required>
            </div>
            <div class="col-12">
              <button type="submit" class="btn-primary-s"><i class="bi bi-hourglass-split"></i> Criar objetivo</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

</div>
</div>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Modal de edição -->
<div id="editModal" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,0.55);align-items:center;justify-content:center">
  <div style="background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:16px;padding:28px;width:100%;max-width:480px;margin:16px;box-shadow:0 20px 60px rgba(0,0,0,0.3)">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <h3 style="font-size:16px;font-weight:600;margin:0">Editar objetivo</h3>
      <button onclick="closeEditModal()" style="background:none;border:none;color:var(--text-secondary);font-size:20px;cursor:pointer;padding:0;line-height:1">&times;</button>
    </div>
    <form method="post"><?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="goal_id" id="edit_goal_id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div style="grid-column:1/-1">
          <label class="filter-label">Nome do objetivo</label>
          <input type="text" name="name" id="edit_name" class="filter-input" required>
        </div>
        <div>
          <label class="filter-label">Quanto queres poupar (€)</label>
          <input type="number" name="target_amount" id="edit_target_amount" class="filter-input" step="0.01" min="1" required>
        </div>
        <div>
          <label class="filter-label">Rendimento mensal líquido (€)</label>
          <input type="number" name="monthly_income" id="edit_monthly_income" class="filter-input" step="0.01" min="1" required>
        </div>
        <div style="grid-column:1/-1">
          <label class="filter-label">Data limite</label>
          <input type="date" name="target_date" id="edit_target_date" class="filter-input" required>
        </div>
        <div style="grid-column:1/-1;display:flex;gap:8px;margin-top:4px">
          <button type="submit" class="btn-primary-s" style="flex:1;justify-content:center"><i class="bi bi-check2"></i> Guardar alterações</button>
          <button type="button" onclick="closeEditModal()" style="flex:1;font-size:13px;font-weight:500;padding:9px 20px;border-radius:9px;border:1px solid var(--border-color);background:transparent;color:var(--text-secondary);cursor:pointer">Cancelar</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(id, name, amount, income, date) {
  document.getElementById('edit_goal_id').value = id;
  document.getElementById('edit_name').value = name;
  document.getElementById('edit_target_amount').value = amount;
  document.getElementById('edit_monthly_income').value = income;
  document.getElementById('edit_target_date').value = date;
  const m = document.getElementById('editModal');
  m.style.display = 'flex';
}
function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
}
document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) closeEditModal();
});
</script>
</body>
</html>