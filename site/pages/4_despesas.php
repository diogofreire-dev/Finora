<?php
// site/pages/4_despesas.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

// Filtros
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');
$categoria = trim($_GET['categoria'] ?? '');
$cartao = trim($_GET['cartao'] ?? '');

// Buscar cartões do utilizador
$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid AND active = 1 ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

// Buscar categorias únicas
$stmt = $pdo->prepare("SELECT DISTINCT category FROM transactions WHERE user_id = :uid AND category IS NOT NULL AND category != '' ORDER BY category");
$stmt->execute([':uid' => $uid]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Query principal com filtros
$query = "
    SELECT t.*, c.name as card_name, q.nif_emitente, q.data_importacao
    FROM transactions t
    LEFT JOIN cards c ON t.card_id = c.id
    LEFT JOIN qr_despesas_import q ON t.id = q.id_despesa
    WHERE t.user_id = :uid
    AND MONTH(t.transaction_date) = :mes
    AND YEAR(t.transaction_date) = :ano
";

$params = [':uid' => $uid, ':mes' => $mes, ':ano' => $ano];

if (!empty($categoria)) {
    $query .= " AND t.category = :categoria";
    $params[':categoria'] = $categoria;
}

if (!empty($cartao)) {
    $query .= " AND c.name = :cartao";
    $params[':cartao'] = $cartao;
}

$query .= " ORDER BY t.transaction_date DESC, t.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$despesas = $stmt->fetchAll();

// Estatísticas do mês
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_despesas,
        SUM(amount) as total_valor,
        AVG(amount) as media_despesa,
        MAX(amount) as maior_despesa
    FROM transactions
    WHERE user_id = :uid
    AND MONTH(transaction_date) = :mes
    AND YEAR(transaction_date) = :ano
");
$stmt->execute([':uid' => $uid, ':mes' => $mes, ':ano' => $ano]);
$stats = $stmt->fetch();

// Processar remoção
$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'delete') {
    $despesaId = intval($_POST['despesa_id'] ?? 0);

    try {
        $pdo->beginTransaction();

        // Buscar despesa para reverter saldo do cartão
        $stmt = $pdo->prepare("SELECT amount, card_id FROM transactions WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $despesaId, ':uid' => $uid]);
        $despesa = $stmt->fetch();

        if ($despesa) {
            // Reverter saldo do cartão
            if ($despesa['card_id']) {
                $stmt = $pdo->prepare("UPDATE cards SET balance = balance - :amount WHERE id = :card_id AND user_id = :uid");
                $stmt->execute([':amount' => $despesa['amount'], ':card_id' => $despesa['card_id'], ':uid' => $uid]);
            }

            // Remover despesa
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = :id AND user_id = :uid");
            $stmt->execute([':id' => $despesaId, ':uid' => $uid]);

            // Remover registo QR se existir
            $stmt = $pdo->prepare("DELETE FROM qr_despesas_import WHERE id_despesa = :id");
            $stmt->execute([':id' => $despesaId]);

            $pdo->commit();
            $message = 'Despesa removida com sucesso!';
            $messageType = 'success';
        } else {
            $pdo->rollBack();
            $message = 'Despesa não encontrada.';
            $messageType = 'danger';
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = 'Erro ao remover despesa.';
        $messageType = 'danger';
    }
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ver Despesas - FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="../dashboard.php"><i class="bi bi-credit-card-fill"></i> FreeCard</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="../dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="3_index.php">Registar Despesa</a></li>
        <li class="nav-item"><a class="nav-link active" href="4_despesas.php">Ver Despesas</a></li>
        <li class="nav-item"><a class="nav-link" href="transactions.php">Transações</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4 mb-5">
  <div class="row">
    <div class="col-12">
      <div class="card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h2 class="mb-1"><i class="bi bi-receipt"></i> Despesas</h2>
            <p class="text-muted mb-0">Histórico completo das suas despesas</p>
          </div>
          <a href="3_index.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nova Despesa
          </a>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?=$messageType?> alert-dismissible fade show" role="alert">
          <?=$message?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Filtros -->
        <form method="get" class="row g-3 mb-4">
          <div class="col-md-2">
            <label class="form-label">Mês</label>
            <select name="mes" class="form-select">
              <?php for($i = 1; $i <= 12; $i++): ?>
              <option value="<?=$i?>" <?=$i == $mes ? 'selected' : ''?>>
                <?=date('F', mktime(0, 0, 0, $i, 1))?>
              </option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Ano</label>
            <select name="ano" class="form-select">
              <?php for($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
              <option value="<?=$i?>" <?=$i == $ano ? 'selected' : ''?>><?=$i?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Categoria</label>
            <select name="categoria" class="form-select">
              <option value="">Todas as categorias</option>
              <?php foreach($categories as $cat): ?>
              <option value="<?=$cat?>" <?=$categoria == $cat ? 'selected' : ''?>><?=$cat?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Cartão</label>
            <select name="cartao" class="form-select">
              <option value="">Todos os cartões</option>
              <?php foreach($cards as $card): ?>
              <option value="<?=$card['name']?>" <?=$cartao == $card['name'] ? 'selected' : ''?>><?=$card['name']?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-outline-primary w-100">
              <i class="bi bi-funnel"></i> Filtrar
            </button>
          </div>
        </form>

        <!-- Estatísticas -->
        <?php if ($stats): ?>
        <div class="row g-3 mb-4">
          <div class="col-md-3">
            <div class="card bg-light">
              <div class="card-body text-center">
                <div class="h4 mb-1"><?=$stats['total_despesas']?></div>
                <div class="text-muted">Total de Despesas</div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card bg-light">
              <div class="card-body text-center">
                <div class="h4 mb-1">€<?=number_format($stats['total_valor'], 2, ',', '.')?></div>
                <div class="text-muted">Valor Total</div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card bg-light">
              <div class="card-body text-center">
                <div class="h4 mb-1">€<?=number_format($stats['media_despesa'], 2, ',', '.')?></div>
                <div class="text-muted">Média por Despesa</div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card bg-light">
              <div class="card-body text-center">
                <div class="h4 mb-1">€<?=number_format($stats['maior_despesa'], 2, ',', '.')?></div>
                <div class="text-muted">Maior Despesa</div>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Lista de Despesas -->
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Data</th>
                <th>Descrição</th>
                <th>Categoria</th>
                <th>Cartão</th>
                <th class="text-end">Valor</th>
                <th class="text-center">Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($despesas)): ?>
              <tr>
                <td colspan="6" class="text-center text-muted py-4">
                  <i class="bi bi-info-circle fs-1"></i><br>
                  Nenhuma despesa encontrada para este período.
                </td>
              </tr>
              <?php else: ?>
                <?php foreach($despesas as $despesa): ?>
                <tr>
                  <td>
                    <?=date('d/m/Y', strtotime($despesa['transaction_date']))?>
                    <?php if ($despesa['data_importacao']): ?>
                    <br><small class="text-muted"><i class="bi bi-upc-scan"></i> QR</small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?=$despesa['description']?>
                    <?php if ($despesa['nif_emitente']): ?>
                    <br><small class="text-muted">NIF: <?=$despesa['nif_emitente']?></small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($despesa['category']): ?>
                    <span class="badge bg-secondary"><?=$despesa['category']?></span>
                    <?php else: ?>
                    <span class="text-muted">Sem categoria</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($despesa['card_name']): ?>
                    <i class="bi bi-credit-card"></i> <?=$despesa['card_name']?>
                    <?php else: ?>
                    <span class="text-muted">Sem cartão</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end fw-bold">
                    €<?=number_format($despesa['amount'], 2, ',', '.')?>
                  </td>
                  <td class="text-center">
                    <form method="post" class="d-inline" onsubmit="return confirm('Tem certeza que deseja remover esta despesa?')">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="despesa_id" value="<?=$despesa['id']?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>