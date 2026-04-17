<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$message = '';
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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Faturas - FreeCard</title>
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
      background: rgba(46, 88, 204, 0.15);
    }
    [data-theme="dark"] .floating-shape {
      background: rgba(46, 88, 204, 0.2);
    }
    .shape1 { width:300px;height:300px;top:-100px;left:-100px;animation-delay:0s; }
    .shape2 { width:200px;height:200px;bottom:-50px;right:-50px;animation-delay:5s; }
    .shape3 { width:150px;height:150px;top:50%;right:10%;animation-delay:2s; }
    .shape4 { width:100px;height:100px;bottom:20%;left:15%;animation-delay:7s; }
    .shape5 { width:250px;height:250px;top:30%;left:50%;animation-delay:3s; }
    .shape6 { width:250px;height:300px;top:-100px;right:-100px;animation-delay:6s; }
    @keyframes float { 0%,100%{transform:translateY(0) rotate(0deg);opacity:.3} 50%{transform:translateY(-30px) rotate(180deg);opacity:.6} }
    .particle { position:absolute;width:4px;height:4px;border-radius:50%;animation:rise 15s infinite ease-in; }
    [data-theme="light"] .particle { background:rgba(46,88,204,.4); }
    [data-theme="dark"] .particle { background:rgba(46,88,204,.5); }
    @keyframes rise { 0%{transform:translateY(0) translateX(0);opacity:0} 10%{opacity:1} 90%{opacity:1} 100%{transform:translateY(-100vh) translateX(50px);opacity:0} }
    .navbar,.container{position:relative;z-index:1;}
    @media(prefers-reduced-motion:reduce){.floating-shape,.particle{animation:none;opacity:.2}}
    #ocrTextoBox{font-size:10px;max-height:160px;overflow:auto;background:#f8f9fa;color:#333;padding:8px;border-radius:4px;margin-top:6px;white-space:pre-wrap;display:none}
    :root {
      --primary-blue: #3498db;
      --dark-blue: #2980b9;
    }
  </style>

  <!-- !! ORDEM IMPORTA: html5-qrcode, depois tesseract, depois pdf.js !! -->
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <script src="https://unpkg.com/tesseract.js@4.0.2/dist/tesseract.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script>
    pdfjsLib.GlobalWorkerOptions.workerSrc =
      'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
  </script>
</head>
<body>
<div class="bg-animation">
  <div class="floating-shape shape1"></div>
  <div class="floating-shape shape2"></div>
  <div class="floating-shape shape3"></div>
  <div class="floating-shape shape4"></div>
  <div class="floating-shape shape5"></div>
  <div class="floating-shape shape6"></div>
</div>

<!--
  Container SEPARADO para scanFile() de ficheiros.
  NUNCA usar #reader — interfere com a câmara ao vivo.
  NÃO chamar scanner.stop() depois de scanFile() — scanFile não "inicia" o scanner,
  stop() lançaria erro e corromperia o fluxo silenciosamente.
-->
<div id="qrFileContainer" style="display:none;width:1px;height:1px;overflow:hidden;position:absolute;"></div>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container">
          <a class="navbar-brand fw-bold" href="../../dashboard.php">
      <img src="../assets/logo2.png" alt="Freecard" style="height:35px;margin-right:8px;">
      FreeCard
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="../../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
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
            <li><a class="dropdown-item" href="../../pages/settings.php"><i class="bi bi-gear"></i> Configurações</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="../../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Sair</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!--
  DIV OCULTO para QR scanning de ficheiros.
  NUNCA usar #reader para isto — causa conflito com a câmara ao vivo.
-->

