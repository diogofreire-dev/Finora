<?php
// site/pages/dados.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid AND active = 1 ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

$categories = ['Compras','Alimentação','Transporte','Saúde','Entretenimento','Educação','Casa','Outros'];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = :uid");
$stmt->execute([':uid' => $uid]);
$totalTx = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM cards WHERE user_id = :uid");
$stmt->execute([':uid' => $uid]);
$totalCards = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM budgets WHERE user_id = :uid");
$stmt->execute([':uid' => $uid]);
$totalBudgets = $stmt->fetchColumn();

$importMsg = '';
$importType = '';
if (!empty($_GET['import_ok'])) {
    $importMsg = "Importação concluída: {$_GET['imported']} transações importadas com sucesso!";
    $importType = 'success';
}
if (!empty($_GET['import_error'])) {
    $importMsg = urldecode($_GET['import_error']);
    $importType = 'danger';
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?= $currentTheme ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dados & Relatórios - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background-color: var(--bg-primary);
      color: var(--text-primary);
    }
    .settings-wrapper {
      max-width: 700px;
      margin: 0 auto;
      padding: 0 16px 60px;
    }
    .page-header {
      display: flex; align-items: center; gap: 14px;
      margin-bottom: 32px; padding-top: 8px;
    }
    .page-header-icon {
      width: 42px; height: 42px; border-radius: 12px;
      background: var(--bg-secondary); border: 1px solid var(--border-color);
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; flex-shrink: 0;
    }
    .page-header h2 { font-size: 20px; font-weight: 600; margin: 0 0 2px; color: var(--text-primary); }
    .page-header p  { font-size: 13px; color: var(--text-secondary); margin: 0; }
    .settings-group { margin-bottom: 24px; }
    .group-label {
      font-size: 11px; font-weight: 600; letter-spacing: .07em;
      text-transform: uppercase; color: var(--text-secondary); margin: 0 0 8px 4px;
    }
    .settings-card {
      background: var(--bg-secondary); border: 1px solid var(--border-color);
      border-radius: 14px; overflow: hidden;
    }
    .s-row {
      display: flex; align-items: center; justify-content: space-between;
      gap: 16px; padding: 16px 20px; transition: background .15s;
    }
    .s-row:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .s-row-label { font-size: 14px; font-weight: 500; color: var(--text-primary); margin: 0 0 2px; }
    .s-row-sub   { font-size: 12px; color: var(--text-secondary); margin: 0; }
    .s-row-action { flex-shrink: 0; }
    .s-action-icon {
      width: 38px; height: 38px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 17px; flex-shrink: 0;
    }
    .icon-red    { background: #fef2f2; color: #ef4444; }
    .icon-green  { background: #f0fdf4; color: #22c55e; }
    .icon-blue   { background: #eff6ff; color: #3b82f6; }
    .icon-orange { background: #fff7ed; color: #f97316; }
    .icon-purple { background: #faf5ff; color: #a855f7; }
    .icon-teal   { background: #f0fdfa; color: #14b8a6; }
    [data-theme="dark"] .icon-red    { background: rgba(239,68,68,.15); }
    [data-theme="dark"] .icon-green  { background: rgba(34,197,94,.15); }
    [data-theme="dark"] .icon-blue   { background: rgba(59,130,246,.15); }
    [data-theme="dark"] .icon-orange { background: rgba(249,115,22,.15); }
    [data-theme="dark"] .icon-purple { background: rgba(168,85,247,.15); }
    [data-theme="dark"] .icon-teal   { background: rgba(20,184,166,.15); }
    .btn-s {
      font-size: 13px; font-weight: 500; padding: 7px 16px;
      border-radius: 9px; border: 1px solid var(--border-color);
      background: var(--bg-primary); color: var(--text-primary);
      cursor: pointer; transition: all .15s; text-decoration: none;
      display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-s:hover { border-color: #999; background: var(--bg-hover); color: var(--text-primary); }
    .btn-s-red    { background: #ef4444; color: #fff; border-color: #ef4444; }
    .btn-s-red:hover { background: #dc2626; color: #fff; border-color: #dc2626; }
    .btn-s-orange { background: #f97316; color: #fff; border-color: #f97316; }
    .btn-s-orange:hover { background: #ea6c0a; color: #fff; border-color: #ea6c0a; }
    .btn-s-green  { background: #22c55e; color: #fff; border-color: #22c55e; }
    .btn-s-green:hover { background: #16a34a; color: #fff; border-color: #16a34a; }
    .btn-s-purple { background: #a855f7; color: #fff; border-color: #a855f7; }
    .btn-s-purple:hover { background: #9333ea; color: #fff; border-color: #9333ea; }
    .btn-s-teal   { background: #14b8a6; color: #fff; border-color: #14b8a6; }
    .btn-s-teal:hover { background: #0d9488; color: #fff; border-color: #0d9488; }
.btn-s:disabled { opacity: 1; cursor: not-allowed; pointer-events: none; filter: grayscale(.2); }

    .stats-row { display: flex; gap: 0; }
    .stat-cell { flex: 1; padding: 14px 20px; text-align: center; }
    .stat-cell:not(:last-child) { border-right: 1px solid var(--border-color); }
    .stat-cell .stat-num { font-size: 22px; font-weight: 700; color: var(--text-primary); line-height: 1; margin-bottom: 3px; }
    .stat-cell .stat-label { font-size: 11px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .05em; }
    .s-alert {
      border-radius: 10px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px;
      display: flex; align-items: flex-start; gap: 10px; border: 1px solid transparent;
    }
    .s-alert-success { background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.3); color: #065f46; }
    .s-alert-danger  { background: rgba(239,68,68,.1);  border-color: rgba(239,68,68,.3);  color: #7f1d1d; }
    [data-theme="dark"] .s-alert-success { color: #6ee7b7; }
    [data-theme="dark"] .s-alert-danger  { color: #fca5a5; }
    .s-form { padding: 0 20px 20px; }
    .s-form .form-label {
      font-size: 12px; font-weight: 600; color: var(--text-secondary);
      text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px;
    }
    .s-form .form-control, .s-form .form-select {
      font-size: 14px; border: 1px solid var(--border-color); border-radius: 9px;
      padding: 9px 12px; background: var(--bg-primary); color: var(--text-primary); transition: border-color .15s;
    }
    .s-form .form-control:focus, .s-form .form-select:focus {
      border-color: #3498db; box-shadow: none; background: var(--bg-primary); color: var(--text-primary);
    }
    [data-theme="dark"] .s-form .form-control,
    [data-theme="dark"] .s-form .form-select {
      background: var(--bg-primary); color: var(--text-primary); border-color: var(--border-color);
    }
    .upload-zone {
      border: 2px dashed var(--border-color); border-radius: 10px; padding: 24px;
      text-align: center; cursor: pointer; transition: border-color .2s, background .2s;
      background: var(--bg-primary);
    }
    .upload-zone:hover, .upload-zone.drag-over { border-color: #3498db; background: rgba(52,152,219,.05); }
    .upload-zone i { font-size: 28px; color: #3498db; margin-bottom: 8px; }
    .upload-zone p { margin: 0; font-size: 13px; color: var(--text-secondary); }
    .template-table th { font-size: 11px; font-weight: 700; letter-spacing: .5px; background: var(--bg-primary); color: var(--text-secondary); }
    .template-table td { font-size: 12px; color: var(--text-primary); background: transparent; }
    .template-table, .template-table th, .template-table td { border-color: var(--border-color) !important; }
    .tip-row { display: flex; gap: 12px; align-items: flex-start; padding: 14px 20px; }
    .tip-row:not(:last-child) { border-bottom: 1px solid var(--border-color); }
    .tip-icon { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
    .tip-title { font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 2px; }
    .tip-sub   { font-size: 12px; color: var(--text-secondary); }
    @media (max-width: 600px) {
      .stats-row { flex-direction: column; }
      .stat-cell:not(:last-child) { border-right: none; border-bottom: 1px solid var(--border-color); }
      .s-row { flex-wrap: wrap; }
    }
  </style>
</head>
<body>
<div class="app-layout">
<?php
$activePage    = 'dados';
$sidebarPrefix = '../';
include __DIR__ . '/../components/sidebar.php';
?>
<main class="main-content">
<div class="page-content">
<div class="settings-wrapper">

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-database"></i></div>
    <div>
      <h2>Dados & Relatórios</h2>
      <p>Exporta, importa e faz backup de todos os teus dados financeiros</p>
    </div>
  </div>

  <?php if ($importMsg): ?>
    <?php $icon = $importType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>
    <div class="s-alert s-alert-<?= $importType ?>">
      <i class="bi bi-<?= $icon ?>" style="flex-shrink:0;margin-top:1px"></i>
      <span><?= htmlspecialchars($importMsg) ?></span>
    </div>
  <?php endif; ?>

  <!-- RESUMO -->
  <div class="settings-group">
    <p class="group-label">Resumo</p>
    <div class="settings-card">
      <div class="stats-row">
        <div class="stat-cell">
          <div class="stat-num"><?= $totalTx ?></div>
          <div class="stat-label">Transações</div>
        </div>
        <div class="stat-cell">
          <div class="stat-num"><?= $totalCards ?></div>
          <div class="stat-label">Cartões</div>
        </div>
        <div class="stat-cell">
          <div class="stat-num"><?= $totalBudgets ?></div>
          <div class="stat-label">Orçamentos</div>
        </div>
      </div>
    </div>
  </div>

  <!-- FILTROS -->
  <div class="settings-group">
    <p class="group-label">Filtros de exportação</p>
    <div class="settings-card">
      <div class="s-row" style="border-bottom:none;padding-bottom:4px">
        <div>
          <p class="s-row-label">Filtros comuns</p>
          <p class="s-row-sub">Aplicados a todas as exportações abaixo</p>
        </div>
      </div>
      <div class="s-form" style="padding-top:8px">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Mês</label>
            <input type="month" id="filterMonth" class="form-control" value="<?= date('Y-m') ?>">
            <div style="font-size:11px;color:var(--text-secondary);margin-top:4px">Deixa em branco para todos</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Categoria</label>
            <select id="filterCategory" class="form-select">
              <option value="">Todas</option>
              <?php foreach ($categories as $cat): ?>
                <option><?= htmlspecialchars($cat) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Cartão</label>
            <select id="filterCard" class="form-select">
              <option value="">Todos</option>
              <?php foreach ($cards as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- EXPORTAÇÃO -->
  <div class="settings-group">
    <p class="group-label">Exportação</p>
    <div class="settings-card">

      <div class="s-row">
        <div style="display:flex;align-items:center;gap:12px;flex:1">
          <div class="s-action-icon icon-red"><i class="bi bi-file-earmark-pdf"></i></div>
          <div>
            <p class="s-row-label">Relatório PDF Completo</p>
            <p class="s-row-sub">Todas as transações com estatísticas por categoria e resumo de cartões</p>
          </div>
        </div>
        <div class="s-row-action">
          <button class="btn-s btn-s-red" onclick="exportPDF('full')"><i class="bi bi-download"></i> Exportar</button>
        </div>
      </div>

      <div class="s-row">
        <div style="display:flex;align-items:center;gap:12px;flex:1">
          <div class="s-action-icon icon-orange"><i class="bi bi-file-earmark-bar-graph"></i></div>
          <div>
            <p class="s-row-label">Relatório PDF Resumido</p>
            <p class="s-row-sub">Últimas 20 transações e resumo geral — ideal para uma vista rápida</p>
          </div>
        </div>
        <div class="s-row-action">
          <button class="btn-s btn-s-orange" onclick="exportPDF('summary')"><i class="bi bi-download"></i> Exportar</button>
        </div>
      </div>

      <div class="s-row">
        <div style="display:flex;align-items:center;gap:12px;flex:1">
          <div class="s-action-icon icon-green"><i class="bi bi-file-earmark-spreadsheet"></i></div>
          <div>
            <p class="s-row-label">Exportar Excel / CSV</p>
            <p class="s-row-sub">Compatível com Excel, Google Sheets e outros — inclui todas as colunas</p>
          </div>
        </div>
        <div class="s-row-action">
          <button class="btn-s btn-s-green" onclick="exportCSV()"><i class="bi bi-download"></i> Exportar</button>
        </div>
      </div>

      <div class="s-row">
        <div style="display:flex;align-items:center;gap:12px;flex:1">
          <div class="s-action-icon icon-purple"><i class="bi bi-box-arrow-up"></i></div>
          <div>
            <p class="s-row-label">Backup Completo (JSON)</p>
            <p class="s-row-sub">Exporta tudo: transações, cartões e orçamentos num ficheiro JSON seguro</p>
          </div>
        </div>
        <div class="s-row-action">
          <button class="btn-s btn-s-purple" onclick="exportJSON()"><i class="bi bi-shield-check"></i> Backup</button>
        </div>
      </div>

    </div>
  </div>

  <!-- IMPORTAÇÃO -->
  <div class="settings-group">
    <p class="group-label">Importação</p>
    <div class="settings-card">

      <div class="s-row" style="border-bottom:none;padding-bottom:4px">
        <div style="display:flex;align-items:center;gap:12px;flex:1">
          <div class="s-action-icon icon-teal"><i class="bi bi-file-earmark-arrow-up"></i></div>
          <div>
            <p class="s-row-label">Importar Transações CSV</p>
            <p class="s-row-sub">Importa transações a partir de um ficheiro CSV (Excel, Google Sheets)</p>
          </div>
        </div>
      </div>

      <div class="s-form" style="padding-top:4px">
        <div class="mb-3">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
            <span style="font-size:10px;font-weight:700;letter-spacing:.05em;padding:3px 8px;border-radius:5px;background:var(--bg-primary);border:1px solid var(--border-color);color:var(--text-secondary);text-transform:uppercase">Formato esperado</span>
            <button class="btn-s" style="padding:4px 12px;font-size:12px" onclick="downloadTemplate()">
              <i class="bi bi-download"></i> Template
            </button>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered template-table mb-0">
              <thead><tr><th>data</th><th>descricao</th><th>categoria</th><th>valor</th><th>cartao</th></tr></thead>
              <tbody>
                <tr><td>2024-01-15</td><td>Supermercado</td><td>Alimentação</td><td>45.90</td><td>Visa</td></tr>
                <tr><td>2024-01-18</td><td>Netflix</td><td>Entretenimento</td><td>12.99</td><td></td></tr>
              </tbody>
            </table>
          </div>
          <p style="font-size:11px;color:var(--text-secondary);margin-top:6px">A coluna <strong>cartao</strong> é opcional. O valor deve ser em euros (ex: 45.90).</p>
        </div>
        <form action="../helpers/import_csv.php" method="post" enctype="multipart/form-data" id="csvImportForm"><?= csrf_field() ?>
          <div class="upload-zone" id="uploadZone" onclick="document.getElementById('csvFile').click()">
            <i class="bi bi-cloud-upload d-block"></i>
            <p class="fw-semibold" style="color:var(--text-primary)">Clica ou arrasta o ficheiro CSV aqui</p>
            <p id="fileName" style="display:none;color:#3498db;margin-top:4px"></p>
          </div>
          <input type="file" id="csvFile" name="csv_file" accept=".csv,.txt" style="display:none" onchange="handleFileSelect(this)">
          <div style="display:flex;align-items:center;gap:10px;margin-top:12px">
            <button type="submit" class="btn-s btn-s-teal" id="importBtn" disabled>
              <i class="bi bi-upload"></i> Importar Transações
            </button>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-secondary);cursor:pointer;margin:0">
              <input class="form-check-input" type="checkbox" name="skip_duplicates" id="skipDuplicates" checked style="margin:0">
              Ignorar duplicados
            </label>
          </div>
        </form>
      </div>

      <div class="s-row" style="border-top:1px solid var(--border-color)">
        <div style="display:flex;align-items:center;gap:12px;flex:1">
          <div class="s-action-icon icon-purple"><i class="bi bi-box-arrow-in-down"></i></div>
          <div>
            <p class="s-row-label">Restaurar Backup JSON</p>
            <p class="s-row-sub">Restaura transações a partir de um backup JSON — não afeta cartões existentes</p>
          </div>
        </div>
        <div class="s-row-action">
          <form action="../helpers/import_json.php" method="post" enctype="multipart/form-data" style="display:flex;align-items:center;gap:8px"><?= csrf_field() ?>
            <label class="btn-s" style="cursor:pointer;margin:0">
              <i class="bi bi-folder2-open"></i> Escolher
              <input type="file" name="json_file" accept=".json" style="display:none"
                     onchange="this.closest('form').querySelector('.json-name').textContent = this.files[0]?.name || ''; this.closest('form').querySelector('[type=submit]').disabled = !this.files[0]">
            </label>
            <span class="json-name" style="font-size:12px;color:var(--text-secondary);max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span>
            <button type="submit" class="btn-s btn-s-purple" disabled>
              <i class="bi bi-shield-check"></i> Restaurar
            </button>
          </form>
        </div>
      </div>

    </div>
  </div>

  <!-- DICAS -->
  <div class="settings-group">
    <p class="group-label">Dicas</p>
    <div class="settings-card">
      <div class="tip-row">
        <div class="tip-icon icon-blue"><i class="bi bi-info-circle"></i></div>
        <div>
          <div class="tip-title">CSV no Excel</div>
          <div class="tip-sub">Abre o CSV no Excel com <em>Dados → Obter Dados → De Texto/CSV</em> para formatação correta.</div>
        </div>
      </div>
      <div class="tip-row">
        <div class="tip-icon icon-purple"><i class="bi bi-shield-check"></i></div>
        <div>
          <div class="tip-title">Backups regulares</div>
          <div class="tip-sub">Faz um backup JSON mensal para não perder dados. Guarda num local seguro.</div>
        </div>
      </div>
      <div class="tip-row">
        <div class="tip-icon icon-teal"><i class="bi bi-table"></i></div>
        <div>
          <div class="tip-title">Importar do banco</div>
          <div class="tip-sub">Exporta o extrato do teu banco em CSV, ajusta as colunas ao template e importa aqui.</div>
        </div>
      </div>
    </div>
  </div>

</div><!-- /settings-wrapper -->
</div><!-- /page-content -->
</main>
</div><!-- /app-layout -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function getFilters() {
  return new URLSearchParams({
    month:    document.getElementById('filterMonth').value,
    category: document.getElementById('filterCategory').value,
    card_id:  document.getElementById('filterCard').value,
  }).toString();
}
function exportPDF(type) {
  window.location.href = '../helpers/generate_report_pdf.php?' + getFilters() + '&type=' + type + '&include_cards=1&include_stats=1';
}
function exportCSV() { window.location.href = '../helpers/export_csv.php?' + getFilters(); }
function exportJSON() { window.location.href = '../helpers/export_json.php'; }
function downloadTemplate() {
  const csv = 'data,descricao,categoria,valor,cartao\n2024-01-15,Supermercado,Alimentação,45.90,Visa\n2024-01-18,Netflix,Entretenimento,12.99,\n';
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'template_finora.csv';
  a.click();
}
function handleFileSelect(input) {
  const file = input.files[0];
  if (!file) return;
  document.getElementById('fileName').textContent = '📄 ' + file.name;
  document.getElementById('fileName').style.display = 'block';
  document.getElementById('importBtn').disabled = false;
}
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
  e.preventDefault(); zone.classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file) { document.getElementById('csvFile').files = e.dataTransfer.files; handleFileSelect(document.getElementById('csvFile')); }
});
</script>
</body>
</html>