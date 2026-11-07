<?php
// site/pages/dados.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/theme_helper.php';
require_once __DIR__ . '/../helpers/categories.php';
require_once __DIR__ . '/../helpers/import_rows.php';
$uid = $_SESSION['user_id'] ?? null;
$currentTheme = getUserTheme($pdo, $uid);

$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid AND active = 1 ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT (SELECT COUNT(*) FROM transactions WHERE user_id = :u1) AS tx,
                              (SELECT COUNT(*) FROM cards        WHERE user_id = :u2) AS cards,
                              (SELECT COUNT(*) FROM budgets      WHERE user_id = :u3) AS budgets");
$stmt->execute([':u1' => $uid, ':u2' => $uid, ':u3' => $uid]);
$counts = $stmt->fetch();

// Meses que têm transações (para o filtro)
$stmt = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(transaction_date, '%Y-%m') AS ym FROM transactions
                       WHERE user_id = :uid ORDER BY ym DESC LIMIT 36");
$stmt->execute([':uid' => $uid]);
$months = $stmt->fetchAll(PDO::FETCH_COLUMN);
$monthNames = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$monthLabel = fn($ym) => ucfirst($monthNames[(int) substr($ym, 5, 2) - 1]) . ' ' . substr($ym, 0, 4);

$categories = array_values(array_filter(array_keys(CATEGORY_STYLES), fn($c) => $c !== 'Sem Categoria'));

// A mensagem da importação vem da sessão, nunca do URL: assim ninguém consegue fazer
// um link para a Finora que mostre um texto inventado por ele.
[$messageType, $message] = $_SESSION['flash_dados'] ?? ['', ''];
unset($_SESSION['flash_dados']);

