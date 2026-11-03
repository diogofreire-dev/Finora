<?php
// site/pages/export_pdf.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

// Buscar cartões
$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid AND active = 1 ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

// Buscar categorias
$categories = ['Compras', 'Alimentação', 'Transporte', 'Saúde', 'Entretenimento', 'Educação', 'Casa', 'Outros'];
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Exportar Relatório - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css?v=6">
<style>
  /* ========== BACKGROUND ANIMADO ========== */
  body {
    position: relative;
    min-height: 100vh;
  }
  .bg-animation {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
    pointer-events: none;
    overflow: hidden;
  }
  [data-theme="light"] body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #e8eef5ff 0%, #e9eef8ff 50%, #e8edf5ff 100%);
    z-index: -1;
  }
  [data-theme="dark"] body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #1a1d29 0%, #252936 50%, #1a1d29 100%);
    z-index: -1;
  }
  .floating-shape {
    position: absolute;
    border-radius: 50%;
    animation: float 20s infinite ease-in-out;
  }
  [data-theme="light"] .floating-shape {
    background: rgba(46, 88, 204);
  }
  [data-theme="dark"] .floating-shape {
    background: rgba(46, 88, 204);
  }
  .shape1 { width: 300px; height: 300px; top: -100px; left: -100px; animation-delay: 0s; }
  .shape2 { width: 200px; height: 200px; bottom: -50px; right: -50px; animation-delay: 5s; }
  .shape3 { width: 150px; height: 150px; top: 50%; right: 10%; animation-delay: 2s; }
  .shape4 { width: 100px; height: 100px; bottom: 20%; left: 15%; animation-delay: 7s; }
  .shape5 { width: 250px; height: 250px; top: 30%; left: 50%; animation-delay: 3s; }
  .shape6 { width: 250px; height: 300px; top: -100px; right: -100px; animation-delay: 6s; }
  .shape7 { width: 180px; height: 180px; top: 10%; left: 70%; animation-delay: 1s; }
  .shape8 { width: 120px; height: 120px; bottom: 10%; right: 30%; animation-delay: 4s; }
  .shape9 { width: 220px; height: 220px; top: 60%; left: -80px; animation-delay: 8s; }
  @keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.3; }
    50% { transform: translateY(-30px) rotate(180deg); opacity: 0.6; }
  }
  .particle {
    position: absolute;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    animation: rise 15s infinite ease-in;
  }
  [data-theme="light"] .particle { background: rgba(46, 88, 204); }
  [data-theme="dark"] .particle { background: rgba(46, 88, 204); }
  @keyframes rise {
    0% { transform: translateY(0) translateX(0); opacity: 0; }
    10% { opacity: 1; }
    90% { opacity: 1; }
    100% { transform: translateY(-100vh) translateX(50px); opacity: 0; }
  }
  .navbar, .container { position: relative; z-index: 1; }
  @media (max-width: 768px) {
    .floating-shape { opacity: 0.4; animation-duration: 25s; }
    .particle { display: none; }
  }
  @media (prefers-reduced-motion: reduce) {
    .floating-shape, .particle { animation: none; opacity: 0.2; }
  }

  :root {
    --primary-blue: var(--fn-accent);
    --dark-blue: var(--fn-accent-hover);
  }
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background-color: var(--bg-primary);
    color: var(--text-primary);
  }
  .navbar {
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    background: var(--navbar-bg);
  }
  .navbar-brand img { height: 35px; margin-right: 8px; }
  .btn-primary {
    background: var(--primary-blue);
    border-color: var(--primary-blue);
  }
  .btn-primary:hover {
    background: var(--dark-blue);
    border-color: var(--dark-blue);
  }
  .btn-outline-primary {
    color: var(--primary-blue);
    border-color: var(--primary-blue);
  }
  .btn-outline-primary:hover {
    background: var(--primary-blue);
    border-color: var(--primary-blue);
  }
  .text-primary { color: var(--primary-blue) !important; }

  .card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 20px var(--shadow);
    transition: all 0.3s;
    background: var(--bg-secondary);
    color: var(--text-primary);
  }
  .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px var(--shadow);
  }

  .filter-card {
    background: var(--bg-secondary);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px var(--shadow);
    margin-bottom: 24px;
  }

  .export-option {
    text-align: center;
    padding: 40px 20px;
  }
  .export-icon {
    font-size: 64px;
    margin-bottom: 20px;
  }

  /* Tema escuro */
  [data-theme="dark"] .text-muted {
    color: var(--text-secondary) !important;
  }
  [data-theme="dark"] .form-control,
  [data-theme="dark"] .form-select {
    background: var(--bg-primary);
    color: var(--text-primary);
    border-color: var(--border-color);
  }
  [data-theme="dark"] .form-control:focus,
  [data-theme="dark"] .form-select:focus {
    background: var(--bg-primary);
    color: var(--text-primary);
    border-color: var(--primary-blue);
  }
  [data-theme="dark"] .form-check-label {
    color: var(--text-primary);
  }
  [data-theme="dark"] .alert-info {
    background: rgba(var(--fn-accent-rgb), 0.2);
    border-color: rgba(var(--fn-accent-rgb), 0.3);
    color: var(--text-primary);
  }
  [data-theme="dark"] .form-label {
    color: var(--text-primary);
  }
