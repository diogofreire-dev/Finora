<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$errors = [];
$success = false;

$stmt = $pdo->prepare("SELECT id, name, limit_amount, balance FROM cards WHERE user_id = :uid AND active = 1 ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

$categories = ['Compras','Alimentação','Transporte','Saúde','Entretenimento','Educação','Casa','Outros'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $amount           = floatval($_POST['amount'] ?? 0);
    $description      = trim($_POST['description'] ?? '');
    $category         = trim($_POST['category'] ?? '');
    $card_id          = !empty($_POST['card_id']) ? intval($_POST['card_id']) : null;
    $transaction_date = trim($_POST['transaction_date'] ?? date('Y-m-d'));

    if ($amount <= 0)             { $errors[] = 'O valor deve ser maior que zero.'; }
    if (strlen($description) < 2) { $errors[] = 'A descrição é obrigatória.'; }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, card_id, amount, description, category, transaction_date, created_at) VALUES (:uid,:cid,:amt,:desc,:cat,:tdate,NOW())");
            $stmt->execute([':uid'=>$uid,':cid'=>$card_id,':amt'=>$amount,':desc'=>$description,':cat'=>$category?:null,':tdate'=>$transaction_date]);
            if ($card_id) {
                $stmt = $pdo->prepare("UPDATE cards SET balance = balance + :amt WHERE id = :cid AND user_id = :uid");
                $stmt->execute([':amt'=>$amount,':cid'=>$card_id,':uid'=>$uid]);
            }
            $pdo->commit();
            $success = true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Erro ao guardar transação.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Ler Fatura QR · FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <style>
    body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:var(--bg-primary); color:var(--text-primary); }
    .page-header { display:flex; align-items:center; gap:14px; margin-bottom:28px; padding-top:8px; }
    .page-header-icon { width:42px; height:42px; border-radius:12px; background:var(--bg-secondary); border:1px solid var(--border-color); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }

    .scan-card { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .scan-card-body { padding:20px; }
    .scan-title { font-size:14px; font-weight:600; color:var(--text-primary); margin:0 0 14px; display:flex; align-items:center; gap:8px; }
    .scan-title i { color:#3498db; }

    /* Camera area */
    #cameraWrap { position:relative; width:100%; max-width:320px; margin:0 auto; border-radius:12px; overflow:hidden; background:#000; aspect-ratio:4/3; }
    #videoEl { width:100%; height:100%; object-fit:cover; display:block; }
    #canvasEl { display:none; }
    .scan-overlay { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; pointer-events:none; }
    .scan-frame { width:60%; aspect-ratio:1; border:2px solid rgba(52,152,219,.8); border-radius:12px; box-shadow:0 0 0 999px rgba(0,0,0,.35); position:relative; }
    .scan-frame::before,.scan-frame::after { content:''; position:absolute; width:20px; height:20px; border-color:#3498db; border-style:solid; }
    .scan-frame::before { top:-2px; left:-2px; border-width:3px 0 0 3px; border-radius:4px 0 0 0; }
    .scan-frame::after  { bottom:-2px; right:-2px; border-width:0 3px 3px 0; border-radius:0 0 4px 0; }
    #scanStatus { text-align:center; font-size:13px; color:var(--text-secondary); margin-top:12px; min-height:20px; }

    /* Tabs */
    .tab-btns { display:flex; gap:8px; margin-bottom:18px; }
    .tab-btn { flex:1; padding:9px; border-radius:9px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-secondary); font-size:13px; font-weight:500; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; transition:all .15s; }
    .tab-btn.active { background:#3498db; color:#fff; border-color:#3498db; }

    /* Upload zone */

    /* Result / form */
    .result-section { display:none; }
    .result-section.visible { display:block; }
    .parsed-badge { display:inline-flex; align-items:center; gap:6px; background:rgba(34,197,94,.1); border:1px solid rgba(34,197,94,.3); color:#16a34a; border-radius:8px; padding:6px 12px; font-size:12px; font-weight:600; margin-bottom:16px; }

    .form-group { margin-bottom:14px; }
    .form-label { font-size:12px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.04em; margin-bottom:5px; display:block; }
    .form-control, .form-select { width:100%; padding:9px 12px; border-radius:9px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); font-size:14px; box-sizing:border-box; }
    .form-control:focus, .form-select:focus { outline:none; border-color:#3498db; }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }

    .btn-primary { background:#3498db; color:#fff; border:none; border-radius:10px; padding:11px 22px; font-size:14px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:7px; }
    .btn-secondary { background:var(--bg-primary); color:var(--text-primary); border:1px solid var(--border-color); border-radius:10px; padding:11px 22px; font-size:14px; font-weight:500; cursor:pointer; display:inline-flex; align-items:center; gap:7px; }
    .btn-row { display:flex; gap:10px; margin-top:20px; flex-wrap:wrap; }

    .alert { border-radius:10px; padding:12px 16px; font-size:13px; margin-bottom:14px; border:1px solid transparent; }
    .alert-success { background:rgba(34,197,94,.1); border-color:rgba(34,197,94,.3); color:#16a34a; }
    .alert-danger  { background:rgba(231,76,60,.1);  border-color:rgba(231,76,60,.3);  color:#e74c3c; }

    .nif-lookup-row { display:flex; align-items:center; gap:8px; }
    .nif-lookup-row .form-control { flex:1; }
    .nif-badge { font-size:11px; color:var(--text-secondary); margin-top:4px; }

    @media(max-width:600px) { .form-row { grid-template-columns:1fr; } }

    /* Layout wrapper */
    .scan-wrapper { max-width: 780px; margin: 0 auto; }

    /* Upload zone — clean dashed border */
    .upload-zone {
      border: 2px dashed var(--border-color);
      border-radius: 12px;
      padding: 36px 20px;
      text-align: center;
      cursor: pointer;
      transition: border-color .2s, color .2s;
      color: var(--text-secondary);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
    }
    .upload-zone:hover { border-color: #3498db; color: #3498db; }
    .upload-zone i { font-size: 34px; }
  </style>
</head>
<body>
<div class="app-layout">
<?php
$activePage   = 'scan_fatura';
$sidebarPrefix = '../';
include __DIR__ . '/../components/sidebar.php';
?>
<main class="main-content">
  <div class="page-content">
  <div class="scan-wrapper">

    <div class="page-header">
      <div class="page-header-icon"><i class="bi bi-qr-code-scan"></i></div>
      <div>
        <h1 style="font-size:20px;font-weight:700;margin:0">Ler Fatura QR</h1>
        <p style="font-size:13px;color:var(--text-secondary);margin:2px 0 0">Lê o QR code de uma fatura portuguesa e cria a transação automaticamente</p>
      </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle"></i> Transação criada com sucesso! <a href="transactions.php" style="color:inherit;font-weight:600">Ver transações →</a></div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?=htmlspecialchars($e)?></div>
    <?php endforeach; ?>

    <!-- Scanner -->
    <div class="scan-card">
      <div class="scan-card-body">
        <p class="scan-title"><i class="bi bi-camera"></i> Ler QR Code</p>

        <div class="tab-btns">
          <button class="tab-btn active" id="tabCamera" onclick="switchTab('camera')"><i class="bi bi-camera-fill"></i> Câmara</button>
          <button class="tab-btn" id="tabUpload" onclick="switchTab('upload')"><i class="bi bi-image"></i> Imagem</button>
        </div>

        <!-- Camera tab -->
        <div id="panelCamera">
          <div id="cameraWrap">
            <video id="videoEl" autoplay playsinline muted></video>
            <canvas id="canvasEl"></canvas>
            <div class="scan-overlay"><div class="scan-frame"></div></div>
          </div>
          <div id="scanStatus">A iniciar câmara…</div>
          <div style="text-align:center;margin-top:16px">
            <button class="btn-secondary" id="btnToggleCam" onclick="toggleCamera()" style="font-size:13px;padding:8px 18px">
              <i class="bi bi-camera-video-off"></i> Parar câmara
            </button>
          </div>
        </div>

        <!-- Upload tab -->
        <div id="panelUpload" style="display:none">
          <label class="upload-zone" id="uploadZone" for="fileInput">
            <i class="bi bi-cloud-upload"></i>
            <strong>Clica para escolher uma imagem</strong>
            <span style="font-size:12px;color:var(--text-secondary)">ou arrasta e larga aqui</span>
          </label>
          <input type="file" id="fileInput" accept="image/*" style="display:none" onchange="handleFile(this.files[0])">
        </div>

      </div>
    </div>

    <!-- Result form -->
    <div class="scan-card result-section" id="resultSection">
      <div class="scan-card-body">
        <div class="parsed-badge"><i class="bi bi-check-circle-fill"></i> QR Code lido com sucesso</div>

        <form method="post">
          <input type="hidden" name="action" value="save">

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Valor Total (€)</label>
              <input type="number" name="amount" id="fAmount" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
            </div>
            <div class="form-group">
              <label class="form-label">Data</label>
              <input type="date" name="transaction_date" id="fDate" class="form-control" required value="<?=date('Y-m-d')?>">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Descrição (Estabelecimento)</label>
            <div style="position:relative">
              <input type="text" name="description" id="fDesc" class="form-control" required placeholder="A consultar…">
              <div id="nifInfo" class="nif-badge"></div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Categoria</label>
              <select name="category" id="fCat" class="form-select">
                <option value="">— Escolher —</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?=htmlspecialchars($c)?>"><?=htmlspecialchars($c)?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Cartão</label>
              <select name="card_id" class="form-select">
                <option value="">— Sem cartão —</option>
                <?php foreach ($cards as $c): ?>
                <option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Raw QR info (collapsible) -->
          <details style="margin-top:4px">
            <summary style="font-size:12px;color:var(--text-secondary);cursor:pointer;user-select:none">Dados brutos do QR code</summary>
            <pre id="rawData" style="font-size:11px;background:var(--bg-primary);border-radius:8px;padding:10px;margin-top:8px;overflow-x:auto;color:var(--text-secondary);white-space:pre-wrap;word-break:break-all"></pre>
          </details>

          <div class="btn-row">
            <button type="submit" class="btn-primary"><i class="bi bi-plus-circle"></i> Guardar Transação</button>
            <button type="button" class="btn-secondary" onclick="resetScan()"><i class="bi bi-arrow-counterclockwise"></i> Ler outro QR</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Instructions -->
    <div class="scan-card" id="instructionsCard">
      <div class="scan-card-body">
        <p class="scan-title"><i class="bi bi-info-circle"></i> Como funciona</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px">
          <?php foreach ([
            ['bi-qr-code','Aponta a câmara','Aponta para o QR code na fatura ou faz upload de uma foto'],
            ['bi-cpu','Parse automático','Extrai automaticamente o valor, data e NIF do emitente'],
            ['bi-building','Nome da empresa','Consulta o nome do estabelecimento pelo NIF automaticamente'],
            ['bi-check2-circle','Confirma e guarda','Revê os dados, escolhe a categoria e o cartão, e guarda'],
          ] as [$icon,$title,$desc]): ?>
          <div style="display:flex;gap:10px;align-items:flex-start">
            <div style="width:34px;height:34px;border-radius:9px;background:rgba(52,152,219,.1);border:1px solid rgba(52,152,219,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#3498db;font-size:16px">
              <i class="bi <?=$icon?>"></i>
            </div>
            <div>
              <div style="font-size:13px;font-weight:600;color:var(--text-primary);margin-bottom:2px"><?=$title?></div>
              <div style="font-size:12px;color:var(--text-secondary);line-height:1.4"><?=$desc?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div><!-- /scan-wrapper -->
  </div><!-- /page-content -->
</main>
</div><!-- /app-layout -->

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
// ── State ──────────────────────────────────────────────────────────────────
let stream = null;
let scanning = false;
let scanInterval = null;
let currentTab = 'camera';

// ── Tab switch ─────────────────────────────────────────────────────────────
function switchTab(tab) {
  currentTab = tab;
  document.getElementById('tabCamera').classList.toggle('active', tab === 'camera');
  document.getElementById('tabUpload').classList.toggle('active', tab === 'upload');
  document.getElementById('panelCamera').style.display = tab === 'camera' ? '' : 'none';
  document.getElementById('panelUpload').style.display  = tab === 'upload' ? '' : 'none';
  if (tab === 'camera') startCamera();
  else stopCamera();
}

// ── Camera ─────────────────────────────────────────────────────────────────
async function startCamera() {
  if (stream) return;
  const status = document.getElementById('scanStatus');
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 960 } }
    });
    const video = document.getElementById('videoEl');
    video.srcObject = stream;
    await video.play();
    scanning = true;
    status.textContent = 'Aponta para o QR code…';
    document.getElementById('btnToggleCam').innerHTML = '<i class="bi bi-camera-video-off"></i> Parar câmara';
    scanInterval = setInterval(scanFrame, 200);
  } catch (e) {
    status.textContent = 'Câmara não disponível. Usa o upload de imagem.';
  }
}

function stopCamera() {
  scanning = false;
  clearInterval(scanInterval);
  if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
  document.getElementById('scanStatus').textContent = 'Câmara parada.';
  document.getElementById('btnToggleCam').innerHTML = '<i class="bi bi-camera-video"></i> Iniciar câmara';
}

function toggleCamera() {
  if (stream) stopCamera(); else startCamera();
}

function scanFrame() {
  if (!scanning) return;
  const video  = document.getElementById('videoEl');
  const canvas = document.getElementById('canvasEl');
  if (video.readyState !== video.HAVE_ENOUGH_DATA) return;
  canvas.width  = video.videoWidth;
  canvas.height = video.videoHeight;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0);
  const img  = ctx.getImageData(0, 0, canvas.width, canvas.height);
  const code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
  if (code) {
    stopCamera();
    processQR(code.data);
  }
}

// ── Upload ─────────────────────────────────────────────────────────────────
const uploadZone = document.getElementById('uploadZone');
uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.style.borderColor='#3498db'; });
uploadZone.addEventListener('dragleave', () => { uploadZone.style.borderColor=''; });
uploadZone.addEventListener('drop', e => { e.preventDefault(); uploadZone.style.borderColor=''; if(e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]); });

