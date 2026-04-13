<?php
// ORIGINAL invoices.php style + ALL fixes (NIF lookup, QR fast, paths)
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
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <script src="https://unpkg.com/tesseract.js@4.0.2/dist/tesseract.min.js"></script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="../dashboard.php"><i class="bi bi-credit-card-fill"></i> FreeCard</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="transactions.php">Transações</a></li>
        <li class="nav-item"><a class="nav-link active" href="invoices.php">Faturas</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-4 mb-5">
  <div class="row">
    <div class="col-12">

      <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h2 class="mb-1"><i class="bi bi-upc-scan"></i> Faturas</h2>
            <p class="text-muted mb-0">Leitura QR + OCR + confirmação manual</p>
          </div>
          <a href="transactions.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left"></i> Voltar às Transações</a>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-<?=$messageType?> alert-dismissible fade show">
          <?=$message?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- EXACT ORIGINAL QR/OCR CARD -->
      <div class="card mb-4">
        <div class="card-body">
          <div class="row gy-3">
            <div class="col-md-6">
              <h5>📷 Ler QR Code</h5>
              <div id="reader" style="width:100%;max-width:400px;min-height:250px;background:#f8f9fa;border-radius:12px;padding:6px;"></div>
              <div class="mt-2 d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-primary btn-sm" id="btnIniciarQR">
                  <i class="bi bi-camera"></i> Iniciar câmara
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPararQR" style="display:none;">
                  <i class="bi bi-stop-circle"></i> Parar
                </button>
                <!-- NEW FAST QR IMAGE -->
                <label class="btn btn-outline-primary btn-sm mb-0">
                  <i class="bi bi-image"></i> QR de imagem
                  <input type="file" id="qrImageInput" accept="image/*" style="display:none;" onchange="lerQRDeImagem(this)">
                </label>
              </div>
              <div id="qrImageStatus" class="small text-muted mt-2"></div>
            </div>
            <div class="col-md-6">
              <h5>🧾 OCR da imagem</h5>
              <p class="text-muted small">Carrega uma foto de fatura para extrair texto automaticamente.</p>
              <input id="upload" type="file" class="form-control form-control-sm" accept="image/*" onchange="uploadImagem()">
              <div id="ocrStatus" class="small text-muted mt-2"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h5 class="mb-3">📝 Confirma e guarda</h5>
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
/**
 * invoices_camera_fix.js  (v2)
 * ────────────────────────────
 * FIX CRÍTICO: startQR() deve ser SÍNCRONO até ao qrScanner.start()
 * Os browsers bloqueiam getUserMedia se houver qualquer await antes da chamada.
 */

let qrScanner = null;

// ─── PARSE QR AT Portugal ────────────────────────────────────────────────────
function parseQR(qr) {
  const fields = {};
  qr.split('*').forEach(segment => {
    const idx = segment.indexOf(':');
    if (idx === -1) return;
    fields[segment.substring(0, idx).trim()] = segment.substring(idx + 1).trim();
  });

  let dataFormatada = '';
  if (fields['F'] && /^\d{8}$/.test(fields['F'])) {
    const d = fields['F'];
    dataFormatada = `${d.slice(0,4)}-${d.slice(4,6)}-${d.slice(6,8)}`;
  }

  return {
    nif:     fields['A'] || '',
    empresa: '',
    data:    dataFormatada,
    numero:  fields['G'] || '',
    total:   (fields['O'] || fields['N'] || '').replace(',', '.')
  };
}

// ─── PARAR SCANNER ────────────────────────────────────────────────────────────
// Retorna uma Promise mas NAO deve ser awaited antes de startQR()
function stopQR() {
  const old = qrScanner;
  qrScanner = null;

  document.getElementById('btnIniciarQR').style.display = 'inline-block';
  document.getElementById('btnPararQR').style.display   = 'none';

  if (!old) return Promise.resolve();

  return old.stop()
    .catch(() => {})
    .then(() => { try { old.clear(); } catch (_) {} });
}

