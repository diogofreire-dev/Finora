<?php
// site/pages/invoices.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

$message = '';
$messageType = 'info';

// Buscar cartões do utilizador para associar faturas
$stmt = $pdo->prepare("SELECT id, name FROM cards WHERE user_id = :uid AND active = 1 ORDER BY name");
$stmt->execute([':uid' => $uid]);
$cards = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['total'] ?? 0);
    $description = trim($_POST['empresa'] ?? 'Fatura');
    $category = 'Fatura';
    $card_id = !empty($_POST['card_id']) ? intval($_POST['card_id']) : null;
    $transaction_date = trim($_POST['data'] ?? date('Y-m-d'));
    $nif = trim($_POST['nif'] ?? '');

    if ($amount <= 0) {
        $message = 'Valor inválido. Coloque um valor maior que zero.';
        $messageType = 'danger';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transaction_date)) {
        $message = 'Data inválida. Use o formato aa-mm-dd.';
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

            if (preg_match('/^\d{9}$/', $nif) && !empty($description)) {
                $stmt = $pdo->prepare("INSERT INTO nif_empresas (nif, nome_empresa) VALUES (:nif, :nome) ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa)");
                $stmt->execute([':nif' => $nif, ':nome' => $description]);
            }

            $pdo->commit();
            $message = 'Fatura guardada como transação com sucesso.';
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
  <script src="https://unpkg.com/html5-qrcode"></script>
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

      <div class="card mb-4">
        <div class="card-body">
          <div class="row gy-3">
            <div class="col-md-6">
              <h5>📷 Ler QR</h5>
              <div id="reader" style="width:100%; max-width:400px; min-height:250px; background:#f8f9fa; border-radius:12px; padding:6px"></div>
              <button type="button" class="btn btn-primary btn-sm mt-2" onclick="startQR()"><i class="bi bi-camera"></i> Iniciar leitura QR</button>
            </div>
            <div class="col-md-6">
              <h5>🧾 OCR da imagem</h5>
              <p class="text-muted small">Carrega uma foto ou PDF de fatura para texto.</p>
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
              <div class="col-md-2">
                <label class="form-label">Total</label>
                <input id="total" name="total" type="text" class="form-control" placeholder="0.00" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Data</label>
                <input id="data" name="data" type="date" class="form-control" value="<?=date('Y-m-d')?>" required>
              </div>
              <div class="col-md-6">
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
// ─────────────────────────────────────────────────────────────────────────────
// QR CODE — formato AT Portugal
// Campos oficiais: A=NIF emitente, B=NIF adquirente, C=país, D=tipo doc,
// E=estado, F=data(YYYYMMDD), G=nº único doc, H=ATCUD, I1..I8=bases IVA,
// N=total impostos, O=TOTAL A PAGAR, P=retenções, Q=hash(4 chars), R=cert
// ─────────────────────────────────────────────────────────────────────────────
function parseQR(qr) {
  const fields = {};

  // Divide por * mas preserva valores que possam ter : (ex: datas, URLs)
  qr.split('*').forEach(segment => {
    const colonIdx = segment.indexOf(':');
    if (colonIdx === -1) return;
    const key   = segment.substring(0, colonIdx).trim();
    const value = segment.substring(colonIdx + 1).trim();
    if (key) fields[key] = value;
  });

  // Converte data YYYYMMDD → YYYY-MM-DD
  let dataFormatada = '';
  if (fields['F'] && /^\d{8}$/.test(fields['F'])) {
    const d = fields['F'];
    dataFormatada = `${d.slice(0,4)}-${d.slice(4,6)}-${d.slice(6,8)}`;
  }

  // Total: campo O = total a pagar (com IVA). Fallback para N (total impostos + base)
  const totalRaw = fields['O'] || fields['N'] || '';
  const total = totalRaw.replace(',', '.');

  return {
    nif:    fields['A'] || '',   // NIF do emitente
    empresa: '',                  // Não está no QR — será buscado pela BD via NIF
    data:   dataFormatada,
    numero: fields['G'] || '',   // Nº único do documento (ex: FT 2024/123)
    total:  total
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// OCR — extração robusta de campos de faturas PT
// ─────────────────────────────────────────────────────────────────────────────

// Extrai o TOTAL com múltiplas estratégias, por ordem de fiabilidade
function extractTotal(text) {
  const strategies = [
    // 1. "Total a pagar" / "A pagar" — mais fiável
    /(?:total\s*a\s*pagar|a\s*pagar)\s*[:\-]?\s*(\d{1,6}[.,]\d{2})\s*€?/i,
    // 2. "Total" sozinho com € obrigatório — evita apanhar nº de fatura
    /total\s*[:\-]?\s*€\s*(\d{1,6}[.,]\d{2})/i,
    /total\s*[:\-]?\s*(\d{1,6}[.,]\d{2})\s*€/i,
    // 3. € à esquerda do valor
    /€\s*(\d{1,6}[.,]\d{2})(?!\d)/,
    // 4. Valor com € à direita (garante que não é dentro de um nº maior)
    /(?<!\d)(\d{1,6}[.,]\d{2})\s*€/,
    // 5. EUR explícito
    /EUR\s*(\d{1,6}[.,]\d{2})/i,
    /(\d{1,6}[.,]\d{2})\s*EUR/i,
  ];

  for (const pattern of strategies) {
    const m = text.match(pattern);
    if (m) return m[1];
  }
  return null;
}

// Normaliza data para YYYY-MM-DD a partir de vários formatos encontrados por OCR
function normalizeDate(raw) {
  if (!raw) return null;
  raw = raw.trim();

  // DD/MM/YYYY ou DD-MM-YYYY ou DD.MM.YYYY
  let m = raw.match(/^(\d{2})[\/\-\.](\d{2})[\/\-\.](\d{4})$/);
  if (m) return `${m[3]}-${m[2]}-${m[1]}`;

  // YYYY-MM-DD já correto
  m = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (m) return raw;

  // YYYYMMDD (QR)
  m = raw.match(/^(\d{4})(\d{2})(\d{2})$/);
  if (m) return `${m[1]}-${m[2]}-${m[3]}`;

  // DD/MM/YY
  m = raw.match(/^(\d{2})[\/\-\.](\d{2})[\/\-\.](\d{2})$/);
  if (m) return `20${m[3]}-${m[2]}-${m[1]}`;

  return null;
}

// Extrai o NIF — 9 dígitos, tipicamente após "NIF", "NIPC", "Contribuinte"
function extractNif(text) {
  // Com prefixo explícito (mais fiável)
  let m = text.match(/(?:NIF|NIPC|Contribuinte|N\.I\.F\.?)\s*[:\-]?\s*(\d{9})(?!\d)/i);
  if (m) return m[1];

  // 9 dígitos sozinhos numa linha (possível NIF)
  m = text.match(/(?:^|\s)(\d{9})(?!\d)/m);
  if (m) return m[1];

  return null;
}

// Extrai nome de empresa — várias heurísticas
function extractEmpresa(text) {
  // Após label explícita
  let m = text.match(/(?:Empresa|Emitente|Razão\s*Social|Fornecedor)\s*[:\-]\s*([^\n\r]{3,60})/i);
  if (m) return m[1].trim();

  // Primeira linha não vazia com maiúsculas (típico cabeçalho de fatura)
  const lines = text.split(/[\n\r]+/).map(l => l.trim()).filter(Boolean);
  for (const line of lines.slice(0, 6)) {
    // Linha com pelo menos 3 palavras e maiúsculas
    if (/[A-ZÁÉÍÓÚÀÃÂÊÔÇÜ]{3,}/.test(line) && line.length > 5 && !/^\d/.test(line)) {
      return line;
    }
  }
  return null;
}

// Extrai número de fatura
function extractNumero(text) {
  // Formatos: FT 2024/1, FR2024/123, FS 24/1, etc.
  let m = text.match(/(?:Fatura|Documento|Recibo|Doc\.?|Ref\.?)\s*[nNºN°\.]?\s*[:\-]?\s*([A-Z]{1,4}[\s\/\-]?\d{2,4}[\s\/\-]\d{1,6})/i);
  if (m) return m[1].trim();

  m = text.match(/\b(F[TRS][\s\/]?\d{4}[\s\/]\d{1,6})\b/i);
  if (m) return m[1].trim();

  return null;
}

// Extrai data — múltiplos formatos
function extractDate(text) {
  const patterns = [
    // Com prefixo
    /(?:Data|Date|Emitido\s*em|Emissão)\s*[:\-]?\s*(\d{2}[\/\-\.]\d{2}[\/\-\.]\d{4})/i,
    /(?:Data|Date|Emitido\s*em|Emissão)\s*[:\-]?\s*(\d{4}[\/\-\.]\d{2}[\/\-\.]\d{2})/i,
    // Sem prefixo — DD/MM/YYYY isolado
    /(?<!\d)(\d{2}\/\d{2}\/\d{4})(?!\d)/,
    /(?<!\d)(\d{2}-\d{2}-\d{4})(?!\d)/,
  ];

  for (const p of patterns) {
    const m = text.match(p);
    if (m) {
      const normalized = normalizeDate(m[1]);
      if (normalized) return normalized;
    }
  }
  return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// FORMULÁRIO
// ─────────────────────────────────────────────────────────────────────────────
function preencherForm(dados) {
  if (dados.nif)    document.getElementById('nif').value    = dados.nif;
  if (dados.empresa) document.getElementById('empresa').value = dados.empresa;
  if (dados.total)  document.getElementById('total').value  = dados.total;
  if (dados.data)   document.getElementById('data').value   = dados.data;
  if (dados.numero) document.getElementById('numero').value = dados.numero;
}

// ─────────────────────────────────────────────────────────────────────────────
// SCANNER QR
// ─────────────────────────────────────────────────────────────────────────────
let scanner;
function startQR() {
  if (scanner) scanner.clear().catch(() => {});
  scanner = new Html5Qrcode("reader");
  scanner.start(
    { facingMode: "environment" },
    { fps: 10, qrbox: 250 },
    (decodedText) => {
      const dados = parseQR(decodedText);
      preencherForm(dados);
      // Após QR, busca nome pela BD via NIF (QR não tem nome)
      if (dados.nif) fetchCompanyByNif(dados.nif);
      scanner.stop().then(() => scanner.clear());
    },
    () => {} // ignorar erros de frame
  ).catch(err => console.error('QR scanner error:', err));
}

// ─────────────────────────────────────────────────────────────────────────────
// OCR
// ─────────────────────────────────────────────────────────────────────────────
async function uploadImagem() {
  const file = document.getElementById('upload').files[0];
  if (!file) return;
  const status = document.getElementById('ocrStatus');
  status.textContent = '⏳ A processar OCR...';

  try {
    const { data: { text } } = await Tesseract.recognize(file, 'por+eng', {
      logger: m => {
        if (m.status === 'recognizing text') {
          status.textContent = `⏳ OCR: ${Math.round(m.progress * 100)}%`;
        }
      }
    });

    const nif     = extractNif(text);
    const empresa = extractEmpresa(text);
    const total   = extractTotal(text);
    const data    = extractDate(text);
    const numero  = extractNumero(text);

    preencherForm({
      nif:    nif    || document.getElementById('nif').value,
      empresa: empresa || document.getElementById('empresa').value,
      total:  total  ? total.replace(',', '.') : document.getElementById('total').value,
      data:   data   || document.getElementById('data').value,
      numero: numero || document.getElementById('numero').value
    });

    // Se encontrou NIF, tenta buscar empresa na BD
    if (nif) fetchCompanyByNif(nif);

    status.textContent = '✅ OCR concluído. Confirme os dados e guarde.';
  } catch (err) {
    console.error('OCR error:', err);
    status.textContent = '❌ Erro no OCR. Tente com uma imagem mais nítida.';
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// NIF → EMPRESA (BD)
// ─────────────────────────────────────────────────────────────────────────────
function fetchCompanyByNif(nif) {
  if (!/^\d{9}$/.test(nif)) return;

  const empresaInput = document.getElementById('empresa');

  fetch(`get_company_by_nif.php?nif=${encodeURIComponent(nif)}`)
    .then(res => res.json())
    .then(json => {
      if (!json.found || !json.nome_empresa) return;

      if (!empresaInput.value.trim()) {
        // Campo vazio — preenche diretamente
        empresaInput.value = json.nome_empresa;
        return;
      }
      if (empresaInput.value.trim() !== json.nome_empresa) {
        // Já tem valor diferente — pergunta
        if (confirm(`Nome na base de dados: "${json.nome_empresa}"\nNome atual: "${empresaInput.value.trim()}"\n\nSubstituir?`)) {
          empresaInput.value = json.nome_empresa;
        }
      }
    })
    .catch(err => console.error('Erro get_company_by_nif:', err));
}

// ─────────────────────────────────────────────────────────────────────────────
// EVENTOS
// ─────────────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  const nifInput    = document.getElementById('nif');
  const empresaInput = document.getElementById('empresa');
  const form        = document.getElementById('faturaForm');

  // Blur no NIF → consulta BD
  nifInput.addEventListener('blur', function () {
    fetchCompanyByNif(this.value.trim());
  });

  // Submit → guarda NIF+Empresa na BD em background
  form.addEventListener('submit', function () {
    const nif    = nifInput.value.trim();
    const empresa = empresaInput.value.trim();
    if (!/^\d{9}$/.test(nif) || !empresa) return;

    const payload = new FormData();
    payload.append('nif', nif);
    payload.append('nome_empresa', empresa);

    fetch('save_company_nif.php', { method: 'POST', body: payload })
      .then(res => res.json())
      .then(data => { if (!data.success) console.error('Erro save_company_nif:', data.error); })
      .catch(err => console.error('Erro save_company_nif:', err));
  });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
