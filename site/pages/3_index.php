<?php
// site/pages/3_index.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

// Buscar cartões do utilizador
$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid AND active = 1 ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

// Buscar categorias únicas para auto-complete
$stmt = $pdo->prepare("SELECT DISTINCT category FROM transactions WHERE user_id = :uid AND category IS NOT NULL AND category != '' ORDER BY category");
$stmt->execute([':uid' => $uid]);
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $card_id = !empty($_POST['card_id']) ? intval($_POST['card_id']) : null;
    $transaction_date = trim($_POST['transaction_date'] ?? date('Y-m-d'));

    if ($amount <= 0) {
        $message = 'Valor deve ser maior que zero.';
        $messageType = 'danger';
    } elseif (empty($description)) {
        $message = 'Descrição é obrigatória.';
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, card_id, amount, description, category, transaction_date, created_at) VALUES (:uid, :cid, :amt, :desc, :cat, :tdate, NOW())");
            $stmt->execute([
                ':uid' => $uid,
                ':cid' => $card_id,
                ':amt' => $amount,
                ':desc' => $description,
                ':cat' => $category,
                ':tdate' => $transaction_date
            ]);

            if ($card_id) {
                $stmt = $pdo->prepare("UPDATE cards SET balance = balance + :amt WHERE id = :cid AND user_id = :uid");
                $stmt->execute([':amt' => $amount, ':cid' => $card_id, ':uid' => $uid]);
            }

            $pdo->commit();
            $message = 'Despesa registada com sucesso!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'Erro ao registar despesa.';
            $messageType = 'danger';
        }
    }
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registar Despesa - FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <script src="https://unpkg.com/html5-qrcode"></script>
  <script src="https://unpkg.com/@zxing/library@0.19.1"></script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="../dashboard.php"><i class="bi bi-credit-card-fill"></i> FreeCard</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="../dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link active" href="3_index.php">Registar Despesa</a></li>
        <li class="nav-item"><a class="nav-link" href="4_despesas.php">Ver Despesas</a></li>
        <li class="nav-item"><a class="nav-link" href="transactions.php">Transações</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4 mb-5">
  <div class="row">
    <div class="col-lg-8 mx-auto">
      <div class="card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h2 class="mb-1"><i class="bi bi-plus-circle"></i> Registar Despesa</h2>
            <p class="text-muted mb-0">Adicione uma nova despesa manualmente ou via QR Code</p>
          </div>
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#qrModal">
            <i class="bi bi-upc-scan"></i> QR Code
          </button>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?=$messageType?> alert-dismissible fade show" role="alert">
          <?=$message?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="post" id="despesaForm">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="amount" class="form-label">Valor (€) *</label>
              <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="transaction_date" class="form-label">Data *</label>
              <input type="date" class="form-control" id="transaction_date" name="transaction_date" value="<?=date('Y-m-d')?>" required>
            </div>
          </div>

          <div class="mb-3">
            <label for="description" class="form-label">Descrição *</label>
            <input type="text" class="form-control" id="description" name="description" placeholder="Ex: Supermercado, Combustível, etc." required>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="category" class="form-label">Categoria</label>
              <input type="text" class="form-control" id="category" name="category" list="categoriesList" placeholder="Ex: Alimentação, Transporte">
              <datalist id="categoriesList">
                <?php foreach($categories as $cat): ?>
                <option value="<?=$cat?>">
                <?php endforeach; ?>
              </datalist>
            </div>
            <div class="col-md-6 mb-3">
              <label for="card_id" class="form-label">Cartão</label>
              <select class="form-select" id="card_id" name="card_id">
                <option value="">Sem cartão associado</option>
                <?php foreach($cards as $card): ?>
                <option value="<?=$card['id']?>"><?=$card['name']?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-success btn-lg">
              <i class="bi bi-check-circle"></i> Registar Despesa
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal QR Code -->
<div class="modal fade" id="qrModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-upc-scan"></i> Ler QR Code da Fatura</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Passo 1: Scanner -->
        <div id="scannerStep" class="text-center">
          <div class="mb-3">
            <h6>Passo 1: Digitalize o QR Code</h6>
            <p class="text-muted">Aponte a câmara para o QR Code da fatura</p>
          </div>
          <div id="qrScanner" class="mb-3" style="display: none;">
            <div id="reader" style="width: 100%; max-width: 400px; margin: 0 auto;"></div>
          </div>
          <div class="mb-3">
            <button type="button" class="btn btn-outline-primary me-2" id="startCamera">
              <i class="bi bi-camera"></i> Iniciar Câmara
            </button>
            <button type="button" class="btn btn-outline-secondary" id="uploadImage">
              <i class="bi bi-upload"></i> Carregar Imagem
            </button>
            <input type="file" id="imageInput" accept="image/*" style="display: none;">
          </div>
          <div id="scanStatus" class="alert alert-info" style="display: none;">
            <i class="bi bi-info-circle"></i> Procurando QR Code...
          </div>
        </div>

        <!-- Passo 2: Formulário -->
        <div id="formStep" style="display: none;">
          <h6>Passo 2: Confirme os dados</h6>
          <form id="qrForm">
            <input type="hidden" id="qr_raw" name="qr_raw">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Valor (€)</label>
                <input type="number" step="0.01" class="form-control" id="qr_amount" name="amount" readonly>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Data</label>
                <input type="date" class="form-control" id="qr_date" name="transaction_date" readonly>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Empresa</label>
              <input type="text" class="form-control" id="qr_empresa" name="description" placeholder="A carregar...">
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Categoria</label>
                <input type="text" class="form-control" id="qr_category" name="category" list="qrCategoriesList">
                <datalist id="qrCategoriesList">
                  <?php foreach($categories as $cat): ?>
                  <option value="<?=$cat?>">
                  <?php endforeach; ?>
                </datalist>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Cartão</label>
                <select class="form-select" id="qr_card_id" name="card_id">
                  <option value="">Sem cartão</option>
                  <?php foreach($cards as $card): ?>
                  <option value="<?=$card['id']?>"><?=$card['name']?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">NIF</label>
              <input type="text" class="form-control" id="qr_nif" readonly>
            </div>
          </form>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="backToScan" style="display: none;">Voltar</button>
        <button type="button" class="btn btn-primary" id="confirmQR" style="display: none;">Registar Despesa</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// QR Code Scanner
