<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$message     = '';
$messageType = 'info';

$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid AND active = 1 ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount           = floatval($_POST['total'] ?? 0);
    $description      = trim($_POST['empresa'] ?? 'Fatura');
    $category         = 'Fatura';
    $card_id          = !empty($_POST['card_id']) ? intval($_POST['card_id']) : null;
    $transaction_date = trim($_POST['data'] ?? date('Y-m-d'));
    $nif              = trim($_POST['nif'] ?? '');

    if ($amount <= 0) {
        $message = 'Valor inválido. Coloque um valor maior que zero.';
        $messageType = 'danger';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transaction_date)) {
        $message = 'Data inválida. Use o formato YYYY-MM-DD.';
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, card_id, amount, description, category, transaction_date, created_at) VALUES (:uid, :cid, :amt, :desc, :cat, :tdate, NOW())");
            $stmt->execute([':uid' => $uid, ':cid' => $card_id, ':amt' => $amount, ':desc' => $description, ':cat' => $category, ':tdate' => $transaction_date]);

            if ($card_id) {
                $stmt = $pdo->prepare("UPDATE cards SET balance = balance + :amt WHERE id = :cid AND user_id = :uid");
                $stmt->execute([':amt' => $amount, ':cid' => $card_id, ':uid' => $uid]);
            }

            if (preg_match('/^\d{9}$/', $nif) && !empty($description)) {
                $id_cat = !empty($_POST['id_categoria']) ? intval($_POST['id_categoria']) : null;
                $stmt = $pdo->prepare("INSERT INTO nif_empresas (nif, nome_empresa, id_categoria) VALUES (:nif, :nome, :cat) ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa), id_categoria = VALUES(id_categoria)");
                $stmt->execute([':nif' => $nif, ':nome' => $description, ':cat' => $id_cat]);
            }

            $pdo->commit();
            $message = 'Fatura guardada com sucesso.';
            $messageType = 'success';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'Erro ao guardar fatura. Tente novamente.';
            $messageType = 'danger';
        }
    }
}
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Ler Fatura · FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <style>
    body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:var(--bg-primary); color:var(--text-primary); }

    /* ── Page header ── */
    .page-header { display:flex; align-items:center; gap:14px; margin-bottom:28px; padding-top:8px; }
    .page-header-icon { width:42px; height:42px; border-radius:12px; background:var(--bg-secondary); border:1px solid var(--border-color); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }

    /* ── Cards ── */
    .scan-card { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; overflow:hidden; margin-bottom:16px; }
    .scan-card-body { padding:20px; }
    .scan-title { font-size:14px; font-weight:600; color:var(--text-primary); margin:0 0 14px; display:flex; align-items:center; gap:8px; }
    .scan-title i { color:#3498db; }

    /* ── Tabs ── */
    .tab-btns { display:flex; gap:8px; margin-bottom:18px; }
    .tab-btn { flex:1; padding:9px; border-radius:9px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-secondary); font-size:13px; font-weight:500; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; transition:all .15s; }
    .tab-btn.active { background:#3498db; color:#fff; border-color:#3498db; }

    /* ── Camera ── */
    #cameraWrap { position:relative; width:100%; max-width:320px; margin:0 auto; border-radius:12px; overflow:hidden; background:#000; aspect-ratio:4/3; }
    #videoEl { width:100%; height:100%; object-fit:cover; display:block; }
    #canvasEl { display:none; }
    .scan-overlay { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; pointer-events:none; }
    .scan-frame { width:60%; aspect-ratio:1; border:2px solid rgba(52,152,219,.8); border-radius:12px; box-shadow:0 0 0 999px rgba(0,0,0,.35); position:relative; }
    .scan-frame::before,.scan-frame::after { content:''; position:absolute; width:20px; height:20px; border-color:#3498db; border-style:solid; }
    .scan-frame::before { top:-2px; left:-2px; border-width:3px 0 0 3px; border-radius:4px 0 0 0; }
    .scan-frame::after  { bottom:-2px; right:-2px; border-width:0 3px 3px 0; border-radius:0 0 4px 0; }
    #scanStatus { text-align:center; font-size:13px; color:var(--text-secondary); margin-top:12px; min-height:20px; }

    /* ── Upload zone ── */
    .upload-zone { border:2px dashed var(--border-color); border-radius:12px; padding:36px 20px; text-align:center; cursor:pointer; transition:border-color .2s,color .2s; color:var(--text-secondary); display:flex; flex-direction:column; align-items:center; gap:6px; }
    .upload-zone:hover { border-color:#3498db; color:#3498db; }
    .upload-zone i { font-size:34px; }

    /* ── OCR progress ── */
    .progress-wrap { background:var(--bg-primary); border-radius:8px; height:6px; overflow:hidden; margin-top:8px; }
    .progress-fill { height:100%; background:linear-gradient(90deg,#3498db,#2980b9); border-radius:8px; transition:width .3s; width:0%; }

    /* ── Result / form ── */
    .result-section { display:none; }
    .result-section.visible { display:block; }
    .parsed-badge { display:inline-flex; align-items:center; gap:6px; background:rgba(34,197,94,.1); border:1px solid rgba(34,197,94,.3); color:#16a34a; border-radius:8px; padding:6px 12px; font-size:12px; font-weight:600; margin-bottom:16px; }

    .form-group { margin-bottom:14px; }
    .form-label { font-size:12px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.04em; margin-bottom:5px; display:block; }
    .form-control, .form-select { width:100%; padding:9px 12px; border-radius:9px; border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary); font-size:14px; box-sizing:border-box; }
    .form-control:focus, .form-select:focus { outline:none; border-color:#3498db; }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }

    .btn-primary   { background:#3498db; color:#fff; border:none; border-radius:10px; padding:11px 22px; font-size:14px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:7px; }
    .btn-secondary { background:var(--bg-primary); color:var(--text-primary); border:1px solid var(--border-color); border-radius:10px; padding:11px 22px; font-size:14px; font-weight:500; cursor:pointer; display:inline-flex; align-items:center; gap:7px; }
    .btn-success   { background:#22c55e; color:#fff; border:none; border-radius:10px; padding:11px 22px; font-size:14px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:7px; }
    .btn-row { display:flex; gap:10px; margin-top:20px; flex-wrap:wrap; }

    .alert { border-radius:10px; padding:12px 16px; font-size:13px; margin-bottom:14px; border:1px solid transparent; }
    .alert-success { background:rgba(34,197,94,.1); border-color:rgba(34,197,94,.3); color:#16a34a; }
    .alert-danger  { background:rgba(231,76,60,.1);  border-color:rgba(231,76,60,.3);  color:#e74c3c; }

    .nif-badge { font-size:11px; color:var(--text-secondary); margin-top:4px; min-height:16px; }

    /* ── Source badge ── */
    .source-badge { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:600; padding:2px 7px; border-radius:5px; margin-left:6px; }
    .source-cache { background:rgba(99,102,241,.12); color:#6366f1; }
    .source-local { background:rgba(34,197,94,.12); color:#16a34a; }
    .source-nifpt { background:rgba(52,152,219,.12); color:#3498db; }
    .source-vies  { background:rgba(245,158,11,.12); color:#d97706; }

    /* ── OCR text box ── */
    #ocrTextoBox { font-size:10px; font-family:monospace; max-height:160px; overflow:auto; background:var(--bg-primary); color:var(--text-secondary); padding:10px; border-radius:8px; margin-top:8px; white-space:pre-wrap; display:none; border:1px solid var(--border-color); }

    /* ── Instructions grid ── */
    .how-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:14px; }
    .how-item { display:flex; gap:10px; align-items:flex-start; }
    .how-icon { width:34px; height:34px; border-radius:9px; background:rgba(52,152,219,.1); border:1px solid rgba(52,152,219,.2); display:flex; align-items:center; justify-content:center; flex-shrink:0; color:#3498db; font-size:16px; }

    .scan-wrapper { max-width:780px; margin:0 auto; }
    @media(max-width:600px) { .form-row { grid-template-columns:1fr; } }
  </style>

  <!-- QR câmara (jsQR — rápido) · QR ficheiros (html5-qrcode) · OCR (tesseract.js) · PDF (pdf.js) -->
  <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <script src="https://unpkg.com/tesseract.js@4.0.2/dist/tesseract.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script>
    pdfjsLib.GlobalWorkerOptions.workerSrc =
      'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
  </script>
</head>
<body>
<!-- Container oculto para scanFile() — nunca misturar com #reader da câmara -->
<div id="qrFileContainer" style="display:none;width:1px;height:1px;overflow:hidden;position:absolute;"></div>

<div class="app-layout">
<?php
$activePage    = 'scan_fatura';
$sidebarPrefix = '../';
include __DIR__ . '/../components/sidebar.php';
?>
<main class="main-content">
  <div class="page-content">
  <div class="scan-wrapper">

    <!-- Page header -->
    <div class="page-header">
      <div class="page-header-icon"><i class="bi bi-qr-code-scan"></i></div>
      <div>
        <h1 style="font-size:20px;font-weight:700;margin:0">Ler Fatura</h1>
        <p style="font-size:13px;color:var(--text-secondary);margin:2px 0 0">QR code por câmara ou imagem · OCR de PDF e imagens de faturas</p>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?=$messageType?>">
      <i class="bi bi-<?=$messageType==='success'?'check-circle':'exclamation-circle'?>"></i>
      <?=htmlspecialchars($message)?>
      <?php if ($messageType==='success'): ?>
        <a href="transactions.php" style="color:inherit;font-weight:600;margin-left:6px">Ver transações →</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── SCANNER CARD ─────────────────────────────────────────── -->
    <div class="scan-card">
      <div class="scan-card-body">
        <p class="scan-title"><i class="bi bi-camera"></i> Ler Fatura</p>

        <!-- Tabs -->
        <div class="tab-btns">
          <button class="tab-btn active" id="tabCamera" onclick="switchTab('camera')">
            <i class="bi bi-camera-fill"></i> Câmara QR
          </button>
          <button class="tab-btn" id="tabUpload" onclick="switchTab('upload')">
            <i class="bi bi-file-earmark-arrow-up"></i> PDF / Imagem
          </button>
        </div>

        <!-- ── Tab Câmara ── -->
        <div id="panelCamera">
          <div id="cameraWrap" style="position:relative;width:100%;max-width:320px;margin:0 auto;border-radius:12px;overflow:hidden;background:#000;aspect-ratio:4/3;">
            <video id="videoEl" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;display:block;"></video>
            <canvas id="canvasEl" style="display:none;"></canvas>
            <!-- viewfinder overlay -->
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
              <div style="width:60%;aspect-ratio:1;border:2px solid rgba(52,152,219,.8);border-radius:12px;box-shadow:0 0 0 999px rgba(0,0,0,.35);position:relative;">
                <span style="position:absolute;top:-2px;left:-2px;width:20px;height:20px;border-top:3px solid #3498db;border-left:3px solid #3498db;border-radius:4px 0 0 0;"></span>
                <span style="position:absolute;bottom:-2px;right:-2px;width:20px;height:20px;border-bottom:3px solid #3498db;border-right:3px solid #3498db;border-radius:0 0 4px 0;"></span>
              </div>
            </div>
          </div>
          <div id="scanStatus" style="text-align:center;font-size:13px;color:var(--text-secondary);margin-top:12px;min-height:20px;">Câmara parada.</div>
          <div style="text-align:center;margin-top:14px;display:flex;justify-content:center;gap:10px;flex-wrap:wrap">
            <button class="btn-primary" id="btnIniciarQR" style="font-size:13px;padding:8px 18px">
              <i class="bi bi-camera"></i> Iniciar câmara
            </button>
            <button class="btn-secondary" id="btnPararQR" style="display:none;font-size:13px;padding:8px 18px">
              <i class="bi bi-stop-circle"></i> Parar
            </button>
          </div>
        </div>

        <!-- ── Tab PDF / Imagem ── -->
        <div id="panelUpload" style="display:none">
          <label class="upload-zone" for="fileInput">
            <i class="bi bi-file-earmark-arrow-up"></i>
            <strong>Clica para escolher um ficheiro</strong>
            <span style="font-size:12px">PDF, PNG, JPG — tenta QR primeiro, depois OCR</span>
          </label>
          <input type="file" id="fileInput" accept="image/*,.pdf" style="display:none" onchange="uploadImagem()">

          <!-- OCR progress -->
          <div id="ocrProgressWrap" style="display:none;margin-top:14px">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px">
              <span style="font-size:12px;font-weight:600;color:var(--text-primary)"><i class="bi bi-cpu" style="color:#3498db"></i> A processar…</span>
              <span id="ocrPct" style="font-size:12px;color:var(--text-secondary)">0%</span>
            </div>
            <div class="progress-wrap"><div class="progress-fill" id="progressBar"></div></div>
          </div>
          <div id="ocrStatus" style="font-size:12px;color:var(--text-secondary);margin-top:8px;min-height:18px"></div>
          <a href="#" id="btnVerTexto" style="display:none;font-size:11px;color:var(--text-secondary)"
             onclick="document.getElementById('ocrTextoBox').style.display='block';this.style.display='none';return false">
            Ver texto extraído pelo OCR ▼
          </a>
          <div id="ocrTextoBox"></div>
        </div>

      </div>
    </div>

    <!-- ── RESULT FORM CARD ──────────────────────────────────────── -->
    <div class="scan-card result-section" id="resultSection">
      <div class="scan-card-body">
        <div class="parsed-badge" id="resultBadge">
          <i class="bi bi-check-circle-fill"></i> Dados extraídos — confirma e guarda
        </div>

        <form method="post" id="faturaForm">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Total (€)</label>
              <input type="text" name="total" id="fTotal" class="form-control" placeholder="0.00" required>
            </div>
            <div class="form-group">
              <label class="form-label">Data</label>
              <input type="date" name="data" id="fData" class="form-control" required value="<?=date('Y-m-d')?>">
            </div>
          </div>

          <input type="hidden" name="nif" id="fNif">

          <div class="form-group">
            <label class="form-label">Empresa</label>
            <input type="text" name="empresa" id="fEmpresa" class="form-control" placeholder="Nome do estabelecimento" required>
            <div id="nifInfo" class="nif-badge"></div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Categoria</label>
              <select name="id_categoria" id="fCategoria" class="form-select">
                <option value="">— Escolher —</option>
                <option value="1">Compras</option>
                <option value="2">Alimentação</option>
                <option value="3">Transporte</option>
                <option value="4">Saúde</option>
                <option value="5">Entretenimento</option>
                <option value="6">Educação</option>
                <option value="7">Casa</option>
                <option value="8">Outros</option>
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

          <div class="btn-row">
            <button type="submit" class="btn-success"><i class="bi bi-save"></i> Guardar Fatura</button>
            <button type="button" class="btn-secondary" onclick="resetForm()">
              <i class="bi bi-arrow-counterclockwise"></i> Limpar
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ── HOW IT WORKS ───────────────────────────────────────────── -->
    <div class="scan-card" id="instructionsCard">
      <div class="scan-card-body">
        <p class="scan-title"><i class="bi bi-info-circle"></i> Como funciona</p>
        <div class="how-grid">
          <?php foreach ([
            ['bi-camera',           'Câmara QR',       'Aponta a câmara ao QR code da fatura — lê instantaneamente o valor, data e NIF'],
            ['bi-file-earmark-pdf', 'PDF / Imagem',    'Carrega um PDF ou foto de fatura — tenta ler o QR, se não existir usa OCR automático'],
            ['bi-building',         'Lookup NIF',      'O NIF da empresa é consultado automaticamente para preencher o nome e sugerir a categoria'],
            ['bi-check2-circle',    'Confirma e guarda','Revê os dados extraídos, escolhe o cartão e guarda a transação'],
          ] as [$icon,$title,$desc]): ?>
          <div class="how-item">
            <div class="how-icon"><i class="bi <?=$icon?>"></i></div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
'use strict';

// ═══════════════════════════════════════════════════════════════════════════
// TABS
// ═══════════════════════════════════════════════════════════════════════════
function switchTab(tab) {
  document.getElementById('tabCamera').classList.toggle('active', tab === 'camera');
  document.getElementById('tabUpload').classList.toggle('active', tab === 'upload');
  document.getElementById('panelCamera').style.display = tab === 'camera' ? '' : 'none';
  document.getElementById('panelUpload').style.display = tab === 'upload' ? '' : 'none';
  if (tab !== 'camera') stopQR(); // para câmara ao sair do tab
}

// ═══════════════════════════════════════════════════════════════════════════
// CÂMARA AO VIVO — jsQR (muito mais rápido que html5-qrcode)
// Processa frames do canvas a cada 150ms sem overhead de biblioteca
// ═══════════════════════════════════════════════════════════════════════════
let cameraStream   = null;
let cameraInterval = null;
let cameraScanning = false;

function stopQR() {
  cameraScanning = false;
  clearInterval(cameraInterval);
  if (cameraStream) { cameraStream.getTracks().forEach(t => t.stop()); cameraStream = null; }
  document.getElementById('btnIniciarQR').style.display = 'inline-flex';
  document.getElementById('btnPararQR').style.display   = 'none';
  document.getElementById('scanStatus').textContent     = 'Câmara parada.';
}

async function startQR() {
  if (cameraStream) stopQR();
  document.getElementById('btnIniciarQR').style.display = 'none';
  document.getElementById('btnPararQR').style.display   = 'inline-flex';
  document.getElementById('scanStatus').textContent     = 'A iniciar câmara…';

  try {
    cameraStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 960 } }
    });
    const video = document.getElementById('videoEl');
    video.srcObject = cameraStream;
    await video.play();
    cameraScanning = true;
    document.getElementById('scanStatus').textContent = 'Aponta para o QR code…';

    // jsQR: processa um frame a cada 150ms — muito mais rápido que html5-qrcode
    cameraInterval = setInterval(() => {
      if (!cameraScanning) return;
      const video  = document.getElementById('videoEl');
      const canvas = document.getElementById('canvasEl');
      if (video.readyState !== video.HAVE_ENOUGH_DATA) return;
      canvas.width  = video.videoWidth;
      canvas.height = video.videoHeight;
      const ctx  = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0);
      const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const code    = jsQR(imgData.data, imgData.width, imgData.height, { inversionAttempts: 'dontInvert' });
      if (code && code.data) {
        stopQR();
        document.getElementById('scanStatus').textContent = '✓ QR lido!';
        const dados = parseQR(code.data);
        preencherForm(dados);
        if (dados.nif) fetchCompanyByNif(dados.nif);
        mostrarResultado('QR code lido com sucesso');
      }
    }, 150);

  } catch (err) {
    console.warn('Câmara indisponível:', err);
    document.getElementById('scanStatus').textContent = 'Câmara não disponível — usa o separador PDF / Imagem.';
    stopQR();
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// PARSE QR Portugal AT
// Formato: A:NIF*B:NIF_comp*C:PT*D:tipo*E:estado*F:YYYYMMDD*G:num*...*O:total
// ═══════════════════════════════════════════════════════════════════════════
function parseQR(qr) {
  const fields = {};
  const sep = qr.includes('*') ? '*' : '\n';
  qr.split(sep).forEach(seg => {
    seg = seg.trim();
    const i = seg.indexOf(':');
    if (i > 0) fields[seg.substring(0, i).trim()] = seg.substring(i + 1).trim();
  });

  let data = '';
  if (fields['F'] && /^\d{8}$/.test(fields['F'])) {
    const d = fields['F'];
    data = `${d.slice(0,4)}-${d.slice(4,6)}-${d.slice(6,8)}`;
  }

  const totalRaw = (fields['O'] || fields['N'] || fields['I1'] || '').replace(',', '.');

  return {
    nif:    fields['A'] || '',
    empresa: '',
    data,
    numero: fields['G'] || '',
    total:  totalRaw
  };
}

// ═══════════════════════════════════════════════════════════════════════════
// scanFile num container oculto SEPARADO do #reader
// NÃO chamar scanner.stop() — scanFile não usa start()
// ═══════════════════════════════════════════════════════════════════════════
async function tentarLerQR(blob) {
  const container = document.getElementById('qrFileContainer');
  container.innerHTML = '';
  const scanner = new Html5Qrcode('qrFileContainer');
  try {
    return await scanner.scanFile(blob, false);
  } finally {
    try { scanner.clear(); } catch(_) {}
    container.innerHTML = '';
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// OCR helpers
// ═══════════════════════════════════════════════════════════════════════════
function extractTotal(text) {
  const padroes = [
    /(?:total\s+a\s+pagar|valor\s+a\s+pagar|total\s+com\s+iva|total\s+c\/iva)\s*[:\-]?\s*€?\s*(\d{1,6}[.,]\d{2})/i,
    /total\s*[:\-]\s*€?\s*(\d{1,6}[.,]\d{2})/i,
    /total\s*€?\s*(\d{1,6}[.,]\d{2})/i,
    /€\s*(\d{1,6}[.,]\d{2})(?!\d)/,
    /(?<!\d)(\d{1,6}[.,]\d{2})\s*€/,
    /EUR\s*(\d{1,6}[.,]\d{2})/i
  ];
  for (const p of padroes) {
    const m = text.match(p);
    if (m) return m[1];
  }
  // fallback: maior valor numérico com decimais
  const todos = [...text.matchAll(/(\d{1,6}[.,]\d{2})/g)]
    .map(m => ({ raw: m[1], val: parseFloat(m[1].replace(',', '.')) }))
    .filter(x => x.val > 0.5);
  if (!todos.length) return null;
  todos.sort((a, b) => b.val - a.val);
  return todos[0].raw;
}

function extractNif(text) {
  const pt = text.match(/PT\s*(\d{9})/i);
  if (pt) return pt[1];
  // remove espaços que o OCR insere entre dígitos
  let cleaned = text.replace(/(\d)\s+(\d)/g, '$1$2').replace(/(\d)\s+(\d)/g, '$1$2');
  const matches = cleaned.match(/\b\d{9}\b/g);
  if (!matches) return null;
  return matches.find(n => /^[5-8]/.test(n)) || matches[0];
}

function normalizeDate(text) {
  let m = text.match(/data\s*de\s*emiss[aã]o\s*[:\-]?\s*(\d{2})[.\/\-](\d{2})[.\/\-](\d{4})/i);
  if (m) return `${m[3]}-${m[2]}-${m[1]}`;
  m = text.match(/(\d{2})[.\/\-](\d{2})[.\/\-](\d{4})/);
  if (m) return `${m[3]}-${m[2]}-${m[1]}`;
  m = text.match(/(\d{4})-(\d{2})-(\d{2})/);
  if (m) return `${m[1]}-${m[2]}-${m[3]}`;
  return null;
}

function extractEmpresa(text) {
  const linhas = text.split('\n').map(l => l.trim()).filter(Boolean);
  for (let i = 0; i < Math.min(12, linhas.length); i++) {
    if (/S\.A\.|, LDA|LDA\.|UNIPESSOAL/i.test(linhas[i]) && linhas[i].length > 4)
      return linhas[i];
  }
  return '';
}

async function pdfPaginaParaBlob(pdfDoc, numPagina, escala) {
  escala = escala || 2.5;
  const page     = await pdfDoc.getPage(numPagina);
  const viewport = page.getViewport({ scale: escala });
  const canvas   = document.createElement('canvas');
  canvas.width   = viewport.width;
  canvas.height  = viewport.height;
  await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
  return new Promise(r => canvas.toBlob(r, 'image/png'));
}

function setProgress(pct, label) {
  document.getElementById('progressBar').style.width = pct + '%';
  document.getElementById('ocrPct').textContent = pct + '%';
  if (label) document.getElementById('ocrStatus').textContent = label;
}

// ═══════════════════════════════════════════════════════════════════════════
// UPLOAD: QR primeiro → Tesseract OCR como fallback
// ═══════════════════════════════════════════════════════════════════════════
async function uploadImagem() {
  const input = document.getElementById('fileInput');
  if (!input.files[0]) return;

  const file  = input.files[0];
  const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');

  document.getElementById('ocrProgressWrap').style.display = '';
  document.getElementById('btnVerTexto').style.display = 'none';
  document.getElementById('ocrTextoBox').style.display = 'none';
  setProgress(5, 'A preparar ficheiro…');

  let pdfDoc = null;

  try {
    // ── Passo 1: tenta ler QR ──────────────────────────────────────────────
    setProgress(10, 'A procurar QR code…');
    let blobParaQR = file;

    if (isPdf) {
      setProgress(15, 'A renderizar PDF para leitura de QR…');
      const ab = await file.arrayBuffer();
      pdfDoc = await pdfjsLib.getDocument({ data: ab }).promise;
      // última página costuma ter o QR nas faturas AT
      blobParaQR = await pdfPaginaParaBlob(pdfDoc, pdfDoc.numPages);
    }

    try {
      const txt   = await tentarLerQR(blobParaQR);
      const dados = parseQR(txt);
      setProgress(100, 'QR code encontrado!');
      document.getElementById('ocrProgressWrap').style.display = 'none';
      preencherForm(dados);
      if (dados.nif) fetchCompanyByNif(dados.nif);
      mostrarResultado('QR code lido com sucesso');
      return;
    } catch (_) {
      setProgress(30, 'QR não encontrado — a correr OCR…');
    }

    // ── Passo 2: OCR ──────────────────────────────────────────────────────
    let textoCompleto = '';

    if (isPdf) {
      if (!pdfDoc) {
        const ab = await file.arrayBuffer();
        pdfDoc = await pdfjsLib.getDocument({ data: ab }).promise;
      }
      for (let i = 1; i <= pdfDoc.numPages; i++) {
        const blob = await pdfPaginaParaBlob(pdfDoc, i);
        const { data: { text } } = await Tesseract.recognize(blob, 'por', {
          logger: m => {
            if (m.status === 'recognizing text') {
              const base = 30 + ((i - 1) / pdfDoc.numPages) * 65;
              const inc  = (m.progress * 65) / pdfDoc.numPages;
              setProgress(Math.round(base + inc), `OCR pág ${i}/${pdfDoc.numPages}: ${Math.round(m.progress * 100)}%`);
            }
          }
        });
        textoCompleto += '\n' + text;
      }
    } else {
      const { data: { text } } = await Tesseract.recognize(file, 'por', {
        logger: m => {
          if (m.status === 'recognizing text')
            setProgress(30 + Math.round(m.progress * 65), `OCR: ${Math.round(m.progress * 100)}%`);
        }
      });
      textoCompleto = text;
    }

    setProgress(100, '');
    document.getElementById('ocrProgressWrap').style.display = 'none';

    // ── Passo 3: extrai campos ─────────────────────────────────────────────
    const nif     = extractNif(textoCompleto)     || '';
    const empresa = extractEmpresa(textoCompleto) || '';
    const data    = normalizeDate(textoCompleto)  || '';
    const total   = (extractTotal(textoCompleto)  || '').replace(',', '.');

    // mostra texto OCR para debug
    document.getElementById('ocrTextoBox').textContent = textoCompleto.substring(0, 3000);
    document.getElementById('btnVerTexto').style.display = 'inline';

    preencherForm({ nif, empresa, data, numero: '', total });
    if (nif) fetchCompanyByNif(nif);

    const resumo = [
      nif     && `NIF: ${nif}`,
      empresa && `Empresa: ${empresa}`,
      total   && `Total: ${total}€`,
      data    && `Data: ${data}`,
    ].filter(Boolean).join(' · ');

    document.getElementById('ocrStatus').textContent = resumo || 'Nenhum campo reconhecido — preenche manualmente.';
    mostrarResultado(resumo ? 'OCR concluído' : 'OCR concluído — preenche manualmente');

  } catch (err) {
    setProgress(0, '');
    document.getElementById('ocrProgressWrap').style.display = 'none';
    document.getElementById('ocrStatus').textContent = 'Erro: ' + err.message;
    console.error('uploadImagem erro:', err);
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// PREENCHER FORMULÁRIO + mostrar resultado
// ═══════════════════════════════════════════════════════════════════════════
function preencherForm(dados) {
  if (!dados) return;
  document.getElementById('fNif').value     = dados.nif     || '';
  document.getElementById('fEmpresa').value = dados.empresa || '';
  document.getElementById('fData').value    = dados.data    || '';
  document.getElementById('fTotal').value   = dados.total   || '';
}

function mostrarResultado(msg) {
  document.getElementById('resultBadge').innerHTML =
    `<i class="bi bi-check-circle-fill"></i> ${msg} — confirma e guarda`;
  document.getElementById('resultSection').classList.add('visible');
  document.getElementById('instructionsCard').style.display = 'none';
  window.scrollTo({ top: document.getElementById('resultSection').offsetTop - 20, behavior: 'smooth' });
}

function resetForm() {
  ['fEmpresa','fData','fTotal'].forEach(id => {
    document.getElementById(id).value = '';
  });
  document.getElementById('fData').value = '<?=date('Y-m-d')?>';
  document.getElementById('nifInfo').innerHTML = '';
  document.getElementById('resultSection').classList.remove('visible');
  document.getElementById('instructionsCard').style.display = '';
  document.getElementById('fileInput').value = '';
  document.getElementById('ocrStatus').textContent = '';
  document.getElementById('btnVerTexto').style.display = 'none';
  document.getElementById('ocrTextoBox').style.display = 'none';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ═══════════════════════════════════════════════════════════════════════════
// NIF → EMPRESA  (chama get_company_by_nif.php)
// ═══════════════════════════════════════════════════════════════════════════
function fetchCompanyByNif(nif) {
  if (!/^\d{9}$/.test(nif)) return;
  const el      = document.getElementById('fEmpresa');
  const infoEl  = document.getElementById('nifInfo');
  infoEl.textContent = '⏳ A consultar NIF ' + nif + '…';
  el.placeholder = 'A procurar empresa…';

  fetch('get_company_by_nif.php?nif=' + encodeURIComponent(nif))
    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(json => {
      el.placeholder = 'Nome do estabelecimento';
      if (json.found && json.nome_empresa) {
        if (!el.value) el.value = json.nome_empresa;
        if (json.id_categoria) document.getElementById('fCategoria').value = json.id_categoria;

        const sourceMap = {
          cache:    ['source-cache', 'Cache local'],
          local:    ['source-local', 'Base local'],
          'nif.pt': ['source-nifpt', 'nif.pt'],
          vies:     ['source-vies',  'VIES'],
        };
        const [cls, label] = sourceMap[json.source] ?? ['source-cache', json.fonte || json.source || 'Cache'];
        infoEl.innerHTML =
          '<i class="bi bi-check-circle" style="color:#16a34a"></i> NIF ' + nif +
          ' <span class="source-badge ' + cls + '">' + label + '</span>';
      } else {
        infoEl.innerHTML = '<i class="bi bi-exclamation-circle" style="color:#f59e0b"></i> NIF ' + nif + ' não encontrado — preenche manualmente';
      }
    })
    .catch(() => {
      el.placeholder = 'Nome do estabelecimento';
      infoEl.textContent = '';
    });
}

function saveCompanyNif(nif, nome, idCategoria) {
  if (!/^\d{9}$/.test(nif) || !nome) return;
  fetch('save_company_nif.php', {
    method: 'POST',
    body: new URLSearchParams({ nif, nome_empresa: nome, id_categoria: idCategoria || '' })
  }).catch(() => {});
}

// ═══════════════════════════════════════════════════════════════════════════
// EVENTOS
// ═══════════════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('btnIniciarQR').addEventListener('click', startQR);
  document.getElementById('btnPararQR').addEventListener('click', stopQR);

  // Auto-lookup quando o utilizador escreve o NIF manualmente
  const nifInput = document.getElementById('fNif');
  let nifTimer;
  nifInput.addEventListener('input', () => {
    clearTimeout(nifTimer);
    if (nifInput.value.length === 9)
      nifTimer = setTimeout(() => fetchCompanyByNif(nifInput.value), 400);
  });

  // Guardar NIF na BD ao submeter
  document.getElementById('faturaForm').addEventListener('submit', () => {
    saveCompanyNif(
      document.getElementById('fNif').value,
      document.getElementById('fEmpresa').value,
      document.getElementById('fCategoria').value
    );
  });
});
</script>
</body>
</html>