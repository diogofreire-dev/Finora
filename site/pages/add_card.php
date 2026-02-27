<?php
// site/add_card.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$errors = [];
$success = false;

// Cores disponíveis para os cartões
$cardColors = [
    'purple' => ['name' => 'Roxo', 'gradient' => 'linear-gradient(135deg, #667eea 0%, #667eea 100%)'],
    'blue' => ['name' => 'Azul', 'gradient' => 'linear-gradient(135deg, #2196F3 0%, #2196F3 100%)'],
    'green' => ['name' => 'Verde', 'gradient' => 'linear-gradient(135deg, #13d168ff 0%, #13d168ff 100%)'],
    'orange' => ['name' => 'Laranja', 'gradient' => 'linear-gradient(135deg, #FF9800 0%, #FF9800 100%)'],
    'red' => ['name' => 'Vermelho', 'gradient' => 'linear-gradient(135deg, #f44336 0%, #f44336 100%)'],
    'pink' => ['name' => 'Rosa', 'gradient' => 'linear-gradient(135deg, #E91E63 0%, #E91E63 100%)'],
    'teal' => ['name' => 'Turquesa', 'gradient' => 'linear-gradient(135deg, #00BCD4 0%, #00BCD4 100%)'],
    'indigo' => ['name' => 'Índigo', 'gradient' => 'linear-gradient(135deg, #3F51B5 0%, #3F51B5 100%)']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $limit = floatval($_POST['limit_amount'] ?? 0);
    $balance = floatval($_POST['balance'] ?? 0);
    $color = $_POST['color'] ?? 'purple';

    // Validações
    if (strlen($name) < 3) {
        $errors[] = 'O nome do cartão deve ter pelo menos 3 caracteres.';
    }
    if ($limit < 0) {
        $errors[] = 'O limite não pode ser negativo.';
    }
    if ($balance < 0) {
        $errors[] = 'O saldo não pode ser negativo.';
    }
    if ($balance > $limit) {
        $errors[] = 'O saldo não pode ser superior ao limite.';
    }
    if (!array_key_exists($color, $cardColors)) {
        $color = 'purple';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO cards (user_id, name, limit_amount, balance, active, color)
                VALUES (:uid, :name, :limit, :balance, 1, :color)
            ");
            $stmt->execute([
                ':uid' => $uid,
                ':name' => $name,
                ':limit' => $limit,
                ':balance' => $balance,
                ':color' => $color
            ]);
            $success = true;

            // Limpar campos após sucesso
            header('Location: cards.php?success=card_added');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Erro ao adicionar cartão. Tenta novamente.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Adicionar Cartão - Freecard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
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
      background: linear-gradient(135deg, #2b3035 0%, #323844 50%, #2b3035 100%);
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
      --primary-blue: #3498db;
      --dark-blue: #2980b9;
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
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
    }
    .btn-outline-primary {
      color: var(--primary-blue);
      border-color: var(--primary-blue);
    }
    .btn-outline-primary:hover {
      background: var(--primary-blue);
      border-color: var(--primary-blue);
    }
    .card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 4px 20px var(--shadow);
      background: var(--bg-secondary);
      color: var(--text-primary);
    }
    .card-header {
      background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
      border-radius: 16px 16px 0 0 !important;
      padding: 24px;
    }
    .form-label {
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 8px;
    }
    .form-control, .form-select {
      border: 2px solid var(--border-color);
      border-radius: 10px;
      padding: 12px 16px;
      transition: all 0.3s;
      background: var(--bg-primary);
      color: var(--text-primary);
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--primary-green);
      box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.1);
      background: var(--bg-primary);
      color: var(--text-primary);
    }
    .card-preview {
      border-radius: 16px;
      padding: 24px;
      color: white;
      min-height: 180px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
      transition: all 0.3s;
    }
    .card-preview .card-number {
      font-size: 24px;
      letter-spacing: 4px;
      font-weight: 600;
    }
    .card-preview .card-name {
      font-size: 16px;
      text-transform: uppercase;
      font-weight: 600;
    }
    .progress-custom {
      height: 8px;
      border-radius: 10px;
      background: var(--bg-hover);
    }
    .progress-bar-custom {
      background: var(--primary-blue);
      border-radius: 10px;
    }
    
    .color-selector {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
      margin-top: 12px;
    }
    .color-option {
      aspect-ratio: 1;
      border-radius: 12px;
      border: 3px solid transparent;
      cursor: pointer;
      transition: all 0.3s;
      position: relative;
      overflow: hidden;
    }
    .color-option:hover {
      transform: scale(1.05);
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    .color-option input[type="radio"] {
      display: none;
    }
    .color-option input[type="radio"]:checked + .color-display {
      border-color: var(--primary-green);
      box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.3);
    }
    .color-display {
      width: 100%;
      height: 100%;
      border-radius: 8px;
      border: 3px solid transparent;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .color-option input[type="radio"]:checked + .color-display::after {
      color: white;
      font-size: 24px;
      font-weight: bold;
      text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }
    
    [data-theme="dark"] .text-muted {
      color: var(--text-secondary) !important;
    }
    [data-theme="dark"] .form-control::placeholder {
      color: var(--text-secondary);
      opacity: 0.7;
    }

    /* Remove number input spinners */
    input[type="number"]::-webkit-outer-spin-button,
    input[type="number"]::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }
    input[type="number"] {
      -moz-appearance: textfield;
    }
  </style>
