<?php
// site/pages/scan_fatura.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/theme_helper.php';
require_once __DIR__ . '/../helpers/categories.php';
require_once __DIR__ . '/../helpers/import_rows.php'; // import_amount() e import_date()
$uid = $_SESSION['user_id'] ?? null;
$currentTheme = getUserTheme($pdo, $uid);

$stmt = $pdo->prepare("SELECT id, name, limit_amount, balance FROM cards WHERE user_id = :uid AND active = 1 ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

$categories = array_values(array_filter(array_keys(CATEGORY_STYLES), fn($c) => $c !== 'Sem Categoria'));

// ── Guardar (POST → mensagem na sessão → redirect → GET) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $amount   = import_amount($_POST['amount'] ?? null);
    $date     = import_date($_POST['transaction_date'] ?? null);
    $desc     = trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) ($_POST['description'] ?? '')));
    $desc     = $desc === '' ? 'Fatura' : mb_substr($desc, 0, 255);
    $category = in_array($_POST['category'] ?? '', $categories, true) ? $_POST['category'] : 'Outros';
    $card_id  = owned_card_id($pdo, (int) $uid, $_POST['card_id'] ?? null);
    $nif      = preg_match('/^\d{9}$/', $_POST['nif'] ?? '') ? (int) $_POST['nif'] : null;

    $error = null;
    if ($amount === null)            $error = 'O valor tem de ser um número maior que zero.';
    elseif (!$date)                  $error = 'A data não é válida.';
    elseif ($date > date('Y-m-d'))   $error = 'A data não pode ser no futuro.';
    elseif ($card_id) {
        foreach ($cards as $c) {
            if ((int) $c['id'] === $card_id && $c['limit_amount'] > 0 && $c['balance'] + $amount > $c['limit_amount']) {
                $error = 'Esta despesa passa o limite disponível do cartão ' . $c['name'] . '.';
            }
        }
    }

    if ($error) {
        $_SESSION['flash_scan'] = ['danger', $error];
    } else {
        try {
            $pdo->beginTransaction();
            // O NIF fica na transação do próprio utilizador (source = 'qr'). Assim, da próxima vez,
            // a Finora lembra-se do nome que ELE escreveu, sem mexer em nada dos outros utilizadores.
            $pdo->prepare("INSERT INTO transactions (user_id, card_id, amount, description, category, transaction_date, source, source_id, created_at)
                           VALUES (:uid, :cid, :amt, :desc, :cat, :d, :src, :nif, NOW())")
                ->execute([':uid' => $uid, ':cid' => $card_id, ':amt' => $amount, ':desc' => $desc, ':cat' => $category,
                           ':d' => $date, ':src' => $nif ? 'qr' : null, ':nif' => $nif]);
            if ($card_id) {
                $pdo->prepare("UPDATE cards SET balance = balance + :amt WHERE id = :cid AND user_id = :uid")
                    ->execute([':amt' => $amount, ':cid' => $card_id, ':uid' => $uid]);
            }
            $pdo->commit();
            $_SESSION['flash_scan'] = ['success', "Despesa de " . fmt_eur($amount) . " em $desc guardada."];
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('[scan_fatura] ' . $e->getMessage());
            $_SESSION['flash_scan'] = ['danger', 'Não foi possível guardar. Tenta outra vez.'];
        }
    }
    header('Location: scan_fatura.php');
    exit;
}