<div class="container mt-4 mb-5">
  <div class="row">
    <div class="col-12">

      <?php if ($message): ?>
        <div class="alert alert-<?=$messageType?> alert-dismissible fade show">
          <?=$message?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="card mb-4">
        <div class="card-body">
          <div class="row gy-3">

            <!-- CÂMARA AO VIVO -->
            <div class="col-md-6">
              <h5>Ler QR-code da Fatura</h5>
              <div id="reader" style="width:100%;max-width:400px;min-height:250px;background:#0b0f1a;border-radius:12px;padding:6px;"></div>
              <div class="mt-2 d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-primary btn-sm" id="btnIniciarQR">
                  <i class="bi bi-camera"></i> Iniciar câmara
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPararQR" style="display:none;">
                  <i class="bi bi-stop-circle"></i> Parar
                </button>

              </div>
              <div id="qrImageStatus" class="small text-muted mt-2"></div>
            </div>

            <!-- OCR / PDF -->
            <div class="col-md-6">
              <h5>🧾 OCR / PDF da fatura</h5>
              <p class="text-muted small">
                Carrega uma imagem (JPG, PNG) ou PDF.<br>
                Tenta ler o QR automaticamente. Se não encontrar, extrai por OCR.
              </p>
              <input id="upload" type="file" class="form-control form-control-sm"
                     accept="image/*,.pdf" onchange="uploadImagem()">
              <div id="ocrStatus" class="small text-muted mt-2"></div>
              <!-- botão para ver texto OCR (aparece após OCR) -->
              <a href="#" id="btnVerTexto" style="display:none;font-size:12px;" onclick="document.getElementById('ocrTextoBox').style.display='block';this.style.display='none';return false;">
                Ver texto extraído pelo OCR
              </a>
              <div id="ocrTextoBox"></div>
              <canvas id="pdfCanvas" style="display:none;max-width:100%;border-radius:8px;margin-top:8px;"></canvas>
            </div>

          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h5 class="mb-3">Confirmar os dados da fatura</h5>
          <form method="post" id="faturaForm">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">NIF</label>
                <input id="nif" name="nif" class="form-control" placeholder="NIF da empresa (ex: 123456789)" maxlength="9">
              </div>
              <div class="col-md-4">
                <label class="form-label">Empresa</label>
                <input id="empresa" name="empresa" class="form-control" placeholder="Nome da empresa" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Categoria</label>
                <select id="categoria" name="id_categoria" class="form-select">
                  <option value="">— Selecionar —</option>
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
              <div class="col-md-2">
                <label class="form-label">Total</label>
                <input id="total" name="total" type="text" class="form-control" placeholder="0.00" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Data</label>
                <input id="data" name="data" type="date" class="form-control" value="<?=date('Y-m-d')?>" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Nº Fatura (opcional)</label>
                <input id="numero" name="numero" class="form-control" placeholder="Número de fatura">
              </div>
              <div class="col-md-6">
                <label class="form-label">Cartão</label>
                <select name="card_id" class="form-select">
                  <option value="">Sem cartão (apenas histórico)</option>
                  <?php foreach ($cards as $card): ?>
                    <option value="<?=$card['id']?>"><?=htmlspecialchars($card['name'])?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Guardar fatura</button>
              </div>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
'use strict';

// ═══════════════════════════════════════════════════════════════════════════
// CÂMARA AO VIVO  (usa #reader — nunca misturar com scanFile)
// ═══════════════════════════════════════════════════════════════════════════
let qrScanner = null;

function stopQR() {
  const old = qrScanner;
  qrScanner = null;
  document.getElementById('btnIniciarQR').style.display = 'inline-block';
  document.getElementById('btnPararQR').style.display   = 'none';
  if (!old) return Promise.resolve();
  return old.stop().catch(() => {}).then(() => { try { old.clear(); } catch(_){} });
}

// NÃO pode ser async — getUserMedia tem de ser chamado direto no click
function startQR() {
  if (qrScanner) stopQR();
  document.getElementById('btnIniciarQR').style.display = 'none';
  document.getElementById('btnPararQR').style.display   = 'inline-block';
  qrScanner = new Html5Qrcode('reader');
  qrScanner.start(
    { facingMode: 'environment' },
    { fps: 10, qrbox: { width: 250, height: 250 }, disableFlip: true },
    (txt) => {
      const dados = parseQR(txt);
      preencherForm(dados);
      if (dados.nif) fetchCompanyByNif(dados.nif);
      stopQR();
    },
    () => {}
  ).catch(err => {
    console.warn('Câmara indisponível:', err);
    alert('Câmara não disponível. Usa o botão "QR de imagem".');
    stopQR();
  });
}

