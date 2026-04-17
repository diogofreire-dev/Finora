<?php
// site/pages/3_index.php - FIXED QR CAMERA VERSION
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

<!-- Modal QR Code - FIXED CAMERA -->
<div class="modal fade" id="qrModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-upc-scan"></i> Ler QR Code da Fatura</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Camera Troubleshooting Alert -->
        <div id="cameraTroubleshoot" class="alert alert-warning" style="display:none;">
          <h6><i class="bi bi-exclamation-triangle"></i> Problemas com a câmara?</h6>
          <ul class="mb-0">
            <li>🔒 Use <strong>HTTPS</strong> (instale SSL local ou use ngrok)</li>
            <li>📱 No telemóvel use câmara traseira</li>
            <li>🔐 Permita acesso à câmara no browser</li>
            <li>📁 Alternativa: <strong>Carregar Imagem</strong></li>
          </ul>
          <button class="btn btn-sm btn-outline-primary" onclick="checkCameraSupport()">Verificar Câmara</button>
        </div>

        <!-- Passo 1: Scanner -->
        <div id="scannerStep" class="text-center">
          <div class="mb-3">
            <h6>Passo 1: Digitalize o QR Code</h6>
            <p class="text-muted">Aponte a câmara para o QR Code da fatura</p>
          </div>
          
          <!-- Device Selector -->
          <div id="deviceSelector" class="mb-3" style="display:none;">
            <label class="form-label small">Selecionar Câmara:</label>
            <select id="cameraSelect" class="form-select form-select-sm w-50 mx-auto"></select>
          </div>

          <div id="qrScanner" class="mb-3" style="display: none;">
    <div id="reader" style="width: 100%; max-width: 500px; margin: 100px auto; height: 300px; position: relative; overflow: hidden; border-radius: 12px;"></div>

          </div>
          
          <div class="mb-3">
            <div class="btn-group" role="group">
              <button type="button" class="btn btn-outline-primary" id="startCamera">
                <i class="bi bi-camera"></i> Iniciar Câmara
              </button>
              <button type="button" class="btn btn-outline-secondary" id="stopCamera" style="display:none;">
                <i class="bi bi-stop-circle"></i> Parar
              </button>
            </div>
            <button type="button" class="btn btn-outline-info mt-2" id="uploadImage">
              <i class="bi bi-upload"></i> Carregar Imagem
            </button>
            <input type="file" id="imageInput" accept="image/*" style="display: none;">
          </div>
          
          <div id="scanStatus" class="alert alert-info" style="display: none;">
            <i class="bi bi-info-circle"></i> Procurando QR Code...
          </div>
        </div>

        <!-- Passo 2: Formulário (unchanged) -->
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

<!-- Troubleshooting Modal -->
<div class="modal fade" id="troubleshootModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">🛠️ Resolver Problemas da Câmara</h5>
      </div>
      <div class="modal-body">
        <div id="cameraInfo"></div>
        <hr>
        <h6>Soluções Rápidas:</h6>
        <ul>
          <li><strong>1. HTTPS:</strong> Instale SSL local (mkcert) ou use <a href="https://ngrok.com" target="_blank">ngrok</a></li>
          <li><strong>2. Permissões:</strong> Verifique site settings → Câmara → Permitir</li>
          <li><strong>3. Navegador:</strong> Chrome/Firefox/Edge atualizado</li>
          <li><strong>4. HTTPS Test:</strong> <span id="httpsStatus"></span></li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" onclick="checkCameraSupport()">Testar Novamente</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// FIXED QR Scanner with Camera Support Detection & Permissions
let html5QrcodeScanner = null;
let availableCameras = [];
let currentCameraId = null;
let currentQRData = null;

const startBtn = document.getElementById('startCamera');
const stopBtn = document.getElementById('stopCamera');
const uploadBtn = document.getElementById('uploadImage');
const statusEl = document.getElementById('scanStatus');
const troubleshootEl = document.getElementById('cameraTroubleshoot');
const deviceSelect = document.getElementById('deviceSelector');
const cameraSelect = document.getElementById('cameraSelect');

// Event listeners
startBtn.addEventListener('click', checkCameraSupport);
stopBtn.addEventListener('click', stopScanner);
uploadBtn.addEventListener('click', () => document.getElementById('imageInput').click());
document.getElementById('imageInput').addEventListener('change', handleImageUpload);
document.getElementById('backToScan').addEventListener('click', backToScan);
document.getElementById('confirmQR').addEventListener('click', confirmQR);