let html5QrcodeScanner = null;
let currentQRData = null;

document.getElementById('startCamera').addEventListener('click', startCamera);
document.getElementById('uploadImage').addEventListener('click', () => {
  document.getElementById('imageInput').click();
});
document.getElementById('imageInput').addEventListener('change', handleImageUpload);
document.getElementById('backToScan').addEventListener('click', backToScan);
document.getElementById('confirmQR').addEventListener('click', confirmQR);

function startCamera() {
  document.getElementById('qrScanner').style.display = 'block';
  document.getElementById('scanStatus').style.display = 'block';
  document.getElementById('scanStatus').className = 'alert alert-info';
  document.getElementById('scanStatus').innerHTML = '<i class="bi bi-info-circle"></i> Procurando QR Code...';

  html5QrcodeScanner = new Html5QrcodeScanner("reader", {
    fps: 10,
    qrbox: 250
  });

  html5QrcodeScanner.render(onScanSuccess, onScanError);
}

function onScanSuccess(decodedText, decodedResult) {
  currentQRData = decodedText;
  processQRData(decodedText);
}

function onScanError(error) {
  console.log('Scan error:', error);
}

function handleImageUpload(event) {
  const file = event.target.files[0];
  if (!file) return;

  document.getElementById('scanStatus').style.display = 'block';
  document.getElementById('scanStatus').className = 'alert alert-info';
  document.getElementById('scanStatus').innerHTML = '<i class="bi bi-info-circle"></i> Processando imagem...';

  const reader = new FileReader();
  reader.onload = function(e) {
    processImage(e.target.result);
  };
  reader.readAsDataURL(file);
}

async function processImage(imageData) {
  try {
    // Redimensionar imagem para processamento mais rápido
    const img = new Image();
    img.onload = async function() {
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      const maxSize = 800;
      const ratio = Math.min(maxSize / img.width, maxSize / img.height);
      canvas.width = img.width * ratio;
      canvas.height = img.height * ratio;
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

      const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

      // Tentar múltiplas abordagens
      let qrCode = null;

      // 1. Tentar com ZXing (mais robusto)
      try {
        const codeReader = new ZXing.BrowserQRCodeReader();
        qrCode = await codeReader.decodeFromImageData(imageData);
        if (qrCode) {
          processQRData(qrCode.text);
          return;
        }
      } catch (e) {
        console.log('ZXing falhou:', e);
      }

      // 2. Tentar com jsQR (fallback)
      try {
        qrCode = jsQR(imageData.data, imageData.width, imageData.height);
        if (qrCode) {
          processQRData(qrCode.data);
          return;
        }
      } catch (e) {
        console.log('jsQR falhou:', e);
      }

      // 3. Tentar com diferentes escalas
      for (let scale of [0.75, 1.5, 2.0]) {
        try {
          const scaledCanvas = document.createElement('canvas');
          const scaledCtx = scaledCanvas.getContext('2d');
          scaledCanvas.width = canvas.width * scale;
          scaledCanvas.height = canvas.height * scale;
          scaledCtx.drawImage(canvas, 0, 0, scaledCanvas.width, scaledCanvas.height);
          const scaledData = scaledCtx.getImageData(0, 0, scaledCanvas.width, scaledCanvas.height);

          qrCode = jsQR(scaledData.data, scaledData.width, scaledData.height);
          if (qrCode) {
            processQRData(qrCode.data);
            return;
          }
        } catch (e) {
          continue;
        }
      }

      document.getElementById('scanStatus').className = 'alert alert-danger';
      document.getElementById('scanStatus').innerHTML = '<i class="bi bi-exclamation-triangle"></i> QR Code não encontrado na imagem. Tente uma imagem mais nítida.';
    };
    img.src = imageData;
  } catch (error) {
    console.error('Erro ao processar imagem:', error);
    document.getElementById('scanStatus').className = 'alert alert-danger';
    document.getElementById('scanStatus').innerHTML = '<i class="bi bi-exclamation-triangle"></i> Erro ao processar imagem.';
  }
}