// ═══════════════════════════════════════════════════════════════════════════
// PARSE QR Portugal AT
// ═══════════════════════════════════════════════════════════════════════════
function parseQR(qr) {
  const fields = {};
  qr.split('*').forEach(seg => {
    const i = seg.indexOf(':');
    if (i === -1) return;
    fields[seg.substring(0, i).trim()] = seg.substring(i + 1).trim();
  });
  let data = '';
  if (fields['F'] && /^\d{8}$/.test(fields['F'])) {
    const d = fields['F'];
    data = `${d.slice(0,4)}-${d.slice(4,6)}-${d.slice(6,8)}`;
  }
  return {
    nif:    fields['A'] || '',
    empresa: '',
    data,
    numero: fields['G'] || '',
    total:  (fields['O'] || fields['N'] || '').replace(',', '.')
  };
}

// ═══════════════════════════════════════════════════════════════════════════
// HELPER: scanFile num container oculto SEPARADO do #reader
//
// !! CRÍTICO: NÃO chamar scanner.stop() depois de scanFile()
//    scanFile() NÃO usa start(), por isso stop() lança erro interno
//    que corrompe o fluxo silenciosamente mesmo dentro de try/catch.
//    Só usar scanner.clear() para limpar o DOM.
// ═══════════════════════════════════════════════════════════════════════════
async function tentarLerQR(blob) {
  const container = document.getElementById('qrFileContainer');
  container.innerHTML = ''; // limpa instâncias anteriores
  const scanner = new Html5Qrcode('qrFileContainer');
  try {
    // scanFile devolve o texto do QR ou lança erro se não encontrar
    const txt = await scanner.scanFile(blob, false);
    return txt;
  } finally {
    // APENAS clear() — NÃO stop() — scanFile nunca "inicia" o scanner
    try { scanner.clear(); } catch(_) {}
    container.innerHTML = '';
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// BOTÃO "QR de imagem" (só imagens, sem PDF)
// ═══════════════════════════════════════════════════════════════════════════


// ─────────────────────────────────────────────────────────────────────────────
// DATA EXTRACTION HELPERS
// ─────────────────────────────────────────────────────────────────────────────
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
  // fallback: maior valor numérico com decimais > 0.50
  const todos = [...text.matchAll(/(\d{1,6}[.,]\d{2})/g)]
    .map(m => ({ raw: m[1], val: parseFloat(m[1].replace(',', '.')) }))
    .filter(x => x.val > 0.5);
  if (!todos.length) return null;
  todos.sort((a, b) => b.val - a.val);
  return todos[0].raw;
}

function extractNif(text) {
  // PT 123456789 ou PT123456789
  const pt = text.match(/PT\s*(\d{9})/i);
  if (pt) return pt[1];
  // remove espaços que o OCR insere entre dígitos (aplica 2x para duplos espaços)
  let cleaned = text.replace(/(\d)\s+(\d)/g, '$1$2').replace(/(\d)\s+(\d)/g, '$1$2');
  const matches = cleaned.match(/\b\d{9}\b/g);
  if (!matches) return null;
  return matches.find(n => /^[5-8]/.test(n)) || matches[0];
}

function normalizeDate(text) {
  // "Data de emissão: DD/MM/YYYY"
  let m = text.match(/data\s*de\s*emiss[aã]o\s*[:\-]?\s*(\d{2})[.\/\-](\d{2})[.\/\-](\d{4})/i);
  if (m) return `${m[3]}-${m[2]}-${m[1]}`;
  // DD/MM/YYYY  DD-MM-YYYY  DD.MM.YYYY
  m = text.match(/(\d{2})[.\/\-](\d{2})[.\/\-](\d{4})/);
  if (m) return `${m[3]}-${m[2]}-${m[1]}`;
  // YYYY-MM-DD já correto
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

// ─────────────────────────────────────────────────────────────────────────────
// PREENCHER FORMULÁRIO
// ─────────────────────────────────────────────────────────────────────────────
function preencherForm(dados) {
  console.log('preencherForm chamado com:', dados);
  if (typeof dados !== 'object' || !dados) return;
  
  const nifEl = document.getElementById('nif');
  const empresaEl = document.getElementById('empresa');
  const dataEl = document.getElementById('data');
  const numeroEl = document.getElementById('numero');
  const totalEl = document.getElementById('total');
  
  if (nifEl) nifEl.value = dados.nif || '';
  if (empresaEl) empresaEl.value = dados.empresa || '';
  if (dataEl) dataEl.value = dados.data || '';
  if (numeroEl) numeroEl.value = dados.numero || '';
  if (totalEl) totalEl.value = dados.total || '';
  
  console.log('Valores após preencher:', {
    nif: nifEl?.value,
    empresa: empresaEl?.value,
    data: dataEl?.value,
    numero: numeroEl?.value,
    total: totalEl?.value
  });
}

// ═══════════════════════════════════════════════════════════════════════════
// NIF → EMPRESA
// ═══════════════════════════════════════════════════════════════════════════
function fetchCompanyByNif(nif) {
  if (!/^\d{9}$/.test(nif)) return;
  const el   = document.getElementById('empresa');
  const orig = el.placeholder;
  el.placeholder = 'A procurar empresa...';
  el.disabled    = true;
  fetch(`./get_company_by_nif.php?nif=${encodeURIComponent(nif)}`)
    .then(r => r.json())
    .then(json => {
      el.disabled    = false;
      el.placeholder = orig;
      if (json.found && json.nome_empresa) {
        el.value = json.nome_empresa;
        if (json.id_categoria) document.getElementById('categoria').value = json.id_categoria;
        if (json.source && json.source !== 'cache') {
          document.getElementById('nifSourceBadge')?.remove();
          const b = document.createElement('small');
          b.id = 'nifSourceBadge'; b.className = 'text-success d-block mt-1';
          b.textContent = 'Encontrado via ' + (json.fonte || json.source);
          el.parentNode.appendChild(b);
        }
      } else {
        el.placeholder = 'Empresa não encontrada — preenche manualmente';
      }
    })
    .catch(() => { el.disabled = false; el.placeholder = orig; });
}

function saveCompanyNif(nif, nome, idCategoria) {
  if (!/^\d{9}$/.test(nif) || !nome) return;
  fetch('./save_company_nif.php', {
    method: 'POST',
    body: new URLSearchParams({ nif, nome_empresa: nome, id_categoria: idCategoria || '' })
  }).catch(() => {});
}

// ═══════════════════════════════════════════════════════════════════════════
// RENDERIZAR PÁGINA PDF → Blob PNG
// ═══════════════════════════════════════════════════════════════════════════
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

// ═══════════════════════════════════════════════════════════════════════════
// MOSTRAR TEXTO OCR (para debug)
// ═══════════════════════════════════════════════════════════════════════════
function mostrarTextoOCR(texto) {
  const box = document.getElementById('ocrTextoBox');
  const btn = document.getElementById('btnVerTexto');
  box.textContent = texto.substring(0, 3000);
  box.style.display = 'none';
  btn.style.display = 'inline';
}

// ═══════════════════════════════════════════════════════════════════════════
// UPLOAD PRINCIPAL: QR primeiro → Tesseract OCR como fallback
// ═══════════════════════════════════════════════════════════════════════════
// ═══════════════════════════════════════════════════════════════════════════
// UPLOAD PRINCIPAL: QR primeiro → Tesseract OCR como fallback
// ═══════════════════════════════════════════════════════════════════════════
async function uploadImagem() {
  const input  = document.getElementById('upload');
  const status = document.getElementById('ocrStatus');
  // esconde o botão de texto anterior
  document.getElementById('btnVerTexto').style.display = 'none';
  document.getElementById('ocrTextoBox').style.display = 'none';

  if (!input.files[0]) return;

  const file  = input.files[0];
  const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
  let   pdfDoc = null;

  status.innerHTML = 'A preparar ficheiro...';

  try {

    // ── PASSO 1: tenta ler QR ──────────────────────────────────────────────
    status.innerHTML = 'A procurar QR code...';
    let blobParaQR = file;

    if (isPdf) {
      status.innerHTML = 'A renderizar PDF para leitura de QR...';
      const ab = await file.arrayBuffer();
      pdfDoc = await pdfjsLib.getDocument({ data: ab }).promise;
      // última página costuma ter o QR nas faturas AT
      blobParaQR = await pdfPaginaParaBlob(pdfDoc, pdfDoc.numPages);
    }

    try {
      const txt   = await tentarLerQR(blobParaQR);
      const dados = parseQR(txt);
      status.innerHTML = 'QR code encontrado e lido!';
      console.log('QR lido:', txt, '→', dados);
      preencherForm(dados);
      if (dados.nif) fetchCompanyByNif(dados.nif);
      return; // não precisa de OCR
    } catch (_) {
      status.innerHTML = 'QR não encontrado. A correr OCR...';
    }

    // ── PASSO 2: OCR ───────────────────────────────────────────────────────
    let textoCompleto = '';

    if (isPdf) {
      if (!pdfDoc) {
        const ab = await file.arrayBuffer();
        pdfDoc = await pdfjsLib.getDocument({ data: ab }).promise;
      }

      // mostra preview da 1ª página
      const prevBlob = await pdfPaginaParaBlob(pdfDoc, 1, 1.0);
      const previewUrl = URL.createObjectURL(prevBlob);
      const cvs = document.getElementById('pdfCanvas');
      cvs.style.display = 'block';
      const img = new Image();
      img.onload = () => { cvs.width = img.width; cvs.height = img.height; cvs.getContext('2d').drawImage(img, 0, 0); URL.revokeObjectURL(previewUrl); };
      img.src = previewUrl;

      // OCR de todas as páginas
      for (let i = 1; i <= pdfDoc.numPages; i++) {
        status.innerHTML = `OCR página ${i}/${pdfDoc.numPages}...`;
        const blob = await pdfPaginaParaBlob(pdfDoc, i);
        const { data: { text } } = await Tesseract.recognize(blob, 'por', {
          logger: m => {
            if (m.status === 'recognizing text')
              status.innerHTML = `OCR pág ${i}/${pdfDoc.numPages}: ${Math.round(m.progress * 100)}%`;
          }
        });
        textoCompleto += '\n' + text;
      }
    } else {
      const { data: { text } } = await Tesseract.recognize(file, 'por', {
        logger: m => {
          if (m.status === 'recognizing text')
            status.innerHTML = `OCR: ${Math.round(m.progress * 100)}%`;
        }
      });
      textoCompleto = text;
    }

    console.log('=== TEXTO OCR COMPLETO ===\n', textoCompleto);
    mostrarTextoOCR(textoCompleto);

    // ── PASSO 3: extrai e preenche ─────────────────────────────────────────
    const nif     = extractNif(textoCompleto)     || '';
    const empresa = extractEmpresa(textoCompleto) || '';
    const data    = normalizeDate(textoCompleto)  || '';
    const total   = (extractTotal(textoCompleto)  || '').replace(',', '.');

    console.log('Campos extraídos →', { nif, empresa, data, total });

    // preenche SEMPRE — mesmo que esteja vazio (utilizador corrige manualmente)
    preencherForm({ nif, empresa, data, numero: '', total });
    if (nif) fetchCompanyByNif(nif);

    const resumo = [
      nif     && `NIF: ${nif}`,
      empresa && `Empresa: ${empresa}`,
      total   && `Total: ${total}`,
      data    && `Data: ${data}`,
    ].filter(Boolean).join('  |  ');

    status.innerHTML = resumo
      ? `Extraído: <strong>${resumo}</strong>`
      : `Nenhum campo reconhecido automaticamente — preenche manualmente.`;

  } catch (err) {
    status.innerHTML = `Erro: ${err.message}`;
    console.error('uploadImagem erro:', err);
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// EVENTOS
// ═══════════════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('btnIniciarQR').addEventListener('click', startQR);
  document.getElementById('btnPararQR').addEventListener('click', stopQR);

  const nifInput = document.getElementById('nif');
  let nifTimer;
  nifInput.addEventListener('input', () => {
    clearTimeout(nifTimer);
    if (nifInput.value.length === 9)
      nifTimer = setTimeout(() => fetchCompanyByNif(nifInput.value), 400);
  });
  nifInput.addEventListener('blur', () => {
    if (nifInput.value.length === 9) fetchCompanyByNif(nifInput.value);
  });

  document.getElementById('faturaForm').addEventListener('submit', () => {
    saveCompanyNif(
      document.getElementById('nif').value,
      document.getElementById('empresa').value,
      document.getElementById('categoria').value
    );
  });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
  if (window.innerWidth <= 768) return;
  const c = document.querySelector('.bg-animation');
  if (!c) return;
  for (let i = 0; i < 12; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    p.style.left = Math.random() * 100 + '%';
    p.style.animationDelay = Math.random() * 15 + 's';
    p.style.animationDuration = (15 + Math.random() * 10) + 's';
    c.appendChild(p);
  }
})();
</script>
</body>
</html>
