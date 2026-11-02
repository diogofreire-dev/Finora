<?php
// site/pages/ai.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?= $currentTheme ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>IA Financeira - FreeCard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg-primary); color: var(--text-primary); }

    .ai-wrapper { max-width: 860px; margin: 0 auto; padding: 0 16px 60px; }

    /* Cabeçalho */
    .page-header { display: flex; align-items: center; gap: 14px; margin-bottom: 28px; padding-top: 8px; }
    .page-header-icon { width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg,#3498db,#9b59b6); display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff; flex-shrink: 0; }
    .page-header h2 { font-size: 20px; font-weight: 600; margin: 0 0 2px; }
    .page-header p { font-size: 13px; color: var(--text-secondary); margin: 0; }

    /* Cards genéricos */
    .ai-card { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 14px; overflow: hidden; margin-bottom: 16px; }
    .ai-card-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border-color); }
    .ai-card-title { font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; margin: 0; }
    .ai-card-title i { font-size: 16px; }
    .ai-card-body { padding: 20px; }

    /* Botões de ação */
    .btn-ai { font-size: 13px; font-weight: 500; padding: 7px 16px; border-radius: 9px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all .15s; }
    .btn-ai-primary { background: #3498db; color: #fff; }
    .btn-ai-primary:hover { background: #2980b9; }
    .btn-ai-ghost { background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); }
    .btn-ai-ghost:hover { border-color: #999; }

    /* Área de resposta da IA */
    .ai-response { font-size: 14px; line-height: 1.7; color: var(--text-primary); white-space: pre-wrap; min-height: 48px; }
    .ai-response.loading { color: var(--text-secondary); }
    .ai-response.error { color: #e74c3c; }

    /* Pulsação enquanto carrega */
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
    .ai-loading-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #3498db; animation: pulse 1.2s ease-in-out infinite; margin: 0 2px; }
    .ai-loading-dot:nth-child(2) { animation-delay: .2s; }
    .ai-loading-dot:nth-child(3) { animation-delay: .4s; }

    /* Chat */
    .chat-messages { display: flex; flex-direction: column; gap: 12px; max-height: 420px; overflow-y: auto; padding: 4px 0; }
    .chat-msg { display: flex; gap: 10px; align-items: flex-start; }
    .chat-msg.user { flex-direction: row-reverse; }
    .chat-avatar { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
    .chat-avatar.ai { background: linear-gradient(135deg,#3498db,#9b59b6); color: #fff; }
    .chat-avatar.user { background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-secondary); }
    .chat-bubble { max-width: 80%; padding: 10px 14px; border-radius: 12px; font-size: 13px; line-height: 1.6; white-space: pre-wrap; }
    .chat-bubble.ai { background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary); border-top-left-radius: 4px; }
    .chat-bubble.user { background: #3498db; color: #fff; border-top-right-radius: 4px; }

    /* Input do chat */
    .chat-input-row { display: flex; gap: 8px; margin-top: 16px; }
    .chat-input { flex: 1; font-size: 13px; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); outline: none; }
    .chat-input:focus { border-color: #3498db; }
    .chat-input::placeholder { color: var(--text-secondary); }
    .chat-send { width: 40px; height: 40px; border-radius: 10px; border: none; background: #3498db; color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; transition: background .15s; }
    .chat-send:hover { background: #2980b9; }
    .chat-send:disabled { background: var(--border-color); cursor: not-allowed; }

    /* Sugestões rápidas */
    .quick-suggestions { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
    .quick-btn { font-size: 12px; padding: 5px 12px; border-radius: 20px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-secondary); cursor: pointer; transition: all .15s; white-space: nowrap; }
    .quick-btn:hover { border-color: #3498db; color: #3498db; }

    /* Vazio */
    .chat-empty { text-align: center; padding: 28px; color: var(--text-secondary); font-size: 13px; }
    .chat-empty i { font-size: 32px; display: block; margin-bottom: 8px; opacity: .4; }

    @media (max-width: 576px) {
      .chat-bubble { max-width: 90%; }
    }
  </style>
</head>
<body>

<?php
$activePage    = 'ai';
$sidebarPrefix = '../';
require_once __DIR__ . '/../components/sidebar.php';
?>

<div class="main-content">
  <div class="ai-wrapper">

    <!-- Cabeçalho -->
    <div class="page-header">
      <div class="page-header-icon"><i class="bi bi-stars"></i></div>
      <div>
        <h2>Assistente IA</h2>
        <p>Análise inteligente das tuas finanças com base nos teus dados reais</p>
      </div>
    </div>

    <!-- ── INSIGHTS DO MÊS ──────────────────────────────────────────────── -->
    <div class="ai-card">
      <div class="ai-card-header">
        <h3 class="ai-card-title">
          <i class="bi bi-lightbulb" style="color:#f39c12"></i>
          Insights do mês
        </h3>
        <button class="btn-ai btn-ai-ghost" id="btnInsights" onclick="loadInsights()">
          <i class="bi bi-arrow-clockwise"></i> Gerar
        </button>
      </div>
      <div class="ai-card-body">
        <div class="ai-response" id="insightsBox">
          <span style="color:var(--text-secondary);font-size:13px">
            <i class="bi bi-info-circle me-1"></i>
            Clica em "Gerar" para obteres uma análise do teu mês atual.
          </span>
        </div>
      </div>
    </div>

    <!-- ── SUGESTÕES DE POUPANÇA ─────────────────────────────────────────── -->
    <div class="ai-card">
      <div class="ai-card-header">
        <h3 class="ai-card-title">
          <i class="bi bi-piggy-bank" style="color:#1abc9c"></i>
          Sugestões de poupança
        </h3>
        <button class="btn-ai btn-ai-ghost" id="btnSavings" onclick="loadSavings()">
          <i class="bi bi-arrow-clockwise"></i> Analisar
        </button>
      </div>
      <div class="ai-card-body">
        <div class="ai-response" id="savingsBox">
          <span style="color:var(--text-secondary);font-size:13px">
            <i class="bi bi-info-circle me-1"></i>
            Clica em "Analisar" para receberes sugestões personalizadas.
          </span>
        </div>
      </div>
    </div>

    <!-- ── CHAT ──────────────────────────────────────────────────────────── -->
    <div class="ai-card">
      <div class="ai-card-header">
        <h3 class="ai-card-title">
          <i class="bi bi-chat-dots" style="color:#9b59b6"></i>
          Chat financeiro
        </h3>
      </div>
      <div class="ai-card-body">

        <!-- Sugestões rápidas -->
        <div class="quick-suggestions">
          <button class="quick-btn" onclick="sendQuick('Quanto gastei este mês?')">Quanto gastei este mês?</button>
          <button class="quick-btn" onclick="sendQuick('Em que categoria gasto mais?')">Categoria com mais gasto?</button>
          <button class="quick-btn" onclick="sendQuick('Qual foi o meu maior gasto?')">Maior gasto?</button>
          <button class="quick-btn" onclick="sendQuick('Como estou a comparar com o mês passado?')">Vs mês passado?</button>
          <button class="quick-btn" onclick="sendQuick('Que categorias posso cortar para poupar?')">Onde posso cortar?</button>
        </div>

        <!-- Mensagens -->
        <div class="chat-messages" id="chatMessages">
          <div class="chat-empty" id="chatEmpty">
            <i class="bi bi-chat-square-dots"></i>
            Faz uma pergunta sobre as tuas finanças
          </div>
        </div>

        <!-- Input -->
        <div class="chat-input-row">
          <input type="text" class="chat-input" id="chatInput"
                 placeholder="Ex: quanto gastei em alimentação este ano?"
                 onkeydown="if(event.key==='Enter') sendChat()">
          <button class="chat-send" id="chatSend" onclick="sendChat()" title="Enviar">
            <i class="bi bi-send-fill"></i>
          </button>
        </div>

      </div>
    </div>

  </div><!-- /ai-wrapper -->
</div><!-- /main-content -->

<script>
const API_URL = 'fdata.php';

// ── Helpers ────────────────────────────────────────────────────────────────
function loadingDots() {
  return '<span class="ai-loading-dot"></span><span class="ai-loading-dot"></span><span class="ai-loading-dot"></span>';
}

function formatAI(text) {
  return text
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\n/g, '<br>');
}

async function callAI(type, message = '') {
  // 1. Buscar o prompt ao PHP via POST
  const res = await fetch(API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ t: type, m: message })
  });
  const raw = await res.text();
  let data;
  try { data = JSON.parse(raw); } catch(e) { throw new Error('RAW: ' + raw.substring(0, 300)); }
  if (data.error) throw new Error(data.error);

  // 2. Chamar a Groq diretamente do browser
  const groqRes = await fetch('https://api.groq.com/openai/v1/chat/completions', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + data.apiKey
    },
    body: JSON.stringify({
      model: 'openai/gpt-oss-120b',
      messages: [{ role: 'user', content: data.prompt }],
      temperature: 0.7,
      max_tokens: 800
    })
  });
  if (!groqRes.ok) {
    const errText = await groqRes.text();
    throw new Error('Groq erro ' + groqRes.status + ': ' + errText.substring(0, 200));
  }
  const groqData = await groqRes.json();
  const text = groqData?.choices?.[0]?.message?.content ?? '';
  if (!text) throw new Error('IA sem resposta. Tenta novamente.');
  return text;
}

// ── Insights ───────────────────────────────────────────────────────────────
async function loadInsights() {
  const box = document.getElementById('insightsBox');
  const btn = document.getElementById('btnInsights');
  box.innerHTML = loadingDots();
  box.className = 'ai-response loading';
  btn.disabled = true;
  try {
    const reply = await callAI('insights');
    box.innerHTML = formatAI(reply);
    box.className = 'ai-response';
  } catch(e) {
    box.textContent = e.message;
    box.className = 'ai-response error';
  } finally {
    btn.disabled = false;
  }
}

// ── Poupança ───────────────────────────────────────────────────────────────
async function loadSavings() {
  const box = document.getElementById('savingsBox');
  const btn = document.getElementById('btnSavings');
  box.innerHTML = loadingDots();
  box.className = 'ai-response loading';
  btn.disabled = true;
  try {
    const reply = await callAI('savings');
    box.innerHTML = formatAI(reply);
    box.className = 'ai-response';
  } catch(e) {
    box.textContent = e.message;
    box.className = 'ai-response error';
  } finally {
    btn.disabled = false;
  }
}

// ── Chat ───────────────────────────────────────────────────────────────────
function appendMessage(role, text) {
  const container = document.getElementById('chatMessages');
  const empty     = document.getElementById('chatEmpty');
  if (empty) empty.remove();

  const isUser = role === 'user';
  const div = document.createElement('div');
  div.className = `chat-msg ${role}`;
  div.innerHTML = `
    <div class="chat-avatar ${role}">
      <i class="bi ${isUser ? 'bi-person' : 'bi-stars'}"></i>
    </div>
    <div class="chat-bubble ${role}">${role === 'ai' ? formatAI(text) : text.replace(/\n/g,'<br>')}</div>
  `;
  container.appendChild(div);
  container.scrollTop = container.scrollHeight;
  return div;
}

function appendLoading() {
  const container = document.getElementById('chatMessages');
  const div = document.createElement('div');
  div.className = 'chat-msg ai';
  div.id = 'chatLoading';
  div.innerHTML = `
    <div class="chat-avatar ai"><i class="bi bi-stars"></i></div>
    <div class="chat-bubble ai">${loadingDots()}</div>
  `;
  container.appendChild(div);
  container.scrollTop = container.scrollHeight;
}

async function sendChat() {
  const input = document.getElementById('chatInput');
  const sendBtn = document.getElementById('chatSend');
  const msg = input.value.trim();
  if (!msg) return;

  input.value = '';
  input.disabled = true;
  sendBtn.disabled = true;

  appendMessage('user', msg);
  appendLoading();

  try {
    const reply = await callAI('chat', msg);
    document.getElementById('chatLoading')?.remove();
    appendMessage('ai', reply);
  } catch(e) {
    document.getElementById('chatLoading')?.remove();
    appendMessage('ai', '⚠️ ' + e.message);
  } finally {
    input.disabled = false;
    sendBtn.disabled = false;
    input.focus();
  }
}

function sendQuick(text) {
  document.getElementById('chatInput').value = text;
  sendChat();
}
</script>

</body>
</html>