[$messageType, $message] = $_SESSION['flash_scan'] ?? ['', ''];
unset($_SESSION['flash_scan']);
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?= $currentTheme ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ler fatura - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="../assets/apple-touch-icon.png">
  <link rel="stylesheet" href="../css/sidebar.css?v=7">
  <style>
    .sc-wrap { max-width: 1000px; margin: 0 auto; padding: 0 0 60px; }
    .sc-head { margin-bottom: 22px; }
    .sc-head h1 { margin: 0 0 4px; font-family: var(--fn-font-brand); font-weight: 700; font-size: 30px; letter-spacing: -0.6px; color: var(--fn-ink); }
    .sc-head p { margin: 0; font-size: 15px; color: var(--fn-muted); }

    .sc-flash { display: flex; gap: 10px; align-items: center; padding: 12px 16px; border-radius: 12px; margin-bottom: 22px; font-size: 14px; border: 1px solid transparent; }
    .sc-flash.success { background: #ecfdf3; border-color: #abefc6; color: #067647; }
    .sc-flash.danger  { background: #fef3f2; border-color: #fecdca; color: #b42318; }
    [data-theme="dark"] .sc-flash.success { background: rgba(117,224,167,.1); border-color: rgba(117,224,167,.25); color: #75e0a7; }
    [data-theme="dark"] .sc-flash.danger  { background: rgba(253,162,155,.1); border-color: rgba(253,162,155,.25); color: #fda29b; }
    .sc-flash a { margin-left: auto; color: inherit; font-weight: 700; white-space: nowrap; }

    .sc-grid { display: grid; grid-template-columns: 380px minmax(0, 1fr); gap: 20px; align-items: start; }
    .sc-card { background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 16px; padding: 20px; }
    .sc-card h2 { margin: 0; font-size: 17px; font-weight: 700; color: var(--fn-text); }
    .sc-title { display: flex; align-items: center; gap: 12px; margin: -20px -20px 18px; padding: 16px 20px; border-bottom: 1px solid var(--fn-line);
      background: color-mix(in srgb, var(--tone) 6%, var(--fn-surface)); border-radius: 16px 16px 0 0; }
    .main-content .sc-title .pg-ico { background: var(--tone); color: #fff; box-shadow: 0 4px 12px color-mix(in srgb, var(--tone) 30%, transparent); }

    /* Câmara / imagem */
    .sc-switch { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; padding: 4px; border-radius: 12px; background: var(--fn-paper); border: 1px solid var(--fn-line); margin-bottom: 14px; }
    .sc-switch button { border: 0; background: transparent; border-radius: 9px; padding: 8px; font-size: 14px; font-weight: 600; color: var(--fn-muted); display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
    .sc-switch button[aria-pressed="true"] { background: var(--fn-surface); color: var(--fn-text); box-shadow: 0 1px 2px rgba(20, 33, 61, .12); }
    .sc-switch button:focus-visible { outline: 2px solid var(--fn-gold); outline-offset: 2px; }

    /* O visor: moldura de "papel de fatura" com o QR em fundo enquanto a câmara está desligada */
    .sc-viewer { position: relative; aspect-ratio: 1; border-radius: 14px; overflow: hidden; background: #0e1420; }
    .sc-viewer video { width: 100%; height: 100%; object-fit: cover; display: block; }
    .sc-frame { position: absolute; inset: 18%; border-radius: 14px; box-shadow: 0 0 0 999px rgba(14, 20, 32, .45); pointer-events: none; }
    .sc-frame::before, .sc-frame::after { content: ""; position: absolute; width: 28px; height: 28px; border: 3px solid var(--fn-gold); }
    .sc-frame::before { top: -3px; left: -3px; border-right: 0; border-bottom: 0; border-radius: 12px 0 0 0; }
    .sc-frame::after  { bottom: -3px; right: -3px; border-left: 0; border-top: 0; border-radius: 0 0 12px 0; }
    .sc-idle { position: absolute; inset: 0; display: grid; place-items: center; align-content: center; gap: 14px; text-align: center; padding: 20px; color: #c9d1e3; }
    .sc-idle i { font-size: 44px; color: var(--fn-gold); }
    .sc-idle p { margin: 0; font-size: 14px; max-width: 26ch; }
    .sc-viewer.live .sc-idle { display: none; }
    .sc-viewer:not(.live) .sc-frame { display: none; }

    .sc-drop { display: grid; place-items: center; align-content: center; gap: 8px; aspect-ratio: 1; border: 2px dashed var(--fn-line); border-radius: 14px; text-align: center; padding: 20px; cursor: pointer; margin: 0; position: relative; transition: border-color .15s, background .15s; }
    .sc-drop:hover, .sc-drop.over { border-color: var(--fn-accent); background: rgba(var(--fn-accent-rgb), .05); }
    .sc-drop:focus-within { outline: 2px solid var(--fn-gold); outline-offset: 2px; }
    .sc-drop i { font-size: 34px; color: var(--cat-1); }
    .sc-drop b { font-size: 15px; color: var(--fn-text); }
    .sc-drop span { font-size: 13px; color: var(--fn-muted); }
    .sc-drop input { position: absolute; width: 1px; height: 1px; opacity: 0; }

    .sc-status { margin: 12px 0 0; min-height: 20px; font-size: 14px; color: var(--fn-muted); text-align: center; }
    .sc-status.warn { color: #b54708; }
    [data-theme="dark"] .sc-status.warn { color: #fedf89; }
    .sc-cam-btn { display: flex; justify-content: center; margin-top: 12px; }

    /* Formulário da despesa */
    .sc-form .form-label { font-size: 14px; font-weight: 600; margin-bottom: 6px; color: var(--fn-text); }
    .sc-form .form-control, .sc-form .form-select { min-height: 44px; border-radius: 10px; border: 1px solid var(--fn-line); background-color: var(--fn-surface); color: var(--fn-text); }
    .sc-form .form-control:focus, .sc-form .form-select:focus { border-color: var(--fn-accent); box-shadow: 0 0 0 3px rgba(var(--fn-accent-rgb), .18); background-color: var(--fn-surface); color: var(--fn-text); }
    .sc-form .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .sc-form .mb { margin-bottom: 14px; }
    .sc-amount { font-family: var(--fn-font-brand); font-size: 22px !important; font-weight: 700; }
    .sc-nif { display: flex; align-items: center; gap: 6px; margin-top: 6px; font-size: 13px; color: var(--fn-muted); min-height: 18px; }
    .sc-nif .ok { color: #067647; } [data-theme="dark"] .sc-nif .ok { color: #75e0a7; }
    .sc-nif .warn { color: #b54708; } [data-theme="dark"] .sc-nif .warn { color: #fedf89; }
    .sc-read { display: none; align-items: center; gap: 8px; padding: 10px 12px; margin-bottom: 16px; border-radius: 10px; font-size: 14px; background: var(--fn-gold-soft); color: var(--fn-text); }
    .sc-read.show { display: flex; }
    .sc-read i { color: var(--fn-gold); }
    .sc-acts { display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; margin-top: 6px; }
    .sc-raw summary { font-size: 13px; color: var(--fn-muted); cursor: pointer; }
    .sc-raw pre { margin: 8px 0 0; padding: 10px; border-radius: 8px; background: var(--fn-paper); font-size: 12px; color: var(--fn-muted); white-space: pre-wrap; word-break: break-all; }

    @media (max-width: 860px) {
      .sc-grid { grid-template-columns: minmax(0, 1fr); }
      .sc-viewer, .sc-drop { max-width: 380px; margin-left: auto; margin-right: auto; width: 100%; }
    }
    @media (max-width: 520px) {
      .sc-head h1 { font-size: 26px; }
      .sc-form .row2 { grid-template-columns: 1fr; }
    }
  </style>
  <link rel="stylesheet" href="../css/finora.css?v=8">
</head>
<body>
<div class="app-layout">
<?php
$activePage    = 'scan_fatura';
$sidebarPrefix = '../';
include __DIR__ . '/../components/sidebar.php';
?>
<main class="main-content">
<div class="page-content">
<div class="sc-wrap">

  <div class="sc-head">
    <h1>Ler fatura</h1>
    <p>Lê o código QR de uma fatura portuguesa e a Finora preenche a despesa por ti.</p>
  </div>

  <?php if ($message): ?>
    <div class="sc-flash <?= htmlspecialchars($messageType) ?>" role="<?= $messageType === 'danger' ? 'alert' : 'status' ?>">
      <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
      <span><?= htmlspecialchars($message) ?></span>
      <?php if ($messageType === 'success'): ?><a href="transactions.php">Ver transações</a><?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="sc-grid">
    <!-- LEITOR -->
    <section class="sc-card" aria-labelledby="scTitle">
      <div class="sc-title" style="--tone:var(--cat-4)"><span class="pg-ico" aria-hidden="true"><i class="bi bi-qr-code-scan"></i></span><h2 id="scTitle">Código QR</h2></div>
      <div class="sc-switch" role="group" aria-label="Como ler o código">
        <button type="button" id="modeCam" aria-pressed="true"><i class="bi bi-camera"></i> Câmara</button>
        <button type="button" id="modeImg" aria-pressed="false"><i class="bi bi-image"></i> Imagem</button>
      </div>

      <div id="panelCam">
        <div class="sc-viewer" id="viewer">
          <video id="video" playsinline muted></video>
          <div class="sc-frame" aria-hidden="true"></div>
          <div class="sc-idle">
            <i class="bi bi-qr-code-scan" aria-hidden="true"></i>
            <p>O código QR está normalmente no fundo da fatura, junto ao total.</p>
          </div>
        </div>
        <div class="sc-cam-btn">
          <button type="button" class="btn-s btn-s-primary" id="camBtn"><i class="bi bi-camera-video"></i> Ligar câmara</button>
        </div>
      </div>

      <div id="panelImg" hidden>
        <label class="sc-drop" id="drop">
          <i class="bi bi-image" aria-hidden="true"></i>
          <b>Escolhe ou arrasta uma foto</b>
          <span>Uma foto nítida do código QR</span>
          <input type="file" id="imgFile" accept="image/*">
        </label>
      </div>

      <p class="sc-status" id="status" role="status"></p>
      <canvas id="canvas" hidden></canvas>
    </section>

    <!-- DESPESA -->
    <section class="sc-card" aria-labelledby="formTitle">
      <div class="sc-title" style="--tone:var(--cat-1)"><span class="pg-ico" aria-hidden="true"><i class="bi bi-receipt"></i></span><h2 id="formTitle">Despesa</h2></div>
      <div class="sc-read" id="readOk"><i class="bi bi-check-circle-fill"></i> Fatura lida. Confirma os dados e guarda.</div>

      <form method="post" class="sc-form" id="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="nif" id="fNif">

        <div class="row2 mb">
          <div>
            <label class="form-label" for="fAmount">Valor (€)</label>
            <input type="text" inputmode="decimal" class="form-control sc-amount" name="amount" id="fAmount" required placeholder="0,00" autocomplete="off">
          </div>
          <div>
            <label class="form-label" for="fDate">Data</label>
            <input type="date" class="form-control" name="transaction_date" id="fDate" required value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
          </div>
        </div>

        <div class="mb">
          <label class="form-label" for="fDesc">Onde</label>
          <input type="text" class="form-control" name="description" id="fDesc" maxlength="255" placeholder="Nome da loja ou empresa">
          <div class="sc-nif" id="nifInfo" aria-live="polite"></div>
        </div>

        <div class="row2 mb">
          <div>
            <label class="form-label" for="fCat">Categoria</label>
            <select class="form-select" name="category" id="fCat">
              <?php foreach ($categories as $cat): ?>
                <option <?= $cat === 'Outros' ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label" for="fCard">Cartão</label>
            <select class="form-select" name="card_id" id="fCard">
              <option value="">Sem cartão</option>
              <?php foreach ($cards as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <details class="sc-raw mb" id="rawWrap" hidden>
          <summary>Ver o conteúdo do código QR</summary>
          <pre id="raw"></pre>
        </details>

        <div class="sc-acts">
          <button type="button" class="btn-s" id="resetBtn">Limpar</button>
          <button type="submit" class="btn-s btn-s-primary"><i class="bi bi-check-lg"></i> Guardar despesa</button>
        </div>
      </form>
    </section>
  </div>

</div>
</div>
</main>
</div>

<!-- integrity: se o ficheiro no CDN for alterado, o browser recusa-o (proteção contra ataques à cadeia de fornecimento) -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"
        integrity="sha384-hStSInNIZ8ljtOVrmrgf7zdHMapaLBWoSnPTtF0nzsybp4+LuhDz6sHuEVpWIX8o" crossorigin="anonymous"></script>
<script>
'use strict';
const $ = id => document.getElementById(id);
const CATEGORIES = <?= json_encode($categories, JSON_UNESCAPED_UNICODE) ?>;
let stream = null, timer = null;

function setStatus(text, warn = false) { $('status').textContent = text; $('status').classList.toggle('warn', warn); }

// ── Câmara (só liga quando a pessoa pede: o pedido de permissão aparece no momento certo) ──
// O nome do erro diz porque é que a câmara falhou; cada caso tem uma solução diferente
function cameraProblem(e) {
  if (!window.isSecureContext || !navigator.mediaDevices) {
    return 'A câmara só funciona em páginas seguras (https ou localhost). Escolhe "Imagem" e usa uma foto.';
  }
  switch (e && e.name) {
    case 'NotAllowedError':
      return 'Sem permissão para a câmara. Carrega no ícone à esquerda do endereço e permite a câmara. '
           + 'No Mac, confirma também em Definições do Sistema › Privacidade e segurança › Câmara que o browser está ligado.';
    case 'NotFoundError':
    case 'OverconstrainedError':
      return 'Não foi encontrada nenhuma câmara neste dispositivo. Escolhe "Imagem" e usa uma foto.';
    case 'NotReadableError':
    case 'AbortError':
      return 'A câmara está a ser usada por outra aplicação (FaceTime, Teams, Zoom…). Fecha-a e tenta outra vez.';
    default:
      return 'Não foi possível usar a câmara. Escolhe "Imagem" e usa uma foto.';
  }
}

async function startCamera() {
  try {
    if (!window.isSecureContext || !navigator.mediaDevices) throw new Error('insecure');
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    $('video').srcObject = stream;
    await $('video').play();
    $('viewer').classList.add('live');
    $('camBtn').innerHTML = '<i class="bi bi-camera-video-off"></i> Desligar câmara';
    setStatus('Aponta para o código QR.');
    timer = setInterval(scanVideo, 250);
  } catch (e) {
    stopCamera();
    console.warn('[câmara]', e && e.name, e && e.message);
    setStatus(cameraProblem(e), true);
  }
}
function stopCamera() {
  clearInterval(timer);
  if (stream) stream.getTracks().forEach(t => t.stop());
  stream = null;
  $('viewer').classList.remove('live');
  $('camBtn').innerHTML = '<i class="bi bi-camera-video"></i> Ligar câmara';
}
$('camBtn').addEventListener('click', () => stream ? (stopCamera(), setStatus('')) : startCamera());

function scanVideo() {
  const v = $('video');
  if (v.readyState !== v.HAVE_ENOUGH_DATA) return;
  const code = readCanvas(v, v.videoWidth, v.videoHeight, 'dontInvert');
  if (code) { stopCamera(); handleQR(code); }
}

// ── Imagem ──
function readImage(file) {
  if (!file || !file.type.startsWith('image/')) { setStatus('Escolhe um ficheiro de imagem.', true); return; }
  const img = new Image();
  img.onload = () => {
    // Imagens enormes ficam mais pequenas: o QR continua legível e o browser não bloqueia
    const scale = Math.min(1, 1600 / Math.max(img.width, img.height));
    const code = readCanvas(img, Math.round(img.width * scale), Math.round(img.height * scale), 'attemptBoth');
    URL.revokeObjectURL(img.src);
    code ? handleQR(code) : setStatus('Não encontrei um código QR nesta imagem. Tenta uma foto mais nítida.', true);
  };
  img.onerror = () => setStatus('Não foi possível abrir esta imagem.', true);
  img.src = URL.createObjectURL(file);
}
function readCanvas(src, w, h, inversion) {
  const c = $('canvas'); c.width = w; c.height = h;
  const ctx = c.getContext('2d', { willReadFrequently: true });
  ctx.drawImage(src, 0, 0, w, h);
  const r = jsQR(ctx.getImageData(0, 0, w, h).data, w, h, { inversionAttempts: inversion });
  return r ? r.data : null;
}
$('imgFile').addEventListener('change', e => readImage(e.target.files[0]));
const drop = $('drop');
drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('over'); });
drop.addEventListener('dragleave', () => drop.classList.remove('over'));
drop.addEventListener('drop', e => { e.preventDefault(); drop.classList.remove('over'); readImage(e.dataTransfer.files[0]); });

function setMode(cam) {
  $('modeCam').setAttribute('aria-pressed', cam); $('modeImg').setAttribute('aria-pressed', !cam);
  $('panelCam').hidden = !cam; $('panelImg').hidden = cam;
  if (!cam) stopCamera();
  setStatus('');
}
$('modeCam').addEventListener('click', () => setMode(true));
$('modeImg').addEventListener('click', () => setMode(false));

// ── Ler o QR da AT: A:NIF*B:...*F:AAAAMMDD*...*O:total ──
// Tudo o que vem do QR é texto de fora (qualquer pessoa pode imprimir um QR). Por isso cada campo
// é validado e só é posto na página com textContent / .value — nunca com innerHTML.
function handleQR(raw) {
  const f = {};
  raw.split('*').forEach(part => { const i = part.indexOf(':'); if (i > 0) f[part.slice(0, i)] = part.slice(i + 1); });

  $('raw').textContent = raw;
  $('rawWrap').hidden = false;

  const isAT = /^\d{9}$/.test(f.A || '') && /^\d{8}$/.test(f.F || '');
  if (!isAT) {
    setStatus('Este código QR não é de uma fatura portuguesa. Preenche a despesa à mão.', true);
    return;
  }

  const total = /^\d+(\.\d{1,2})?$/.test(f.O || '') ? Number(f.O) : null;
  if (total) $('fAmount').value = total.toFixed(2).replace('.', ',');
  const iso = f.F.slice(0, 4) + '-' + f.F.slice(4, 6) + '-' + f.F.slice(6, 8);
  if (!isNaN(Date.parse(iso)) && iso <= $('fDate').max) $('fDate').value = iso;
  $('fNif').value = f.A;
  $('readOk').classList.add('show');
  setStatus('');
  lookupNIF(f.A);
  if (window.matchMedia('(max-width: 860px)').matches) $('form').scrollIntoView({ behavior: 'smooth' });
}

function nifLine(icon, cls, text) {
  const box = $('nifInfo');
  box.replaceChildren();
  const i = document.createElement('i'); i.className = 'bi bi-' + icon + ' ' + cls;
  const s = document.createElement('span'); s.textContent = text;
  box.append(i, s);
}

async function lookupNIF(nif) {
  nifLine('hourglass-split', '', 'A procurar o NIF ' + nif + '…');
  try {
    const r = await fetch('get_company_by_nif.php?nif=' + encodeURIComponent(nif));
    if (r.status === 429) return nifLine('exclamation-circle', 'warn', 'Muitas pesquisas seguidas. Escreve o nome à mão.');
    const d = await r.json();
    if (!d.found) return nifLine('exclamation-circle', 'warn', 'NIF ' + nif + ' sem nome conhecido. Escreve onde foi.');
    $('fDesc').value = d.nome;
    nifLine('check-circle', 'ok', 'NIF ' + nif + (d.source === 'teu' ? ' · o nome que usaste da última vez' : ''));
    if (CATEGORIES.includes(d.categoria)) $('fCat').value = d.categoria;
  } catch (e) {
    nifLine('exclamation-circle', 'warn', 'Não foi possível procurar o NIF. Escreve onde foi.');
  }
}

$('resetBtn').addEventListener('click', () => {
  $('form').reset();
  $('fNif').value = '';
  $('nifInfo').replaceChildren();
  $('raw').textContent = '';
  $('rawWrap').hidden = true;
  $('readOk').classList.remove('show');
  $('imgFile').value = '';
  setStatus('');
});
</script>
</body>
</html>