</head>
<body>
  <!-- Background animado -->
  <div class="bg-animation">
    <div class="floating-shape shape1"></div>
    <div class="floating-shape shape2"></div>
    <div class="floating-shape shape3"></div>
    <div class="floating-shape shape4"></div>
    <div class="floating-shape shape5"></div>
    <div class="floating-shape shape6"></div>
    <div class="floating-shape shape7"></div>
    <div class="floating-shape shape8"></div>  
    <div class="floating-shape shape9"></div>
  </div>
<nav class="navbar navbar-expand-lg navbar-light">
  <div class="container">
    <a class="navbar-brand fw-bold" href="../dashboard.php">
      <img src="../assets/logo2.png" alt="Freecard">
      FreeCard
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link active" href="cards.php"><i class="bi bi-wallet2"></i> Cartões</a></li>
        <li class="nav-item"><a class="nav-link" href="transactions.php"><i class="bi bi-receipt"></i> Transações</a></li>
        <li class="nav-item"><a class="nav-link" href="budgets.php"><i class="bi bi-piggy-bank"></i> Orçamentos</a></li>
        <li class="nav-item"><a class="nav-link" href="reminders.php"><i class="bi bi-calendar-check"></i> Lembretes</a></li>
        <li class="nav-item"><a class="nav-link" href="analytics.php"><i class="bi bi-graph-up"></i> Análise</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <?=htmlspecialchars($_SESSION['username'])?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear"></i> Configurações</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
          </ul>
        </li>      
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-5 mb-5">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10">
      <div class="mb-4">
        <a href="cards.php" class="text-decoration-none text-muted">
          <i class="bi bi-arrow-left"></i> Voltar aos Cartões
        </a>
      </div>

      <div class="row g-4">
        <div class="col-lg-5">
          <div class="card">
            <div class="card-body p-4">
              <h5 class="mb-4"><i class="bi bi-eye"></i> Pré-visualização</h5>
              <div class="card-preview" id="cardPreview" style="background: <?=$cardColors['purple']['gradient']?>;">
                <div>
                  <div class="mb-3">
                    <i class="bi bi-credit-card" style="font-size: 32px;"></i>
                  </div>
                  <div class="card-number" id="preview-number">•••• •••• •••• ••••</div>
                </div>
                <div>
                  <div class="card-name" id="preview-name">NOME DO CARTÃO</div>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <small>Limite: <span id="preview-limit">€0.00</span></small>
                    <small>Saldo: <span id="preview-balance">€0.00</span></small>
                  </div>
                </div>
              </div>
              
              <div class="mt-4">
                <h6 class="mb-3">Utilização do Limite</h6>
                <div class="progress-custom">
                  <div class="progress-bar-custom" id="usage-bar" style="width: 0%"></div>
                </div>
                <div class="d-flex justify-content-between mt-2">
                  <small class="text-muted">0%</small>
                  <small class="text-muted" id="usage-percent">0% usado</small>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="card">
            <div class="card-header text-white">
              <h4 class="mb-0"><i class="bi bi-credit-card-2-front"></i> Adicionar Novo Cartão</h4>
            </div>
            <div class="card-body p-4">
              <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                  <i class="bi bi-check-circle"></i> Cartão adicionado com sucesso!
                  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
              <?php endif; ?>

              <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                  <strong><i class="bi bi-exclamation-circle"></i> Erros:</strong>
                  <ul class="mb-0 mt-2">
                    <?php foreach($errors as $e): ?>
                      <li><?=htmlspecialchars($e)?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <form method="post" id="cardForm">
                <div class="mb-3">
                  <label class="form-label">Nome do Cartão *</label>
                  <input 
                    type="text" 
                    name="name" 
                    id="cardName"
                    class="form-control" 
                    placeholder="ex: Visa Principal, Mastercard Viagens"
                    value="<?=htmlspecialchars($name ?? '')?>" 
                    required
                  >
                  <small class="text-muted">Dá um nome descritivo ao teu cartão</small>
                </div>

                <div class="mb-3">
                  <label class="form-label">Cor do Cartão *</label>
                  <div class="color-selector">
                    <?php foreach($cardColors as $colorKey => $colorData): ?>
                      <label class="color-option">
                        <input 
                          type="radio" 
                          name="color" 
                          value="<?=$colorKey?>" 
                          <?=($color ?? 'purple') === $colorKey ? 'checked' : ''?>
                          data-gradient="<?=$colorData['gradient']?>"
                        >
                        <div class="color-display" style="background: <?=$colorData['gradient']?>;"></div>
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <small class="text-muted mt-2 d-block">Escolhe uma cor para identificar facilmente o teu cartão</small>
                </div>

                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Limite do Cartão (€) *</label>
                    <input 
                      type="number" 
                      name="limit_amount" 
                      id="cardLimit"
                      class="form-control" 
                      placeholder="1500.00"
                      step="0.01"
                      min="0"
                      value="<?=htmlspecialchars($limit ?? 0)?>" 
                      required
                    >
                    <small class="text-muted">O limite máximo de gastos</small>
                  </div>

                  <div class="col-md-6 mb-3">
                    <label class="form-label">Gasto Inicial (€)</label>
                    <input 
                      type="number" 
                      name="balance" 
                      id="cardBalance"
                      class="form-control" 
                      placeholder="0.00"
                      step="0.01"
                      min="0"
                      value="<?=htmlspecialchars($balance ?? 0)?>"
                    >
                    <small class="text-muted">Se já tens gastos acumulados</small>
                  </div>
                </div>

                <div class="d-grid gap-2 mt-4">
                  <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-plus-circle"></i> Adicionar Cartão
                  </button>
                  <a href="cards.php" class="btn btn-outline-secondary">
                    Cancelar
                  </a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Live preview