function processQRData(qrData) {
  // Parar scanner se ativo
  if (html5QrcodeScanner) {
    html5QrcodeScanner.clear();
  }

  document.getElementById('scanStatus').className = 'alert alert-success';
  document.getElementById('scanStatus').innerHTML = '<i class="bi bi-check-circle"></i> QR Code detetado! Processando...';

  // Enviar para processamento no servidor
  fetch('processar_qr_despesa.php?action=parsear', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'qr_data=' + encodeURIComponent(qrData)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      mostrarFormQR(data.dados, qrData);
    } else {
      document.getElementById('scanStatus').className = 'alert alert-danger';
      document.getElementById('scanStatus').innerHTML = '<i class="bi bi-exclamation-triangle"></i> ' + data.message;
    }
  })
  .catch(error => {
    console.error('Erro:', error);
    document.getElementById('scanStatus').className = 'alert alert-danger';
    document.getElementById('scanStatus').innerHTML = '<i class="bi bi-exclamation-triangle"></i> Erro de comunicação.';
  });
}

function mostrarFormQR(dados, qrRaw) {
  // Esconder scanner e mostrar formulário
  document.getElementById('scannerStep').style.display = 'none';
  document.getElementById('formStep').style.display = 'block';
  document.getElementById('backToScan').style.display = 'inline-block';
  document.getElementById('confirmQR').style.display = 'inline-block';

  // Preencher campos
  document.getElementById('qr_amount').value = dados.total || '';
  document.getElementById('qr_date').value = dados.data || '';
  document.getElementById('qr_empresa').value = dados.empresa || '';
  document.getElementById('qr_category').value = dados.categoria || '';
  document.getElementById('qr_nif').value = dados.nif || '';
  document.getElementById('qr_raw').value = qrRaw;

  // Buscar nome da empresa se temos NIF
  if (dados.nif && !dados.empresa) {
    buscarNomeEmpresa(dados.nif);
  }
}

function buscarNomeEmpresa(nif) {
  fetch(`processar_qr_despesa.php?action=lookup_nif&nif=${nif}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        document.getElementById('qr_empresa').value = data.nome;
      }
    })
    .catch(error => console.error('Erro ao buscar empresa:', error));
}

function backToScan() {
  document.getElementById('formStep').style.display = 'none';
  document.getElementById('scannerStep').style.display = 'block';
  document.getElementById('backToScan').style.display = 'none';
  document.getElementById('confirmQR').style.display = 'none';
  document.getElementById('scanStatus').style.display = 'none';

  // Reiniciar scanner se necessário
  if (html5QrcodeScanner) {
    html5QrcodeScanner.clear();
  }
  currentQRData = null;
}

function confirmQR() {
  const formData = new FormData(document.getElementById('qrForm'));

  document.getElementById('confirmQR').disabled = true;
  document.getElementById('confirmQR').innerHTML = '<i class="bi bi-hourglass"></i> A registar...';

  fetch('processar_qr_despesa.php?action=criar', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      total: formData.get('amount'),
      data: formData.get('transaction_date'),
      empresa: formData.get('description'),
      categoria: formData.get('category'),
      card_id: formData.get('card_id'),
      nif: document.getElementById('qr_nif').value,
      qr_raw: formData.get('qr_raw')
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Fechar modal e recarregar página
      bootstrap.Modal.getInstance(document.getElementById('qrModal')).hide();
      location.reload();
    } else {
      alert('Erro: ' + data.message);
      document.getElementById('confirmQR').disabled = false;
      document.getElementById('confirmQR').innerHTML = 'Registar Despesa';
    }
  })
  .catch(error => {
    console.error('Erro:', error);
    alert('Erro de comunicação');
    document.getElementById('confirmQR').disabled = false;
    document.getElementById('confirmQR').innerHTML = 'Registar Despesa';
  });
}

// Limpar scanner quando modal fecha
document.getElementById('qrModal').addEventListener('hidden.bs.modal', function() {
  if (html5QrcodeScanner) {
    html5QrcodeScanner.clear();
  }
  backToScan();
});
</script>
</body>
</html>