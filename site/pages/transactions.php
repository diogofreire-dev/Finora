<?php
// site/transactions.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$message = '';
$messageType = 'info';

// Processar remoção de transação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'delete') {
    $transactionId = intval($_POST['transaction_id'] ?? 0);
    
    try {
        $pdo->beginTransaction();
        
        // Buscar a transação para saber o valor e o cartão
        $stmt = $pdo->prepare("SELECT amount, card_id FROM transactions WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $transactionId, ':uid' => $uid]);
        $transaction = $stmt->fetch();
        
        if ($transaction) {
            // Se a transação estava associada a um cartão, devolver o valor ao saldo disponível
            if ($transaction['card_id']) {
                $stmt = $pdo->prepare("
                    UPDATE cards 
                    SET balance = balance - :amount 
                    WHERE id = :card_id AND user_id = :uid
                ");
                $stmt->execute([
                    ':amount' => $transaction['amount'],
                    ':card_id' => $transaction['card_id'],
                    ':uid' => $uid
                ]);
            }
            
            // Remover a transação
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = :id AND user_id = :uid");
            $stmt->execute([':id' => $transactionId, ':uid' => $uid]);
            
            $pdo->commit();
            $message = 'Transação removida com sucesso!';
            $messageType = 'success';
        } else {
            $pdo->rollBack();
            $message = 'Transação não encontrada.';
            $messageType = 'danger';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = 'Erro ao remover transação.';
        $messageType = 'danger';
    }
}

// Filtros
$category = $_GET['category'] ?? '';
$card_id = !empty($_GET['card_id']) ? intval($_GET['card_id']) : null;
$month = $_GET['month'] ?? date('Y-m');

// Buscar categorias únicas
$stmt = $pdo->prepare("SELECT DISTINCT category FROM transactions WHERE user_id = :uid AND category IS NOT NULL ORDER BY category");
$stmt->execute([':uid' => $uid]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Buscar cartões
$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

// Query base
$sql = "
    SELECT t.*, c.name AS card_name
    FROM transactions t
    LEFT JOIN cards c ON c.id = t.card_id
    WHERE t.user_id = :uid
";
$params = [':uid' => $uid];

// Aplicar filtros
if ($category) {
    $sql .= " AND t.category = :cat";
    $params[':cat'] = $category;
}
if ($card_id) {
    $sql .= " AND t.card_id = :cid";
    $params[':cid'] = $card_id;
}
if ($month) {
    $sql .= " AND DATE_FORMAT(t.transaction_date, '%Y-%m') = :month";
    $params[':month'] = $month;
}

$sql .= " ORDER BY t.transaction_date DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Total filtrado
$total = array_sum(array_column($transactions, 'amount'));

// Agrupar por dia
$byDay = [];
foreach ($transactions as $t) {
    $day = date('Y-m-d', strtotime($t['transaction_date']));
    if (!isset($byDay[$day])) {
        $byDay[$day] = [];
    }
    $byDay[$day][] = $t;
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Transações - FreeCard</title>
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
      --primary-green: #3498db;
      --dark-green: #2980b9;
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
    .card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 4px 20px var(--shadow);
      background: var(--bg-secondary);
      color: var(--text-primary);
    }
    .filter-card {
      background: var(--bg-secondary);
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 2px 10px var(--shadow);
    }
    .transaction-item {
      background: var(--bg-secondary);
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 12px;
      border: 1px solid var(--border-color);
      transition: all 0.2s;
    }
    .transaction-item:hover {
      box-shadow: 0 4px 15px var(--shadow);
      background: var(--bg-hover);
    }
    .transaction-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
      color: white;
      flex-shrink: 0;
    }
    .transaction-amount {
      font-size: 18px;
      font-weight: 700;
      color: #e74c3c;
    }
    .day-separator {
      font-weight: 700;
      color: var(--text-primary);
      margin: 24px 0 16px;
      padding-bottom: 8px;
      border-bottom: 2px solid var(--border-color);
    }
    .category-badge {
      background: var(--bg-hover);
      color: var(--text-primary);
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
    }
    .summary-card {
      background: var(--bg-secondary);
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 20px var(--shadow);
    }
    .stat-item {
      text-align: center;
      padding: 16px;
    }
    .stat-item h3 {
      font-size: 28px;
      font-weight: 800;
      margin-bottom: 4px;
      color: var(--text-primary);
    }
    .stat-item p {
      font-size: 13px;
      margin: 0;
      color: var(--text-secondary);
    }
    .stat-item:not(:last-child) {
      border-right: 1px solid var(--border-color);
    }
    .btn-delete {
      padding: 6px 12px;
      font-size: 14px;
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
    
    /* ========== MOBILE RESPONSIVE FIXES ========== */
    @media (max-width: 768px) {
      /* Remover border-right e adicionar border-bottom em mobile */
      .stat-item:not(:last-child) {
        border-right: none !important;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 16px;
        margin-bottom: 16px;
      }
      
      .stat-item:last-child {
        padding-bottom: 0;
        margin-bottom: 0;
      }
      
      /* Ajustar tamanho dos títulos em mobile */
      .stat-item h3 {
        font-size: 24px;
      }
      
      .stat-item p {
        font-size: 12px;
      }
      
      /* Corrigir transações em mobile */
      .transaction-item > .d-flex {
        flex-direction: column !important;
        gap: 12px !important;
      }
      
      .transaction-item .text-end {
        text-align: left !important;
        width: 100%;
      }
      
      .transaction-item .text-end .d-flex.gap-2 {
        flex-direction: row !important;
        width: 100%;
      }
      
      .transaction-icon {
        width: 40px;
        height: 40px;
        font-size: 18px;
      }
      
      .transaction-amount {
        font-size: 20px;
        margin-bottom: 8px;
      }
      
      /* Ajustar botões em mobile */
      .btn-delete {
        font-size: 13px;
        padding: 4px 8px;
      }
    }

    /* Corrigir filtros em mobile */
    @media (max-width: 768px) {
      .filter-card .row {
        margin-bottom: 0 !important;
      }

      .filter-card .col-md-3 {
        margin-bottom: 12px;
      }

      .filter-card .col-md-3:last-child {
        margin-bottom: 0;
      }
    }

    /* Header da página responsivo */
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      gap: 1rem;
    }

    .page-header-buttons {
      display: flex;
      gap: 0.5rem;
    }

    @media (max-width: 576px) {
      .page-header {
        flex-direction: column;
        align-items: stretch;
      }

      .page-header-buttons {
        width: 100%;
      }

      .page-header-buttons .btn {
        flex: 1;
        white-space: nowrap;
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
      }
    }
  </style>
</head>
<body>
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
    <a class="navbar-brand fw-bold" href="index.php">
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

<div class="container mt-4 mb-5">
  <div class="page-header">
    <div>
      <h2><i class="bi bi-receipt"></i> As Minhas Transações</h2>
      <p class="text-muted mb-0">Histórico completo de despesas</p>
    </div>
    <div class="page-header-buttons">
      <a href="create_transaction.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nova Transação
      </a>
      <a href="invoices.php" class="btn btn-outline-success ms-2">
        <i class="bi bi-upc-scan"></i> Faturas (QR/OCR)
      </a>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert alert-<?=$messageType?> alert-dismissible fade show">
      <i class="bi bi-<?=$messageType === 'success' ? 'check-circle' : 'exclamation-circle'?>"></i>
      <?=htmlspecialchars($message)?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Card Exportar PDF -->
  <div class="card shadow-sm mb-4">
    <div class="card-body py-3 px-4">
      <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
          <i class="bi bi-file-earmark-pdf" style="font-size: 24px; color: #e74c3c;"></i>
          <div>
            <small class="text-muted d-block" style="font-size: 12px;">Relatórios</small>
            <strong>Exporta os teus dados em PDF</strong>
          </div>
        </div>
        <a href="export_pdf.php" class="btn btn-sm btn-danger">
          <i class="bi bi-download"></i> Exportar PDF
        </a>
      </div>
    </div>
  </div>

  <!-- Resumo -->
  <?php if (!empty($transactions)): ?>
    <div class="summary-card mb-4">
      <div class="row">
        <div class="col-12 col-md-4">
          <div class="stat-item">
            <h3><?=count($transactions)?></h3>
            <p>Transações</p>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="stat-item">
            <h3 class="text-danger">€<?=number_format($total, 2)?></h3>
            <p>Total Gasto</p>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="stat-item">
            <h3 style="color: #3498db;">€<?=count($transactions) > 0 ? number_format($total / count($transactions), 2) : '0.00'?></h3>
            <p>Média</p>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Filtros -->
  <div class="filter-card mb-4">
    <form method="get" class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-semibold">
          <i class="bi bi-calendar"></i> Mês
        </label>
        <input type="month" name="month" class="form-control" value="<?=htmlspecialchars($month)?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">
          <i class="bi bi-tag"></i> Categoria
        </label>
        <select name="category" class="form-select">
          <option value="">Todas as categorias</option>
          <?php foreach($categories as $cat): ?>
            <option value="<?=htmlspecialchars($cat)?>" <?=$category === $cat ? 'selected' : ''?>>
              <?=htmlspecialchars($cat)?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">
          <i class="bi bi-credit-card"></i> Cartão
        </label>
        <select name="card_id" class="form-select">
          <option value="">Todos os cartões</option>
          <?php foreach($cards as $c): ?>
            <option value="<?=$c['id']?>" <?=$card_id == $c['id'] ? 'selected' : ''?>>
              <?=htmlspecialchars($c['name'])?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill">
            <i class="bi bi-search"></i> Filtrar
          </button>
          <a href="transactions.php" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg"></i>
          </a>
        </div>
      </div>
    </form>
  </div>

  <!-- Lista de Transações -->
  <?php if (empty($transactions)): ?>
    <div class="card">
      <div class="card-body text-center py-5">
        <div class="mb-4">
          <i class="bi bi-receipt" style="font-size: 80px; color: #e0e0e0;"></i>
        </div>
        <h4 class="text-muted mb-3">Nenhuma transação encontrada</h4>
        <p class="text-muted mb-4">Altera os filtros ou cria a tua primeira transação</p>
        <a href="create_transaction.php" class="btn btn-primary">
          <i class="bi bi-plus-circle"></i> Criar Transação
        </a>
      </div>
    </div>
  <?php else: ?>
    <div class="card">
      <div class="card-body p-4">
        <?php foreach($byDay as $day => $dayTransactions): ?>
          <div class="day-separator">
            <?php
              $dayObj = new DateTime($day);
              $today = new DateTime();
              $yesterday = (new DateTime())->modify('-1 day');
              
              if ($dayObj->format('Y-m-d') === $today->format('Y-m-d')) {
                echo 'Hoje';
              } elseif ($dayObj->format('Y-m-d') === $yesterday->format('Y-m-d')) {
                echo 'Ontem';
              } else {
                // Arrays para tradução
                $diasSemana = [
                  'Sunday' => 'Domingo',
                  'Monday' => 'Segunda-feira',
                  'Tuesday' => 'Terça-feira',
                  'Wednesday' => 'Quarta-feira',
                  'Thursday' => 'Quinta-feira',
                  'Friday' => 'Sexta-feira',
                  'Saturday' => 'Sábado'
                ];
                
                $meses = [
                  'January' => 'janeiro',
                  'February' => 'fevereiro',
                  'March' => 'março',
                  'April' => 'abril',
                  'May' => 'maio',
                  'June' => 'junho',
                  'July' => 'julho',
                  'August' => 'agosto',
                  'September' => 'setembro',
                  'October' => 'outubro',
                  'November' => 'novembro',
                  'December' => 'dezembro'
                ];
                
                $diaSemanaEn = $dayObj->format('l');
                $mesEn = $dayObj->format('F');
                $dia = $dayObj->format('d');
                
                $diaSemana = $diasSemana[$diaSemanaEn];
                $mes = $meses[$mesEn];
                
                echo "{$diaSemana}, {$dia} de {$mes}";
              }
              
              $dayTotal = array_sum(array_column($dayTransactions, 'amount'));
            ?>
            <span class="float-end text-danger">-€<?=number_format($dayTotal, 2)?></span>
          </div>

          <?php foreach($dayTransactions as $t): ?>
            <div class="transaction-item">
              <div class="d-flex align-items-start gap-3">
                <div class="transaction-icon">
                  <i class="bi bi-<?=
                    match($t['category']) {
                      'Compras' => 'cart',
                      'Alimentação' => 'cup-straw',
                      'Transporte' => 'bus-front',
                      'Saúde' => 'heart-pulse',
                      'Entretenimento' => 'controller',
                      'Educação' => 'book',
                      'Casa' => 'house-door',
                      default => 'receipt'
                    }
                  ?>"></i>
                </div>
                <div class="flex-grow-1">
                  <div class="fw-semibold"><?=htmlspecialchars($t['description'])?></div>
                  <div class="d-flex gap-2 align-items-center mt-1 flex-wrap">
                    <?php if($t['category']): ?>
                      <span class="category-badge"><?=htmlspecialchars($t['category'])?></span>
                    <?php endif; ?>
                    <?php if($t['card_name']): ?>
                      <small class="text-muted">
                        <i class="bi bi-credit-card"></i>
                        <?=htmlspecialchars($t['card_name'])?>
                      </small>
                    <?php else: ?>
                      <small class="text-muted">
                        <i class="bi bi-cash"></i> Dinheiro
                      </small>
                    <?php endif; ?>
                    <small class="text-muted">
                        <i class="bi bi-calendar-event"></i> <?=date('d/m/Y', strtotime($t['transaction_date']))?>
                    </small>
                  </div>
                </div>
                <div class="text-end">
                  <div class="transaction-amount">
                    -€<?=number_format($t['amount'], 2)?>
                  </div>
                  <div class="d-flex gap-2 mt-2">
                    <a href="edit_transaction.php?id=<?=$t['id']?>" class="btn btn-sm btn-outline-primary btn-delete">
                      <i class="bi bi-pencil"></i> Editar
                    </a>
                    <form method="post" style="margin: 0;" onsubmit="return confirm('Tens a certeza que queres remover esta transação? Esta ação não pode ser revertida.');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="transaction_id" value="<?=$t['id']?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger btn-delete">
                        <i class="bi bi-trash"></i> Remover
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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