// Check HTTPS & Camera Support on Modal Show
document.getElementById('qrModal').addEventListener('shown.bs.modal', async function() {
  await checkCameraSupport();
  checkHTTPS();
});

async function checkCameraSupport() {
  try {
    // Check HTTPS
    if (!window.isSecureContext) {
      showStatus('alert-warning', '<strong>HTTPS necessário</strong> para usar a câmara. Use ngrok ou SSL local.');
      troubleshootEl.style.display = 'block';
      new bootstrap.Modal(document.getElementById('troubleshootModal')).show();
      return false;
    }

    // Enumerate devices
    availableCameras = await Html5Qrcode.getCameras();
    
    if (availableCameras.length === 0) {
      showStatus('alert-danger', 'Nenhuma câmara encontrada');
      troubleshootEl.style.display = 'block';
      return false;
    }

    // Populate selector & prefer back camera
    cameraSelect.innerHTML = '';
    availableCameras.forEach((camera, index) => {
      const option = document.createElement('option');
      option.value = camera.id;
      option.text = camera.label || `Câmara ${index + 1} (${camera.facingMode || 'unknown'})`;
      if (camera.facingMode === 'environment') currentCameraId = camera.id;
      cameraSelect.appendChild(option);
    });

    if (currentCameraId) {
      cameraSelect.value = currentCameraId;
    }

    deviceSelect.style.display = 'block';
    showStatus('alert-success', `${availableCameras.length} câmaras encontradas. Selecione e inicie.`);
    startBtn.innerHTML = '<i class="bi bi-camera"></i> Iniciar Câmara Selecionada';
    
    return true;
  } catch (err) {
    console.error('Camera check failed:', err);
    showStatus('alert-danger', 'Erro ao verificar câmaras: ' + err.message);
    troubleshootEl.style.display = 'block';
    return false;
  }
}

function checkHTTPS() {
  const status = document.getElementById('httpsStatus');
  status.innerHTML = window.isSecureContext ? 'HTTPS OK' : 'HTTP (bloqueia câmara)';
}

async function startScanner() {
  if (!currentCameraId) {
    showStatus('alert-warning', 'Selecione uma câmara primeiro');
    return;
  }

  html5Qrcode = new Html5Qrcode("reader");
  const config = {
    fps: 10,
    qrbox: function(viewfinderWidth, viewfinderHeight) {
      let size = Math.min(viewfinderWidth, viewfinderHeight) * 0.6;
      return {width: size, height: size};
    }
  };

  try {
    await html5Qrcode.start(
      { deviceId: { exact: currentCameraId } },
      config,
      onScanSuccess,
      onScanError
    );
    showStatus('alert-success', 'Câmara ativa! Aponte para QR Code...');
    document.getElementById('qrScanner').style.display = 'block';
    startBtn.style.display = 'none';
    stopBtn.style.display = 'inline-block';
    deviceSelect.style.display = 'none';
  } catch (err) {
    console.error('Erro ao iniciar scanner:', err);
    showStatus('alert-danger', 'Erro ao iniciar câmara: ' + err.message);
    new bootstrap.Modal(document.getElementById('troubleshootModal')).show();
  }
}


function onScanSuccess(decodedText, decodedResult) {
  currentQRData = decodedText;
  processQRData(decodedText);
}

function onScanError(err) {
  // Silent errors during scan
}

function stopScanner() {
  if (html5QrcodeScanner) {
    html5QrcodeScanner.clear().catch(console.error);
    html5QrcodeScanner = null;
  }
  startBtn.style.display = 'inline-block';
  startBtn.innerHTML = '<i class="bi bi-camera"></i> Iniciar Câmara';
  stopBtn.style.display = 'none';
  deviceSelect.style.display = 'block';
  document.getElementById('qrScanner').style.display = 'none';
  statusEl.style.display = 'none';
}

function showStatus(type, message) {
  statusEl.className = `alert ${type}`;
  statusEl.innerHTML = message;
  statusEl.style.display = 'block';
}

// Image upload & processing (unchanged - already robust)
function handleImageUpload(event) {
  const file = event.target.files[0];
  if (!file) return;

  showStatus('alert-info', '<i class="bi bi-image"></i> Processando imagem...');

  const reader = new FileReader();
  reader.onload = function(e) {
    processImage(e.target.result);
  };
  reader.readAsDataURL(file);
}