document.getElementById('cardName').addEventListener('input', function(e) {
  const name = e.target.value || 'NOME DO CARTÃO';
  document.getElementById('preview-name').textContent = name.toUpperCase();
});

document.getElementById('cardLimit').addEventListener('input', updateUsage);
document.getElementById('cardBalance').addEventListener('input', updateUsage);

// Mudança de cor
document.querySelectorAll('input[name="color"]').forEach(radio => {
  radio.addEventListener('change', function() {
    const gradient = this.dataset.gradient;
    document.getElementById('cardPreview').style.background = gradient;
  });
});

function updateUsage() {
  const limit = parseFloat(document.getElementById('cardLimit').value) || 0;
  const balance = parseFloat(document.getElementById('cardBalance').value) || 0;
  
  document.getElementById('preview-limit').textContent = `€${limit.toFixed(2)}`;
  document.getElementById('preview-balance').textContent = `€${balance.toFixed(2)}`;
  
  if (limit > 0) {
    const percent = Math.min((balance / limit) * 100, 100);
    document.getElementById('usage-bar').style.width = percent + '%';
    document.getElementById('usage-percent').textContent = Math.round(percent) + '% usado';
    
    // Mudar cor baseado no uso
    const bar = document.getElementById('usage-bar');
    if (percent >= 80) {
      bar.style.background = '#e74c3c';
    } else if (percent >= 60) {
      bar.style.background = '#f39c12';
    } else {
      bar.style.background = '#2ecc71';
    }
  }
}
<!-- Gerar partículas (apenas desktop) -->

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

</script>
</body>
</html>