function handleFile(file) {
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const img = new Image();
    img.onload = () => {
      const canvas = document.getElementById('canvasEl');
      canvas.width  = img.width;
      canvas.height = img.height;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(img, 0, 0);
      const data = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(data.data, data.width, data.height, { inversionAttempts: 'attemptBoth' });
      if (code) {
        processQR(code.data);
      } else {
        alert('Não foi possível ler um QR code nesta imagem. Tenta com uma foto mais nítida.');
      }
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}

// ── Parse QR AT format ─────────────────────────────────────────────────────
// Formato: A:NIF*B:NIF_comp*C:pais*D:tipo*E:estado*F:YYYYMMDD*G:num_doc*H:atcud*...*O:total*...
function parseATQR(raw) {
  const fields = {};
  raw.split('*').forEach(part => {
    const colon = part.indexOf(':');
    if (colon > 0) {
      const key = part.substring(0, colon);
      const val = part.substring(colon + 1);
      fields[key] = val;
    }
  });
  return fields;
}

function atDateToISO(dateStr) {
  // YYYYMMDD → YYYY-MM-DD
  if (!dateStr || dateStr.length !== 8) return new Date().toISOString().slice(0,10);
  return dateStr.slice(0,4) + '-' + dateStr.slice(4,6) + '-' + dateStr.slice(6,8);
}

function processQR(raw) {
  document.getElementById('rawData').textContent = raw;

  const fields = parseATQR(raw);

  // Tentar ler valor total (O = total c/ IVA, N = total IVA, I = ilíquido)
  const total = parseFloat(fields['O'] || fields['N'] || fields['I1'] || 0);
  const date  = atDateToISO(fields['F'] || '');
  const nif   = fields['A'] || '';

  // Preencher form
  if (total > 0) document.getElementById('fAmount').value = total.toFixed(2);
  document.getElementById('fDate').value = date;
  document.getElementById('fDesc').value = nif ? 'A consultar NIF ' + nif + '…' : '';

  // Mostrar resultados
  document.getElementById('resultSection').classList.add('visible');
  document.getElementById('instructionsCard').style.display = 'none';
  window.scrollTo({ top: document.getElementById('resultSection').offsetTop - 20, behavior: 'smooth' });

  // Lookup NIF → nome empresa
  if (nif) {
    lookupNIF(nif);
  } else {
    document.getElementById('fDesc').value = '';
    document.getElementById('fDesc').placeholder = 'Nome do estabelecimento';
  }
}

// ── NIF Lookup ─────────────────────────────────────────────────────────────
function lookupNIF(nif) {
  const descInput = document.getElementById('fDesc');
  const nifInfo   = document.getElementById('nifInfo');
  nifInfo.textContent = '⏳ A consultar NIF ' + nif + '…';
  descInput.value = '';
  descInput.placeholder = 'A consultar…';

  fetch('../helpers/nif_lookup.php?nif=' + encodeURIComponent(nif))
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        descInput.value = data.name;
        nifInfo.innerHTML = '<i class="bi bi-check-circle" style="color:#16a34a"></i> NIF ' + nif + ' — ' + (data.source === 'cache' ? 'cache local' : 'nif.pt');
        // Sugerir categoria com base no nome
        suggestCategory(data.name);
      } else {
        descInput.value = '';
        descInput.placeholder = 'Nome do estabelecimento';
        nifInfo.innerHTML = '<i class="bi bi-exclamation-circle" style="color:#f59e0b"></i> NIF ' + nif + ' não encontrado — preenche manualmente';
      }
    })
    .catch(() => {
      descInput.value = '';
      descInput.placeholder = 'Nome do estabelecimento';
      nifInfo.innerHTML = '<i class="bi bi-wifi-off" style="color:#e74c3c"></i> Erro de rede — preenche o nome manualmente';
    });
}