// [Rest of functions unchanged: processImage, processQRData, mostrarFormQR, buscarNomeEmpresa, backToScan, confirmQR]
function processImage(imageData) {
  // [EXACT SAME as original - multiple decoders with scaling]
  // ... (keeping original robust image processing)
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

      const imageDataProcessed = ctx.getImageData(0, 0, canvas.width, canvas.height);

      // Tentar múltiplas abordagens
      let qrCode = null;

      // 1. Tentar com ZXing (mais robusto)
      try {
        const codeReader = new ZXing.BrowserQRCodeReader();
        qrCode = await codeReader.decodeFromImageData(imageDataProcessed);
        if (qrCode) {
          processQRData(qrCode.text);
          return;
        }
      } catch (e) {
        console.log('ZXing falhou:', e);
      }

      // 2. Tentar com jsQR (fallback)
      try {
        qrCode = jsQR(imageDataProcessed.data, imageDataProcessed.width, imageDataProcessed.height);
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

      showStatus('alert-danger', '<i class="bi bi-exclamation-triangle"></i> QR Code não encontrado na imagem. Tente uma imagem mais nítida.');
    };
    img.src = imageData;
  } catch (error) {
    console.error('Erro ao processar imagem:', error);
    showStatus('alert-danger', '<i class="bi bi-exclamation-triangle"></i> Erro ao processar imagem.');
  }
}

function processQRData(qrData) {
  stopScanner();
  showStatus('alert-success', '<i class="bi bi-check-circle"></i> QR Code detetado! Processando...');

  fetch('processar_qr_despesa.php?action=parsear', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'qr_data=' + encodeURIComponent(qrData)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      mostrarFormQR(data.dados, qrData);
    } else {
      showStatus('alert-danger', '<i class="bi bi-exclamation-triangle"></i> ' + data.message);
    }
  })
  .catch(error => {
    console.error('Erro:', error);
    showStatus('alert-danger', '<i class="bi bi-exclamation-triangle"></i> Erro de comunicação.');
  });
}

function mostrarFormQR(dados, qrRaw) {
  document.getElementById('scannerStep').style.display = 'none';
  document.getElementById('formStep').style.display = 'block';
  document.getElementById('backToScan').style.display = 'inline-block';
  document.getElementById('confirmQR').style.display = 'inline-block';

  document.getElementById('qr_amount').value = dados.total || '';
  document.getElementById('qr_date').value = dados.data || '';
  document.getElementById('qr_empresa').value = dados.empresa || '';
  document.getElementById('qr_category').value = dados.categoria || '';
  document.getElementById('qr_nif').value = dados.nif || '';
  document.getElementById('qr_raw').value = qrRaw;

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
    .catch(error => {
      console.error('Erro ao buscar empresa:', error);
      // Fallback NIFs
      const nifsConhecidos = {
        '500961353': 'CONTINENTE HIPERMERCADOS SA',
        '501907372': 'MCDONALD\'S PORTUGAL LDA'
      };
      if (nifsConhecidos[nif]) {
        document.getElementById('qr_empresa').value = nifsConhecidos[nif];
      }
    });
}

function backToScan() {
  document.getElementById('formStep').style.display = 'none';
  document.getElementById('scannerStep').style.display = 'block';
  document.getElementById('backToScan').style.display = 'none';
  document.getElementById('confirmQR').style.display = 'none';
  statusEl.style.display = 'none';
  currentQRData = null;
}

function confirmQR() {
  const formData = new FormData(document.getElementById('qrForm'));

  document.getElementById('confirmQR').disabled = true;
  document.getElementById('confirmQR').innerHTML = '<i class="bi bi-hourglass"></i> A registar...';

  fetch('processar_qr_despesa.php?action=criar', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
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
      bootstrap.Modal.getInstance(document.getElementById('qrModal')).hide();
      location.reload();
    } else {
      alert('Erro: ' + data.message);
      document.getElementById('confirmQR').disabled = false;
      document.getElementById('confirmQR').innerHTML = 'Registar Despesa';
    }
  })
  .catch(() => {
    alert('Erro de comunicação');
    document.getElementById('confirmQR').disabled = false;
    document.getElementById('confirmQR').innerHTML = 'Registar Despesa';
  });
}

// Cleanup
document.getElementById('qrModal').addEventListener('hidden.bs.modal', function() {
  stopScanner();
  backToScan();
});
</script>
</body>
</html>
