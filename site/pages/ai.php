<?php
// site/pages/ai.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/theme_helper.php';
$uid = $_SESSION['user_id'] ?? null;
$currentTheme = getUserTheme($pdo, $uid);
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?= $currentTheme ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Assistente - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css?v=6">
  <style>
    .main-content .pg-wrap.ai-wrap { max-width: 1000px; }
    .ai-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
    .ai-grid .pg-card { margin-bottom: 0; display: flex; flex-direction: column; }
    .ai-grid + .pg-card { margin-top: 18px; }
    .pg-card-head .pg-grow + .btn-s { flex-shrink: 0; }

    /* Lista de ideias (insights e poupança) */
    .ai-body { padding: 18px 20px; flex: 1; }
    .ai-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
    .ai-list li { display: flex; gap: 12px; align-items: flex-start; font-size: 15px; line-height: 1.5; color: var(--fn-text); }
    .ai-list li::before { content: ""; width: 10px; height: 10px; margin-top: 7px; border-radius: 3px; flex-shrink: 0; background: var(--tone); }
    .ai-list strong { color: var(--fn-text); font-weight: 700; background: color-mix(in srgb, var(--tone) 14%, transparent); padding: 0 4px; border-radius: 4px; }
    .ai-empty { margin: 0; font-size: 14px; color: var(--fn-muted); }
    .ai-error { margin: 0; font-size: 14px; color: #b42318; }
    [data-theme="dark"] .ai-error { color: #fda29b; }
    .ai-skel { display: grid; gap: 12px; }
    .ai-skel i { display: block; height: 12px; border-radius: 6px; background: color-mix(in srgb, var(--tone) 18%, transparent); animation: aiPulse 1.2s ease-in-out infinite; }
    .ai-skel i:nth-child(2) { width: 85%; animation-delay: .15s; } .ai-skel i:nth-child(3) { width: 70%; animation-delay: .3s; }
    @keyframes aiPulse { 50% { opacity: .45; } }

    /* Chat */
    .chat-body { padding: 18px 20px 20px; }
    .chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
    .chip { font-size: 13px; font-weight: 600; padding: 6px 12px; border-radius: 999px; border: 1px solid color-mix(in srgb, var(--c) 35%, transparent);
            background: color-mix(in srgb, var(--c) 10%, transparent); color: var(--fn-text); cursor: pointer; }
    .chip:hover { background: color-mix(in srgb, var(--c) 20%, transparent); }
    .chip:focus-visible { outline: 2px solid var(--fn-gold); outline-offset: 2px; }
    .msgs { display: flex; flex-direction: column; gap: 12px; max-height: 440px; overflow-y: auto; }
    .msgs:empty::before { content: "As tuas perguntas e as respostas aparecem aqui."; display: block; padding: 22px; text-align: center; font-size: 14px; color: var(--fn-muted); border: 1px dashed var(--fn-line); border-radius: 12px; }
    .msg { max-width: 80%; padding: 10px 14px; border-radius: 14px; font-size: 15px; line-height: 1.5; white-space: pre-wrap; }
    .msg.user { align-self: flex-end; background: var(--fn-accent); color: #fff; border-bottom-right-radius: 4px; }
    .msg.ai { align-self: flex-start; background: color-mix(in srgb, var(--cat-7) 9%, var(--fn-surface)); border: 1px solid color-mix(in srgb, var(--cat-7) 22%, transparent); color: var(--fn-text); border-bottom-left-radius: 4px; }
    .msg.ai strong { font-weight: 700; }
    .msg.err { align-self: flex-start; background: #fef3f2; color: #b42318; }
    [data-theme="dark"] .msg.err { background: rgba(253,162,155,.1); color: #fda29b; }
    .msg.typing { color: var(--fn-muted); }
    .ask { display: flex; gap: 8px; margin-top: 16px; }
    .ask .form-control { min-height: 46px; border-radius: 12px; border: 1px solid var(--fn-line); background: var(--fn-surface); color: var(--fn-text); }
    .ask .form-control:focus { border-color: var(--cat-7); box-shadow: 0 0 0 3px color-mix(in srgb, var(--cat-7) 20%, transparent); }
    .ask .btn-s { min-width: 46px; justify-content: center; }
    .ai-note { display: flex; gap: 8px; align-items: flex-start; margin: 14px 2px 0; font-size: 13px; color: var(--fn-muted); }

    @media (prefers-reduced-motion: reduce) { .ai-skel i { animation: none; } }
    @media (max-width: 820px) { .ai-grid { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 520px) { .msg { max-width: 92%; } }
  </style>
  <link rel="stylesheet" href="../css/finora.css?v=7">
</head>
<body>
<div class="app-layout">
<?php
$activePage    = 'ai';
$sidebarPrefix = '../';
include __DIR__ . '/../components/sidebar.php';
?>
<main class="main-content">
<div class="page-content">
<div class="pg-wrap ai-wrap">

  <div class="pg-head">
    <h1>Assistente</h1>
    <p>Respostas sobre o teu dinheiro, a partir das tuas transações.</p>
  </div>

  <div class="ai-grid">
    <section class="pg-card" style="--tone:var(--cat-4)" aria-labelledby="insTitle">
      <header class="pg-card-head">
        <span class="pg-ico" aria-hidden="true"><i class="bi bi-lightbulb"></i></span>
        <div class="pg-grow"><h2 id="insTitle">O teu mês</h2><p>Três coisas que se destacam.</p></div>
        <button type="button" class="btn-s" id="btnInsights" data-type="insights" aria-label="Gerar o resumo do mês"><i class="bi bi-stars"></i> Gerar</button>
      </header>
      <div class="ai-body" id="insightsBox" aria-live="polite">
        <p class="ai-empty">Carrega em "Gerar" para veres o resumo do mês.</p>
      </div>
    </section>

    <section class="pg-card" style="--tone:var(--cat-3)" aria-labelledby="savTitle">
      <header class="pg-card-head">
        <span class="pg-ico" aria-hidden="true"><i class="bi bi-piggy-bank"></i></span>
        <div class="pg-grow"><h2 id="savTitle">Onde poupar</h2><p>Sugestões com valores do teu mês.</p></div>
        <button type="button" class="btn-s" id="btnSavings" data-type="savings" aria-label="Gerar sugestões de poupança"><i class="bi bi-stars"></i> Gerar</button>
      </header>
      <div class="ai-body" id="savingsBox" aria-live="polite">
        <p class="ai-empty">Carrega em "Gerar" para receberes sugestões.</p>
      </div>
    </section>
  </div>

  <section class="pg-card" style="--tone:var(--cat-7)" aria-labelledby="chatTitle">
    <header class="pg-card-head">
      <span class="pg-ico" aria-hidden="true"><i class="bi bi-chat-dots"></i></span>
      <div class="pg-grow"><h2 id="chatTitle">Pergunta</h2><p>Sobre gastos, categorias, cartões ou meses anteriores.</p></div>
    </header>
    <div class="chat-body">
      <div class="chips">
        <?php foreach ([['Quanto gastei este mês?', '--cat-1'], ['Em que categoria gasto mais?', '--cat-2'],
                        ['Qual foi o meu maior gasto?', '--cat-8'], ['Como estou face ao mês passado?', '--cat-3'],
                        ['Onde posso cortar?', '--cat-4']] as [$q, $c]): ?>
          <button type="button" class="chip" style="--c:var(<?= $c ?>)"><?= htmlspecialchars($q) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="msgs" id="msgs" aria-live="polite"></div>
      <form class="ask" id="askForm">
        <label class="visually-hidden" for="askInput">A tua pergunta</label>
        <input type="text" class="form-control" id="askInput" maxlength="500" autocomplete="off" placeholder="Ex.: quanto gastei em transportes este mês?">
        <button type="submit" class="btn-s btn-s-primary" id="askBtn" aria-label="Enviar pergunta"><i class="bi bi-send"></i></button>
      </form>
      <p class="ai-note"><i class="bi bi-shield-lock" aria-hidden="true"></i>
        Para responder, a Finora envia à Groq (o serviço de IA) um resumo dos teus gastos deste mês e a tua pergunta. Não envia o teu nome, email nem dados dos cartões além do nome que lhes deste. As respostas podem ter erros e não são aconselhamento financeiro.</p>
    </div>
  </section>

</div>
</div>
</main>
</div>

<script>
'use strict';
const CSRF = <?= json_encode(csrf_token()) ?>;
const $ = id => document.getElementById(id);

async function callAI(type, message = '') {
  // O PHP chama a Groq no servidor e devolve só o texto da resposta
  let res, data;
  try {
    res = await fetch('fdata.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                                     body: JSON.stringify({ t: type, m: message }) });
    data = await res.json();
  } catch (e) { throw new Error('Sem ligação ao servidor. Tenta outra vez.'); }
  if (data.error) throw new Error(data.error);
  if (!data.reply) throw new Error('O assistente não respondeu. Tenta outra vez.');
  return data.reply;
}

// A resposta é texto de fora (um modelo de IA pode ser manipulado), por isso é construída com nós
// de texto: só **negrito** vira <strong>, e nunca se usa innerHTML com o texto da resposta.
function richText(parent, text) {
  text.split(/(\*\*[^*]+\*\*)/g).forEach(part => {
    if (/^\*\*[^*]+\*\*$/.test(part)) { const b = document.createElement('strong'); b.textContent = part.slice(2, -2); parent.append(b); }
    else if (part) parent.append(document.createTextNode(part));
  });
  return parent;
}

function skeleton(box) { box.replaceChildren(); const s = document.createElement('div'); s.className = 'ai-skel'; s.append(document.createElement('i'), document.createElement('i'), document.createElement('i')); box.append(s); }

async function loadList(btn) {
  const box = $(btn.dataset.type === 'insights' ? 'insightsBox' : 'savingsBox');
  btn.disabled = true;
  skeleton(box);
  try {
    const lines = (await callAI(btn.dataset.type)).split('\n').map(l => l.replace(/^\s*(?:[-•*]|\d+[.)])\s*/, '').trim()).filter(Boolean);
    const ul = document.createElement('ul'); ul.className = 'ai-list';
    lines.slice(0, 5).forEach(l => { const li = document.createElement('li'); li.append(richText(document.createElement('span'), l)); ul.append(li); });
    box.replaceChildren(ul);
    btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Gerar outra vez';
  } catch (e) {
    const p = document.createElement('p'); p.className = 'ai-error'; p.textContent = e.message; box.replaceChildren(p);
  } finally { btn.disabled = false; }
}
$('btnInsights').addEventListener('click', e => loadList(e.currentTarget));
$('btnSavings').addEventListener('click', e => loadList(e.currentTarget));

function addMsg(cls, text) {
  const div = document.createElement('div');
  div.className = 'msg ' + cls;
  cls === 'ai' ? richText(div, text) : (div.textContent = text);
  $('msgs').append(div);
  $('msgs').scrollTop = $('msgs').scrollHeight;
  return div;
}

async function ask(q) {
  q = q.trim();
  if (!q) return;
  $('askInput').value = '';
  $('askInput').disabled = $('askBtn').disabled = true;
  addMsg('user', q);
  const typing = addMsg('ai typing', 'A pensar…');
  try { const r = await callAI('chat', q); typing.remove(); addMsg('ai', r); }
  catch (e) { typing.remove(); addMsg('err', e.message); }
  finally { $('askInput').disabled = $('askBtn').disabled = false; $('askInput').focus(); }
}
$('askForm').addEventListener('submit', e => { e.preventDefault(); ask($('askInput').value); });
document.querySelectorAll('.chip').forEach(c => c.addEventListener('click', () => ask(c.textContent)));
</script>
</body>
</html>