// ─── INICIAR SCANNER ──────────────────────────────────────────────────────────
// !! NAO pode ser async nem ter await antes de qrScanner.start() !!
// O browser exige que getUserMedia seja chamado directamente no handler do clique.
function startQR() {
  // Se ja existe um scanner activo, para-o em background (fire and forget)
  if (qrScanner) {
    stopQR();
  }

  document.getElementById('btnIniciarQR').style.display = 'none';
  document.getElementById('btnPararQR').style.display   = 'inline-block';

  qrScanner = new Html5Qrcode('reader');

  qrScanner.start(
    { facingMode: 'environment' },
    { fps: 10, qrbox: { width: 250, height: 250 }, disableFlip: true },
    (decodedText) => {
      const dados = parseQR(decodedText);
      preencherForm(dados);
      if (dados.nif) fetchCompanyByNif(dados.nif);
      stopQR();
    },
    () => {}
  ).catch((err) => {
    console.warn('Camera nao disponivel:', err);
    alert('Camera nao disponivel. Usa o botao "QR de imagem".');
    stopQR();
  });
}

// ─── LER QR DE IMAGEM ─────────────────────────────────────────────────────────
// scanFile() nao precisa de camera -> async/await e seguro aqui
async function lerQRDeImagem(input) {
  const file = input.files[0];
  if (!file) return;

  const status = document.getElementById('qrImageStatus');
  status.textContent = 'A ler QR da imagem...';

  // Para camera ao vivo se estiver activa
  await stopQR();

  const tempScanner = new Html5Qrcode('reader');

  try {
    const decodedText = await tempScanner.scanFile(file, false);
    status.textContent = 'QR lido com sucesso!';
    const dados = parseQR(decodedText);
    preencherForm(dados);
    if (dados.nif) fetchCompanyByNif(dados.nif);
  } catch (_) {
    status.textContent = 'QR nao encontrado - a tentar OCR...';
    const uploadInput = document.getElementById('upload');
    const dt = new DataTransfer();
    dt.items.add(file);
    uploadInput.files = dt.files;
    uploadImagem();
  } finally {
    try { tempScanner.clear(); } catch (_) {}
    input.value = '';
  }
}

// ─── OCR ─────────────────────────────────────────────────────────────────────
function extractTotal(text) {
  const patterns = [
    /(?:total\s*a\s*pagar|a\s*pagar)\s*[:\-]?\s*(\d{1,6}[.,]\d{2})\s*€?/i,
    /total\s*[:\-]?\s*€\s*(\d{1,6}[.,]\d{2})/i,
    /total\s*[:\-]?\s*(\d{1,6}[.,]\d{2})\s*€/i,
    /€\s*(\d{1,6}[.,]\d{2})(?!\d)/,
    /(?<!\d)(\d{1,6}[.,]\d{2})\s*€/,
    /EUR\s*(\d{1,6}[.,]\d{2})/i
  ];
  for (const p of patterns) {
    const m = text.match(p);
    if (m) return m[1];
  }
  return null;
}

function extractNif(text) {
  const matches = text.match(/\b\d{9}\b/g);
  if (!matches) return null;
  return matches.find(n => /^5/.test(n)) || matches[0];
}

function normalizeDate(text) {
  const m =
    text.match(/(\d{2})\/(\d{2})\/(\d{4})/) ||
    text.match(/(\d{2})-(\d{2})-(\d{4})/);
  if (m) return `${m[3]}-${m[2]}-${m[1]}`;
  return null;
}