$plural = fn($n, $one, $many) => $n == 1 ? "1 $one" : "$n $many";
?>
<!doctype html>
<html lang="<?= html_lang() ?>" data-theme="<?= $currentTheme ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dados - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="../assets/apple-touch-icon.png">
  <link rel="stylesheet" href="../css/sidebar.css?v=7">
  <style>

    .dt-flash { display: flex; gap: 10px; align-items: flex-start; padding: 12px 16px; border-radius: 12px; margin-bottom: 22px; font-size: 14px; border: 1px solid transparent; }
    .dt-flash.success { background: #ecfdf3; border-color: #abefc6; color: #067647; }
    .dt-flash.danger  { background: #fef3f2; border-color: #fecdca; color: #b42318; }
    .dt-flash.info    { background: #fffaeb; border-color: #fedf89; color: #b54708; }
    [data-theme="dark"] .dt-flash.success { background: rgba(117,224,167,.1); border-color: rgba(117,224,167,.25); color: #75e0a7; }
    [data-theme="dark"] .dt-flash.danger  { background: rgba(253,162,155,.1); border-color: rgba(253,162,155,.25); color: #fda29b; }
    [data-theme="dark"] .dt-flash.info    { background: rgba(254,200,75,.1); border-color: rgba(254,200,75,.25); color: #fedf89; }


    .pg-card > .dt-body { border-top: 1px solid var(--fn-line); }
    .dt-body:not(.dt-pad) > * + * { border-top: 1px solid var(--fn-line); }
    .dt-pad { padding: 18px 20px; }

    .dt-filters { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    .dt-body .form-label { font-size: 14px; font-weight: 600; margin-bottom: 6px; color: var(--fn-text); }
    .dt-body .form-select { min-height: 44px; border-radius: 10px; border: 1px solid var(--fn-line); background-color: var(--fn-surface); color: var(--fn-text); }

    .dt-row { display: flex; align-items: center; gap: 14px; padding: 16px 20px; }
    .dt-row .txt { flex: 1; min-width: 0; }
    .dt-row b { display: block; font-size: 15px; color: var(--fn-text); }
    .dt-row span { font-size: 14px; color: var(--fn-muted); }
    .dt-row .btn-s { flex-shrink: 0; }
    .dt-row form { margin: 0; }

    /* Zona de largar o CSV: é um <label> do input, por isso funciona com teclado e leitor de ecrã */
    .dt-drop { position: relative; display: block; border: 2px dashed var(--fn-line); border-radius: 12px; padding: 26px 16px; text-align: center; cursor: pointer; margin: 0; transition: border-color .15s, background .15s; }
    .dt-drop:hover, .dt-drop.over { border-color: var(--fn-accent); background: rgba(var(--fn-accent-rgb), .05); }
    .dt-drop:focus-within { outline: 2px solid var(--fn-gold); outline-offset: 2px; }
    .dt-drop i { font-size: 30px; color: var(--cat-1); }
    .dt-drop b { display: block; margin-top: 6px; font-size: 15px; color: var(--fn-text); }
    .dt-drop span { font-size: 13px; color: var(--fn-muted); }
    .dt-drop .file { color: var(--fn-accent); font-weight: 600; }
    .dt-drop input { position: absolute; width: 1px; height: 1px; opacity: 0; }

    .dt-scroll { overflow-x: auto; margin: 10px 0 6px; }
    .dt-sample { width: 100%; min-width: 440px; border-collapse: collapse; font-size: 13px; font-variant-numeric: tabular-nums; }
    .dt-sample th, .dt-sample td { border: 1px solid var(--fn-line); padding: 6px 10px; text-align: left; color: var(--fn-text); }
    .dt-sample th { background: var(--fn-paper); font-weight: 600; color: var(--fn-muted); }
    .dt-hint { font-size: 13px; color: var(--fn-muted); margin: 0; }
    .dt-hint code { color: var(--fn-text); background: var(--fn-paper); padding: 1px 5px; border-radius: 5px; font-size: 12px; }
    .dt-acts { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-top: 14px; }
    .dt-check { display: inline-flex; align-items: center; gap: 8px; font-size: 14px; color: var(--fn-text); margin: 0; cursor: pointer; }
    .dt-check input { width: 18px; height: 18px; accent-color: var(--fn-accent); margin: 0; }
    .dt-toggle { background: none; border: 0; padding: 0; font-size: 14px; font-weight: 600; color: var(--fn-accent); cursor: pointer; }
    [data-theme="dark"] .dt-toggle { color: #9db4ee; }

    @media (max-width: 760px) {
      .dt-filters { grid-template-columns: 1fr; }
    }
    @media (max-width: 520px) {
      .dt-row { flex-wrap: wrap; }
      .dt-row .txt { flex-basis: calc(100% - 50px); }
      .dt-row .btn-s, .dt-row form { margin-left: 50px; }
    }
  </style>
  <link rel="stylesheet" href="../css/finora.css?v=9">
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
<div class="pg-wrap">

  <div class="pg-head"><h1>Dados</h1></div>

  <?php if ($message): ?>
    <?php $icon = ['success' => 'check-circle', 'danger' => 'exclamation-circle'][$messageType] ?? 'info-circle'; ?>
    <div class="dt-flash <?= htmlspecialchars($messageType) ?>" role="<?= $messageType === 'danger' ? 'alert' : 'status' ?>">
      <i class="bi bi-<?= $icon ?>"></i><span><?= htmlspecialchars($message) ?></span>
    </div>
  <?php endif; ?>

  <!-- EXPORTAR -->
  <section class="pg-card" aria-labelledby="sec-exp" style="--tone:var(--cat-2)">
    <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-box-arrow-up"></i></span><div><h2 id="sec-exp">Exportar</h2><p>Escolhe o período e o formato. Os filtros servem para os PDF e para o CSV.</p></div></header>
    <form class="dt-body" method="get" action="../helpers/export_csv.php" id="exportForm">
      <div class="dt-pad dt-filters">
        <div>
          <label class="form-label" for="month">Mês</label>
          <select class="form-select" id="month" name="month">
            <option value="">Todos os meses</option>
            <?php foreach ($months as $i => $ym): ?>
              <option value="<?= htmlspecialchars($ym) ?>" <?= $i === 0 ? 'selected' : '' ?>><?= $monthLabel($ym) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label" for="category">Categoria</label>
          <select class="form-select" id="category" name="category">
            <option value="">Todas</option>
            <?php foreach ($categories as $cat): ?>
              <option><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label" for="card_id">Cartão</label>
          <select class="form-select" id="card_id" name="card_id">
            <option value="">Todos</option>
            <?php foreach ($cards as $c): ?>
              <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <input type="hidden" name="include_cards" value="1">
      <input type="hidden" name="include_stats" value="1">

      <div class="dt-row">
        <span class="pg-ico sm" style="--tone:var(--cat-8)" aria-hidden="true"><i class="bi bi-file-earmark-pdf"></i></span>
        <div class="txt"><b>Relatório completo (PDF)</b><span>Todas as transações, totais por categoria e resumo dos cartões.</span></div>
        <button type="submit" class="btn-s" formaction="../helpers/generate_report_pdf.php" name="type" value="full"><i class="bi bi-download"></i> Exportar</button>
      </div>
      <div class="dt-row">
        <span class="pg-ico sm" style="--tone:var(--cat-4)" aria-hidden="true"><i class="bi bi-file-earmark-text"></i></span>
        <div class="txt"><b>Resumo (PDF)</b><span>As últimas 20 transações e os totais. Bom para uma vista rápida.</span></div>
        <button type="submit" class="btn-s" formaction="../helpers/generate_report_pdf.php" name="type" value="summary"><i class="bi bi-download"></i> Exportar</button>
      </div>
      <div class="dt-row">
        <span class="pg-ico sm" style="--tone:var(--cat-6)" aria-hidden="true"><i class="bi bi-file-earmark-spreadsheet"></i></span>
        <div class="txt"><b>Folha de cálculo (CSV)</b><span>Abre no Excel ou no Google Sheets. No Excel usa Dados → De Texto/CSV.</span></div>
        <button type="submit" class="btn-s"><i class="bi bi-download"></i> Exportar</button>
      </div>
    </form>
  </section>

  <!-- BACKUP -->
  <section class="pg-card" aria-labelledby="sec-bak" style="--tone:var(--cat-3)">
    <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-shield-check"></i></span><div><h2 id="sec-bak">Cópia de segurança</h2><p>Um ficheiro com tudo, para guardares fora da Finora. Faz uma por mês.</p></div></header>
    <div class="dt-body">
      <div class="dt-row">
        <span class="pg-ico sm" style="--tone:var(--cat-3)" aria-hidden="true"><i class="bi bi-cloud-download"></i></span>
        <div class="txt">
          <b>Descarregar cópia (JSON)</b>
          <span>Inclui <?= $plural($counts['tx'], 'transação', 'transações') ?>, <?= $plural($counts['cards'], 'cartão', 'cartões') ?> e <?= $plural($counts['budgets'], 'orçamento', 'orçamentos') ?>.</span>
        </div>
        <a class="btn-s" href="../helpers/export_json.php"><i class="bi bi-download"></i> Descarregar</a>
      </div>
      <form class="dt-row" action="../helpers/import_json.php" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <span class="pg-ico sm" style="--tone:var(--cat-7)" aria-hidden="true"><i class="bi bi-arrow-counterclockwise"></i></span>
        <div class="txt">
          <b>Restaurar uma cópia</b>
          <span id="jsonName">Repõe as transações. As que já existem não são duplicadas.</span>
        </div>
        <label class="btn-s" style="margin:0">
          <i class="bi bi-folder2-open"></i> Escolher ficheiro
          <input type="file" name="json_file" accept=".json,application/json" hidden id="jsonFile">
        </label>
      </form>
    </div>
  </section>

  <!-- IMPORTAR -->
  <section class="pg-card" aria-labelledby="sec-imp" style="--tone:var(--cat-1)">
    <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-box-arrow-in-down"></i></span><div><h2 id="sec-imp">Importar transações</h2><p>Traz movimentos do extrato do banco ou de outra app, num ficheiro CSV.</p></div></header>
    <form class="dt-body dt-pad" action="../helpers/import_csv.php" method="post" enctype="multipart/form-data" id="csvForm">
      <?= csrf_field() ?>
      <label class="dt-drop" id="dropZone">
        <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
        <b id="csvName">Escolhe ou arrasta o ficheiro CSV</b>
        <span>Até 2 MB e <?= IMPORT_MAX_ROWS ?> linhas</span>
        <input type="file" name="csv_file" id="csvFile" accept=".csv,.txt,text/csv" required>
      </label>

      <div class="dt-acts">
        <button type="submit" class="btn-s btn-s-primary" id="importBtn" disabled><i class="bi bi-upload"></i> Importar</button>
        <label class="dt-check"><input type="checkbox" name="skip_duplicates" checked> Ignorar transações repetidas</label>
      </div>

      <div style="margin-top:18px">
        <button type="button" class="dt-toggle" data-bs-toggle="collapse" data-bs-target="#csvFormat" aria-expanded="false" aria-controls="csvFormat">
          <i class="bi bi-table"></i> Como deve ser o ficheiro
        </button>
        <div class="collapse" id="csvFormat">
          <div class="dt-scroll"><table class="dt-sample">
            <thead><tr><th>data</th><th>descricao</th><th>categoria</th><th>valor</th><th>cartao</th></tr></thead>
            <tbody>
              <tr><td>2026-01-15</td><td>Continente</td><td>Alimentação</td><td>45,90</td><td>Visa</td></tr>
              <tr><td>18/01/2026</td><td>Netflix</td><td>Entretenimento</td><td>12.99</td><td></td></tr>
            </tbody>
          </table></div>
          <p class="dt-hint">Obrigatórias: <code>data</code>, <code>descricao</code> e <code>valor</code>. A categoria tem de ser uma das da Finora (senão fica "Outros") e o cartão tem de ter o mesmo nome que na Finora.
            <button type="button" class="dt-toggle" id="templateBtn">Descarregar modelo</button></p>
        </div>
      </div>
    </form>
  </section>

</div>
</div>
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// CSV: mostra o nome escolhido e ativa o botão
const csvFile = document.getElementById('csvFile');
const showCsv = () => {
  const f = csvFile.files[0];
  document.getElementById('csvName').innerHTML = f ? '<span class="file"></span>' : 'Escolhe ou arrasta o ficheiro CSV';
  if (f) document.querySelector('#csvName .file').textContent = f.name;
  document.getElementById('importBtn').disabled = !f;
};
csvFile.addEventListener('change', showCsv);
const zone = document.getElementById('dropZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('over'));
zone.addEventListener('drop', e => {
  e.preventDefault(); zone.classList.remove('over');
  if (e.dataTransfer.files[0]) { csvFile.files = e.dataTransfer.files; showCsv(); }
});

// Backup: restaura assim que se escolhe o ficheiro, depois de confirmar
document.getElementById('jsonFile').addEventListener('change', function () {
  const f = this.files[0];
  if (!f) return;
  if (confirm('Restaurar as transações de "' + f.name + '"?')) this.form.submit();
  else this.value = '';
});

// Modelo do CSV
document.getElementById('templateBtn').addEventListener('click', () => {
  const csv = 'data;descricao;categoria;valor;cartao\n2026-01-15;Continente;Alimentação;45,90;Visa\n18/01/2026;Netflix;Entretenimento;12.99;\n';
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
  a.download = 'modelo_finora.csv';
  a.click();
});
</script>
</body>
</html>