</style>
  <link rel="stylesheet" href="../css/finora.css?v=5">
</head>
<body>
<div class="app-layout">
<?php
$activePage    = 'settings';
$sidebarPrefix = '../';
include __DIR__ . '/../components/sidebar.php';
?>
<main class="main-content">
<div class="page-content">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2><i class="bi bi-file-earmark-pdf text-danger"></i> Exportar Relatório</h2>
      <p class="text-muted mb-0">Gera relatórios PDF das tuas transações</p>
    </div>
  </div>

  <!-- Filtros -->
  <div class="filter-card">
    <h5 class="mb-3"><i class="bi bi-funnel"></i> Filtros</h5>
    <form id="exportForm" method="get" action="../helpers/generate_report_pdf.php">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label small fw-semibold">
            <i class="bi bi-calendar"></i> Mês
          </label>
          <input type="month" name="month" class="form-control" value="<?=date('Y-m')?>">
          <small class="text-muted">Deixa em branco para todos os meses</small>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">
            <i class="bi bi-tag"></i> Categoria
          </label>
          <select name="category" class="form-select">
            <option value="">Todas as categorias</option>
            <?php foreach($categories as $cat): ?>
              <option value="<?=htmlspecialchars($cat)?>"><?=htmlspecialchars($cat)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-semibold">
            <i class="bi bi-credit-card"></i> Cartão
          </label>
          <select name="card_id" class="form-select">
            <option value="">Todos os cartões</option>
            <?php foreach($cards as $card): ?>
              <option value="<?=$card['id']?>"><?=htmlspecialchars($card['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="row mt-3">
        <div class="col-md-6">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="include_cards" value="1" id="includeCards" checked>
            <label class="form-check-label" for="includeCards">
              Incluir resumo de cartões
            </label>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="include_stats" value="1" id="includeStats" checked>
            <label class="form-check-label" for="includeStats">
              Incluir estatísticas por categoria
            </label>
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- Opções de Exportação -->
  <div class="row g-4">
    <div class="col-md-6">
      <div class="card">
        <div class="card-body export-option">
          <i class="bi bi-file-earmark-text export-icon text-primary"></i>
          <h4 class="fw-bold mb-3">Relatório Completo</h4>
          <p class="text-muted mb-4">Todas as transações com análise detalhada</p>
          <!-- Botão de exportação completa -->
          <button type="button" class="btn btn-primary px-4" onclick="exportReport('full')">
            <i class="bi bi-download"></i> Exportar Completo
          </button>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-body export-option">
          <i class="bi bi-file-earmark-bar-graph export-icon text-success"></i>
          <h4 class="fw-bold mb-3">Relatório Resumido</h4>
          <p class="text-muted mb-4">Últimas 20 transações e resumo</p>
          <!-- Botão de exportação resumida -->
          <button type="button" class="btn btn-outline-primary px-4" onclick="exportReport('summary')">
            <i class="bi bi-download"></i> Exportar Resumido
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Info -->
  <div class="alert alert-info mt-4">
    <i class="bi bi-info-circle"></i>
    <strong>Nota:</strong> O relatório será gerado em formato PDF e descarregado automaticamente.
  </div>
</div>
</div><!-- /page-content -->
</main><!-- /main-content -->
</div><!-- /app-layout -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Partículas animadas
  (function() {
    if (window.innerWidth <= 768) return;
    const container = document.querySelector('.bg-animation');
    if (!container) return;
    for (let i = 0; i < 15; i++) {
      const particle = document.createElement('div');
      particle.className = 'particle';
      particle.style.left = Math.random() * 100 + '%';
      particle.style.animationDelay = Math.random() * 15 + 's';
      particle.style.animationDuration = (15 + Math.random() * 10) + 's';
      container.appendChild(particle);
    }
  })();

  function exportReport(type) {
    const form = document.getElementById('exportForm');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    params.append('type', type);
    window.location.href = '../helpers/generate_report_pdf.php?' + params.toString();
  }
</script>
</body>
</html>
