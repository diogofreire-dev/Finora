<?php
// site/edit_transaction.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$errors = [];
$success = false;

// Obter ID da transação
$transaction_id = !empty($_GET['id']) ? intval($_GET['id']) : null;

if (!$transaction_id) {
    header('Location: transactions.php');
    exit;
}

// Buscar a transação
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = :id AND user_id = :uid");
$stmt->execute([':id' => $transaction_id, ':uid' => $uid]);
$transaction = $stmt->fetch();

if (!$transaction) {
    header('Location: transactions.php');
    exit;
}

// Buscar cartões do utilizador
$stmt = $pdo->prepare("SELECT id, name, limit_amount, balance FROM cards WHERE user_id = :uid AND active = 1 ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

// Categorias comuns
$categories = [
    ['icon' => 'cart', 'name' => 'Compras'],
    ['icon' => 'cup-straw', 'name' => 'Alimentação'],
    ['icon' => 'bus-front', 'name' => 'Transporte'],
    ['icon' => 'heart-pulse', 'name' => 'Saúde'],
    ['icon' => 'controller', 'name' => 'Entretenimento'],
    ['icon' => 'book', 'name' => 'Educação'],
    ['icon' => 'house-door', 'name' => 'Casa'],
    ['icon' => 'three-dots', 'name' => 'Outros']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $card_id = !empty($_POST['card_id']) ? intval($_POST['card_id']) : null;
    $transaction_date = trim($_POST['transaction_date'] ?? '');
    
    $old_amount = $transaction['amount'];
    $old_card_id = $transaction['card_id'];

    // Validações
    if ($amount <= 0) {
        $errors[] = 'O valor deve ser maior que zero.';
    }
    if (strlen($description) < 3) {
        $errors[] = 'A descrição deve ter pelo menos 3 caracteres.';
    }
    if (empty($transaction_date)) {
        $errors[] = 'A data da transação é obrigatória.';
    } else {
        // Validar formato da data
        $date = DateTime::createFromFormat('Y-m-d', $transaction_date);
        if (!$date || $date->format('Y-m-d') !== $transaction_date) {
            $errors[] = 'Data inválida.';
        }
        // Não permitir datas futuras
        if ($date > new DateTime()) {
            $errors[] = 'A data da transação não pode ser no futuro.';
        }
    }

    // Verificar se o cartão tem limite disponível (se mudou)
    if ($card_id && ($card_id != $old_card_id || $amount != $old_amount)) {
        $stmt = $pdo->prepare("SELECT limit_amount, balance FROM cards WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $card_id, ':uid' => $uid]);
        $card = $stmt->fetch();
        
        if ($card && $card['limit_amount'] > 0) {
            // Calcular o novo saldo se aplicarmos esta transação
            $currentBalance = $card['balance'];
            
            // Se era o mesmo cartão, remover o valor antigo
            if ($card_id == $old_card_id) {
                $currentBalance -= $old_amount;
            }
            
            // Adicionar o novo valor
            $newBalance = $currentBalance + $amount;
            
            if ($newBalance > $card['limit_amount']) {
                $errors[] = 'Esta transação excede o limite disponível do cartão.';
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Atualizar a transação
            $stmt = $pdo->prepare("
                UPDATE transactions 
                SET amount = :amt, description = :desc, category = :cat, card_id = :cid, transaction_date = :tdate
                WHERE id = :id AND user_id = :uid
            ");
            $stmt->execute([
                ':amt' => $amount,
                ':desc' => $description,
                ':cat' => $category ?: null,
                ':cid' => $card_id,
                ':tdate' => $transaction_date,
                ':id' => $transaction_id,
                ':uid' => $uid
            ]);

            // Ajustar saldos dos cartões
            // 1. Se tinha cartão antigo, devolver o valor
            if ($old_card_id) {
                $stmt = $pdo->prepare("
                    UPDATE cards 
                    SET balance = balance - :amt 
                    WHERE id = :cid AND user_id = :uid
                ");
                $stmt->execute([':amt' => $old_amount, ':cid' => $old_card_id, ':uid' => $uid]);
            }

            // 2. Se tem cartão novo, adicionar o valor
            if ($card_id) {
                $stmt = $pdo->prepare("
                    UPDATE cards 
                    SET balance = balance + :amt 
                    WHERE id = :cid AND user_id = :uid
                ");
                $stmt->execute([':amt' => $amount, ':cid' => $card_id, ':uid' => $uid]);
            }

            $pdo->commit();
            $success = true;
            
            // Atualizar os dados da transação para mostrar os novos valores
            header('Location: transactions.php?success=transaction_updated');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Erro ao atualizar transação. Tenta novamente.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Editar Transação - FreeCard</title>
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
      border-color: var(--primary-blue);
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
      background: var(--bg-primary);
      color: var(--text-primary);
    }
    .amount-input {
      font-size: 32px;
      font-weight: 700;
      text-align: center;
      border: 3px solid var(--border-color);
      background: var(--bg-primary);
      color: var(--text-primary);
    }
    .amount-input:focus {
      border-color: var(--primary-green);
    }
    .amount-input::-webkit-outer-spin-button,
    .amount-input::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }
    .amount-input[type=number] {
      -moz-appearance: textfield;
    }
    .category-option {
      border: 2px solid var(--border-color);
      border-radius: 12px;
      padding: 20px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s;
      background: var(--bg-secondary);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      aspect-ratio: 1;
      min-height: 0;
    }
    .category-option:hover {
      border-color: var(--primary-green);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px var(--shadow);
    }
    .category-option input[type="radio"] {
      display: none;
    }
    .category-option input[type="radio"]:checked + .category-content {
      color: var(--primary-green);
    }
    .category-option input[type="radio"]:checked ~ .category-option,
    .category-option.selected {
      border-color: var(--primary-green);
      background: rgba(46, 204, 113, 0.05);
    }
    .category-content {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      width: 100%;
      gap: 8px;
    }
    .category-icon {
      font-size: 32px;
      margin-bottom: 0;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .category-content small {
      font-size: 13px;
      font-weight: 600;
      text-align: center;
      line-height: 1.2;
      display: block;
    }
    .row.g-2 {
      --bs-gutter-x: 0.5rem;
      --bs-gutter-y: 0.5rem;
    }
    .col-6.col-md-3 {
      display: flex;
    }
    .category-option {
      width: 100%;
    }
    [data-theme="dark"] .category-option {
      border-color: var(--border-color);
      background: var(--bg-secondary);
    }
    [data-theme="dark"] .category-option:hover {
      border-color: var(--primary-green);
      background: var(--bg-hover);
    }
    .info-box {
      background: var(--bg-primary);
      padding: 16px;
      border-radius: 8px;
    }
    [data-theme="dark"] .text-muted {
      color: var(--text-secondary) !important;
    }
    [data-theme="dark"] .form-control::placeholder,
    [data-theme="dark"] .amount-input::placeholder {
      color: var(--text-secondary);
      opacity: 0.7;
    }
    [data-theme="dark"] .bg-light {
      background: var(--bg-hover) !important;
      color: var(--text-primary);
    }
    [data-theme="dark"] .badge {
      background: var(--bg-hover) !important;
      color: var(--text-primary) !important;
    }
    input[type="date"]::-webkit-calendar-picker-indicator {
      filter: invert(0);
    }
    [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator {
      filter: invert(1);
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
        <li class="nav-item"><a class="nav-link" href="cards.php"><i class="bi bi-wallet2"></i> Cartões</a></li>
        <li class="nav-item"><a class="nav-link active" href="transactions.php"><i class="bi bi-receipt"></i> Transações</a></li>
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
        <a href="transactions.php" class="text-decoration-none text-muted">
          <i class="bi bi-arrow-left"></i> Voltar às Transações
        </a>
      </div>

      <div class="row g-4">
        <div class="col-lg-7">
          <div class="card">
            <div class="card-header text-white">
              <h4 class="mb-0"><i class="bi bi-pencil-square"></i> Editar Transação</h4>
            </div>
            <div class="card-body p-4">
              <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                  <i class="bi bi-check-circle"></i> Transação atualizada com sucesso!
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

              <div class="info-box mb-4">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-info-circle text-primary"></i>
                  <div>
                    <strong>Registo criado em:</strong> <?=date('d/m/Y H:i', strtotime($transaction['created_at']))?>
                    <br>
                    <small class="text-muted">Esta informação não será alterada</small>
                  </div>
                </div>
              </div>

              <form method="post">
                <div class="mb-4">
                  <label class="form-label">Data da Transação *</label>
                  <input 
                    type="date" 
                    name="transaction_date" 
                    class="form-control" 
                    value="<?=htmlspecialchars($transaction['transaction_date'])?>"
                    max="<?=date('Y-m-d')?>"
                    required
                  >
                  <small class="text-muted">Quando é que esta despesa ocorreu realmente?</small>
                </div>

                <div class="mb-4">
                  <label class="form-label text-center w-100">Valor da Transação (€) *</label>
                  <input 
                    type="number" 
                    name="amount" 
                    class="form-control amount-input" 
                    placeholder="0.00"
                    step="0.01"
                    min="0.01"
                    value="<?=htmlspecialchars($transaction['amount'])?>" 
                    required
                    autofocus
                  >
                </div>

                <div class="mb-4">
                  <label class="form-label">Descrição *</label>
                  <input 
                    type="text" 
                    name="description" 
                    class="form-control" 
                    placeholder="ex: Café e snack, Supermercado, Gasolina"
                    value="<?=htmlspecialchars($transaction['description'])?>" 
                    required
                  >
                </div>

                <div class="mb-4">
                  <label class="form-label mb-3">Categoria</label>
                  <div class="row g-2">
                    <?php foreach($categories as $cat): ?>
                      <div class="col-6 col-md-3">
                        <label class="category-option <?=($transaction['category'] ?? '') === $cat['name'] ? 'selected' : ''?>">
                          <input type="radio" name="category" value="<?=$cat['name']?>" <?=($transaction['category'] ?? '') === $cat['name'] ? 'checked' : ''?>>
                          <div class="category-content">
                            <div class="category-icon">
                              <i class="bi bi-<?=$cat['icon']?>"></i>
                            </div>
                            <small class="fw-semibold"><?=$cat['name']?></small>
                          </div>
                        </label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>

                <div class="mb-4">
                  <label class="form-label">Cartão Associado</label>
                  <select name="card_id" class="form-select">
                    <option value="" <?=!$transaction['card_id'] ? 'selected' : ''?>>Nenhum / Dinheiro</option>
                    <?php foreach($cards as $c): ?>
                      <option value="<?=$c['id']?>" <?=($transaction['card_id'] ?? '') == $c['id'] ? 'selected' : ''?>>
                        <?=htmlspecialchars($c['name'])?>
                        - Disponível: €<?=number_format($c['limit_amount'] - $c['balance'], 2)?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <small class="text-muted">Deixa vazio se foi pago em dinheiro</small>
                </div>

                <div class="d-grid gap-2 mt-4">
                  <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-circle"></i> Guardar Alterações
                  </button>
                  <a href="transactions.php" class="btn btn-outline-secondary">
                    Cancelar
                  </a>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card">
            <div class="card-body p-4">
              <h5 class="mb-4"><i class="bi bi-info-circle"></i> Informações</h5>
              
              <div class="mb-3">
                <h6><i class="bi bi-clock-history text-primary"></i> Registo preservado</h6>
                <p class="text-muted small mb-0">A data e hora em que criaste o registo são mantidas, mesmo após editar.</p>
              </div>
              
              <div class="mb-3">
                <h6><i class="bi bi-calendar-event text-info"></i> Data editável</h6>
                <p class="text-muted small mb-0">Podes alterar a data em que a transação realmente ocorreu.</p>
              </div>
              
              <div class="mb-3">
                <h6><i class="bi bi-credit-card text-success"></i> Saldos automáticos</h6>
                <p class="text-muted small mb-0">Se mudares o cartão ou o valor, os saldos serão ajustados automaticamente.</p>
              </div>
              
              <div class="mb-3">
                <h6><i class="bi bi-pencil text-warning"></i> Editar com cuidado</h6>
                <p class="text-muted small mb-0">Certifica-te que os dados estão corretos antes de guardar as alterações.</p>
              </div>

              <hr class="my-4">

              <h6 class="mb-3">Dados Originais</h6>
              <div class="p-3 bg-light rounded">
                <div class="mb-2">
                  <small class="text-muted">Data da Transação</small>
                  <div><?=date('d/m/Y', strtotime($transaction['transaction_date']))?></div>
                </div>
                <div class="mb-2">
                  <small class="text-muted">Valor</small>
                  <div class="fw-bold text-danger">€<?=number_format($transaction['amount'], 2)?></div>
                </div>
                <div class="mb-2">
                  <small class="text-muted">Descrição</small>
                  <div><?=htmlspecialchars($transaction['description'])?></div>
                </div>
                <?php if ($transaction['category']): ?>
                <div class="mb-2">
                  <small class="text-muted">Categoria</small>
                  <div><span class="badge bg-info"><?=htmlspecialchars($transaction['category'])?></span></div>
                </div>
                <?php endif; ?>
                <div>
                  <small class="text-muted">Registo criado em</small>
                  <div><?=date('d/m/Y H:i', strtotime($transaction['created_at']))?></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Style for selected category
document.querySelectorAll('.category-option').forEach(label => {
  label.addEventListener('click', function() {
    document.querySelectorAll('.category-option').forEach(l => {
      l.classList.remove('selected');
    });
    this.classList.add('selected');
  });
});

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