async function uploadImagem() {
  const input  = document.getElementById('upload');
  const status = document.getElementById('ocrStatus');
  if (!input.files[0]) return;

  status.textContent = 'A processar OCR...';

  try {
    const { data: { text } } = await Tesseract.recognize(input.files[0], 'por', {
      logger: m => {
        if (m.status === 'recognizing text')
          status.textContent = `OCR: ${Math.round(m.progress * 100)}%`;
      }
    });

    const dados = {
      nif:    extractNif(text)    || '',
      empresa:'',
      data:   normalizeDate(text) || '',
      numero: '',
      total:  (extractTotal(text) || '').replace(',', '.')
    };

    preencherForm(dados);
    if (dados.nif) fetchCompanyByNif(dados.nif);
    status.textContent = 'OCR concluido!';
  } catch (err) {
    status.textContent = 'Erro no OCR.';
    console.error(err);
  }
}

// ─── PREENCHER FORMULARIO ─────────────────────────────────────────────────────
function preencherForm(dados) {
  if (dados.nif)     document.getElementById('nif').value     = dados.nif;
  if (dados.empresa) document.getElementById('empresa').value = dados.empresa;
  if (dados.data)    document.getElementById('data').value    = dados.data;
  if (dados.numero)  document.getElementById('numero').value  = dados.numero;
  if (dados.total)   document.getElementById('total').value   = dados.total;
}

// ─── BUSCAR EMPRESA POR NIF ───────────────────────────────────────────────────
function fetchCompanyByNif(nif) {
  if (!/^\d{9}$/.test(nif)) return;

  const empresaInput = document.getElementById('empresa');
  const original     = empresaInput.placeholder;
  empresaInput.placeholder = 'A procurar empresa...';
  empresaInput.disabled    = true;

  fetch(`./get_company_by_nif.php?nif=${encodeURIComponent(nif)}`)
    .then(res => res.json())
    .then(json => {
      empresaInput.disabled    = false;
      empresaInput.placeholder = original;

      if (json.found && json.nome_empresa) {
        empresaInput.value = json.nome_empresa;

        if (json.id_categoria) {
          const sel = document.getElementById('categoria');
          if (sel) sel.value = json.id_categoria;
        }

        if (json.source && json.source !== 'cache') {
          const prev = document.getElementById('nifSourceBadge');
          if (prev) prev.remove();
          const badge = document.createElement('small');
          badge.id          = 'nifSourceBadge';
          badge.className   = 'text-success d-block mt-1';
          badge.textContent = 'Encontrado via ' + (json.fonte || json.source);
          empresaInput.parentNode.appendChild(badge);
        }
      } else {
        empresaInput.placeholder = 'Empresa nao encontrada - preenche manualmente';
      }
    })
    .catch(() => {
      empresaInput.disabled    = false;
      empresaInput.placeholder = original;
    });
}

// ─── GUARDAR NIF MANUALMENTE ─────────────────────────────────────────────────
function saveCompanyNif(nif, nome, idCategoria) {
  if (!/^\d{9}$/.test(nif) || !nome) return;
  fetch('./save_company_nif.php', {
    method: 'POST',
    body: new URLSearchParams({ nif, nome_empresa: nome, id_categoria: idCategoria || '' })
  }).catch(() => {});
}

// ─── EVENTOS ──────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('btnIniciarQR').addEventListener('click', startQR);
  document.getElementById('btnPararQR').addEventListener('click', stopQR);

  const nifInput = document.getElementById('nif');
  if (nifInput) {
    let nifTimer;
    nifInput.addEventListener('input', () => {
      clearTimeout(nifTimer);
      if (nifInput.value.length === 9) {
        nifTimer = setTimeout(() => fetchCompanyByNif(nifInput.value), 400);
      }
    });
    nifInput.addEventListener('blur', () => {
      if (nifInput.value.length === 9) fetchCompanyByNif(nifInput.value);
    });
  }

  const form = document.getElementById('faturaForm');
  if (form) {
    form.addEventListener('submit', () => {
      saveCompanyNif(
        document.getElementById('nif')?.value,
        document.getElementById('empresa')?.value,
        document.getElementById('categoria')?.value
      );
    });
  }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