// Sugestão automática de categoria com base no nome da empresa
function suggestCategory(name) {
  const n = name.toLowerCase();
  const map = [
    [['pingo doce','continente','lidl','aldi','minipreço','mercadona','intermarché','el corte','jumbo','pão de açúcar','supermercado'], 'Alimentação'],
    [['mc','mcdonald','kfc','burger','pizza','sushi','restaurante','café','pastelaria','snack','tasca','churrasqueira'], 'Alimentação'],
    [['galp','bp','repsol','cepsa','combustível','posto'], 'Transporte'],
    [['uber','bolt','cp ','comboio','metro','rede expressos','flixbus','transporte'], 'Transporte'],
    [['farmácia','farmacia','wells','clínica','clinica','médico','dentista','hospital','saúde'], 'Saúde'],
    [['worten','fnac','apple','samsung','mediamarkt','electro','informática'], 'Compras'],
    [['cinema','nos ','meo ','disney','netflix','spotify','games','playstation','xbox'], 'Entretenimento'],
    [['escola','universidade','curso','udemy','coursera','educação'], 'Educação'],
    [['água','luz','gás','ren ','edp','epal','seguro','condomínio','renda'], 'Casa'],
  ];
  const catSelect = document.getElementById('fCat');
  for (const [keywords, cat] of map) {
    if (keywords.some(k => n.includes(k))) {
      for (const opt of catSelect.options) {
        if (opt.value === cat) { catSelect.value = cat; break; }
      }
      break;
    }
  }
}

// ── Reset ──────────────────────────────────────────────────────────────────
function resetScan() {
  document.getElementById('resultSection').classList.remove('visible');
  document.getElementById('instructionsCard').style.display = '';
  document.getElementById('rawData').textContent = '';
  document.getElementById('nifInfo').textContent = '';
  document.getElementById('fileInput').value = '';
  if (currentTab === 'camera') startCamera();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Init ───────────────────────────────────────────────────────────────────
startCamera();
</script>
</body>
</html>