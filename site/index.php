<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FreeCard — Gestão de Cartões e Transações</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    /* ── Tokens (espelham a app) ─────────────────────────── */
    :root {
      --blue:        #3498db;
      --blue-dark:   #2980b9;
      --bg:          #f4f6f9;
      --bg-card:     #ffffff;
      --border:      #e8ecef;
      --text:        #1a202c;
      --text-sub:    #6b7280;
      --radius:      14px;
      --shadow:      0 1px 4px rgba(0,0,0,.06), 0 4px 20px rgba(0,0,0,.05);
      --shadow-md:   0 4px 24px rgba(0,0,0,.10);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: var(--bg);
      color: var(--text);
      overflow-x: hidden;
    }

    /* ── Navbar ──────────────────────────────────────────── */
    .top-nav {
      background: var(--bg-card);
      border-bottom: 1px solid var(--border);
      padding: 0 32px;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 1px 0 var(--border);
    }
    .nav-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      color: var(--text);
      font-weight: 700;
      font-size: 17px;
    }
    .nav-brand img { height: 34px; }
    .nav-links { display: flex; align-items: center; gap: 8px; }
    .nav-link-plain {
      font-size: 14px;
      font-weight: 500;
      color: var(--text-sub);
      text-decoration: none;
      padding: 8px 14px;
      border-radius: 9px;
      transition: background .15s, color .15s;
    }
    .nav-link-plain:hover { background: var(--bg); color: var(--text); }
    .btn-nav {
      font-size: 14px;
      font-weight: 600;
      color: #fff;
      background: var(--blue);
      text-decoration: none;
      padding: 8px 20px;
      border-radius: 9px;
      transition: background .15s;
    }
    .btn-nav:hover { background: var(--blue-dark); color: #fff; }

    /* ── Hero ────────────────────────────────────────────── */
    .hero {
      padding: 96px 0 80px;
      text-align: center;
      position: relative;
    }
    .hero-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      background: rgba(52,152,219,.1);
      color: var(--blue);
      border: 1px solid rgba(52,152,219,.25);
      border-radius: 20px;
      padding: 5px 14px;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: .04em;
      text-transform: uppercase;
      margin-bottom: 24px;
    }
    .hero h1 {
      font-size: clamp(32px, 5vw, 56px);
      font-weight: 800;
      line-height: 1.15;
      color: var(--text);
      margin-bottom: 20px;
      letter-spacing: -.02em;
    }
    .hero h1 em {
      font-style: normal;
      color: var(--blue);
    }
    .hero-sub {
      font-size: 18px;
      color: var(--text-sub);
      max-width: 520px;
      margin: 0 auto 40px;
      line-height: 1.6;
    }
    .hero-actions {
      display: flex;
      justify-content: center;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 72px;
    }
    .btn-primary-lg {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--blue);
      color: #fff;
      text-decoration: none;
      font-size: 15px;
      font-weight: 600;
      padding: 13px 28px;
      border-radius: 10px;
      transition: background .15s, transform .15s;
      box-shadow: 0 4px 14px rgba(52,152,219,.35);
    }
    .btn-primary-lg:hover { background: var(--blue-dark); color: #fff; transform: translateY(-1px); }
    .btn-outline-lg {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--bg-card);
      color: var(--text);
      text-decoration: none;
      font-size: 15px;
      font-weight: 500;
      padding: 13px 28px;
      border-radius: 10px;
      border: 1px solid var(--border);
      transition: border-color .15s, transform .15s;
    }
    .btn-outline-lg:hover { border-color: #aaa; color: var(--text); transform: translateY(-1px); }

    /* ── App mockup ──────────────────────────────────────── */
    .mockup-wrap {
      max-width: 820px;
      margin: 0 auto;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 18px;
      box-shadow: var(--shadow-md), 0 0 0 6px rgba(0,0,0,.03);
      overflow: hidden;
    }
    .mockup-titlebar {
      background: var(--bg);
      border-bottom: 1px solid var(--border);
      padding: 10px 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .dot { width: 10px; height: 10px; border-radius: 50%; }
    .dot-r { background: #ff5f57; }
    .dot-y { background: #febc2e; }
    .dot-g { background: #28c840; }
    .mockup-bar {
      flex: 1;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 6px;
      height: 22px;
      margin: 0 8px;
      display: flex;
      align-items: center;
      padding: 0 10px;
    }
    .mockup-bar span {
      font-size: 10px;
      color: var(--text-sub);
    }
    .mockup-body {
      display: flex;
      min-height: 320px;
    }
    /* mini sidebar */
    .mock-sidebar {
      width: 180px;
      min-width: 180px;
      border-right: 1px solid var(--border);
      padding: 14px 10px;
      display: flex;
      flex-direction: column;
      gap: 3px;
    }
    .mock-logo {
      display: flex;
      align-items: center;
      gap: 7px;
      padding: 4px 8px 12px;
      font-size: 13px;
      font-weight: 700;
      color: var(--text);
      border-bottom: 1px solid var(--border);
      margin-bottom: 8px;
    }
    .mock-logo img { height: 22px; }
    .mock-label {
      font-size: 9px;
      font-weight: 600;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: var(--text-sub);
      padding: 8px 8px 4px;
    }
    .mock-item {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 7px 10px;
      border-radius: 7px;
      font-size: 11px;
      font-weight: 500;
      color: var(--text-sub);
    }
    .mock-item.active {
      background: var(--blue);
      color: #fff;
    }
    .mock-item i { font-size: 13px; }
    /* mini content */
    .mock-content { flex: 1; padding: 18px; display: flex; flex-direction: column; gap: 12px; }
    .mock-header {
      font-size: 14px;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 4px;
    }
    .mock-metrics {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 8px;
    }
    .mock-metric {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 10px 12px;
    }
    .mock-metric-label { font-size: 9px; color: var(--text-sub); margin-bottom: 4px; text-transform: uppercase; letter-spacing: .04em; }
    .mock-metric-val { font-size: 16px; font-weight: 700; color: var(--text); }
    .mock-metric-val.blue { color: var(--blue); }
    .mock-metric-val.green { color: #1abc9c; }
    .mock-metric-val.red { color: #e74c3c; }
    .mock-chart-area {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 10px;
      flex: 1;
      padding: 12px 14px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .mock-chart-title { font-size: 11px; font-weight: 600; color: var(--text); display: flex; align-items: center; gap: 6px; }
    .mock-chart-title i { color: var(--blue); }
    .mock-bars {
      display: flex;
      align-items: flex-end;
      gap: 6px;
      height: 80px;
      flex: 1;
    }
    .mock-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 3px; }
    .mock-bar {
      width: 100%;
      background: var(--blue);
      border-radius: 4px 4px 0 0;
      opacity: .85;
    }
    .mock-bar-lbl { font-size: 8px; color: var(--text-sub); }

    /* ── Section shared ──────────────────────────────────── */
    section { padding: 80px 0; }
    .sect-label {
      font-size: 11px;
      font-weight: 600;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--blue);
      margin-bottom: 12px;
    }
    .sect-title {
      font-size: clamp(26px, 3.5vw, 38px);
      font-weight: 800;
      color: var(--text);
      letter-spacing: -.02em;
      line-height: 1.2;
      margin-bottom: 12px;
    }
    .sect-sub {
      font-size: 16px;
      color: var(--text-sub);
      line-height: 1.6;
      max-width: 480px;
    }

    /* ── Feature cards ───────────────────────────────────── */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
    }
    .feat-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 28px 24px;
      transition: box-shadow .2s, transform .2s;
    }
    .feat-card:hover {
      box-shadow: var(--shadow-md);
      transform: translateY(-2px);
    }
    .feat-icon {
      width: 42px;
      height: 42px;
      border-radius: 11px;
      background: rgba(52,152,219,.1);
      border: 1px solid rgba(52,152,219,.2);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--blue);
      font-size: 18px;
      margin-bottom: 16px;
    }
    .feat-card h4 {
      font-size: 15px;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 8px;
    }
    .feat-card p {
      font-size: 13px;
      color: var(--text-sub);
      line-height: 1.6;
      margin: 0;
    }

    /* ── Metrics row ─────────────────────────────────────── */
    .metrics-strip {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      overflow: hidden;
    }
    .m-cell {
      padding: 32px 24px;
      text-align: center;
      position: relative;
    }
    .m-cell:not(:last-child)::after {
      content: '';
      position: absolute;
      right: 0; top: 20%; bottom: 20%;
      width: 1px;
      background: var(--border);
    }
    .m-val {
      font-size: 36px;
      font-weight: 800;
      color: var(--blue);
      line-height: 1;
      margin-bottom: 8px;
    }
    .m-lbl {
      font-size: 13px;
      color: var(--text-sub);
      font-weight: 500;
    }

    /* ── Testimonial / quote ─────────────────────────────── */
    .quote-strip {
      background: var(--blue);
      border-radius: var(--radius);
      padding: 48px 40px;
      text-align: center;
      color: #fff;
    }
    .quote-strip h2 {
      font-size: clamp(22px, 3vw, 32px);
      font-weight: 800;
      margin-bottom: 12px;
      line-height: 1.25;
    }
    .quote-strip p {
      font-size: 16px;
      opacity: .85;
      margin-bottom: 32px;
      max-width: 500px;
      margin-left: auto;
      margin-right: auto;
    }
    .btn-white {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #fff;
      color: var(--blue);
      text-decoration: none;
      font-size: 15px;
      font-weight: 700;
      padding: 13px 28px;
      border-radius: 10px;
      transition: opacity .15s, transform .15s;
    }
    .btn-white:hover { opacity: .92; color: var(--blue); transform: translateY(-1px); }

    /* ── Footer ──────────────────────────────────────────── */
    footer {
      background: var(--bg-card);
      border-top: 1px solid var(--border);
      padding: 40px 0 24px;
    }
    .footer-brand {
      display: flex;
      align-items: center;
      gap: 9px;
      text-decoration: none;
      color: var(--text);
      font-weight: 700;
      font-size: 16px;
      margin-bottom: 8px;
    }
    .footer-brand img { height: 28px; }
    .footer-copy {
      font-size: 12px;
      color: var(--text-sub);
    }
    .footer-link {
      font-size: 13px;
      color: var(--text-sub);
      text-decoration: none;
      display: block;
      margin-bottom: 8px;
      transition: color .15s;
    }
    .footer-link:hover { color: var(--blue); }
    .footer-heading {
      font-size: 12px;
      font-weight: 700;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: var(--text);
      margin-bottom: 14px;
    }
    .footer-divider {
      border: none;
      border-top: 1px solid var(--border);
      margin: 24px 0 16px;
    }

    /* ── Fade-in on scroll ───────────────────────────────── */
    .fade-up {
      opacity: 0;
      transform: translateY(20px);
      transition: opacity .55s ease, transform .55s ease;
    }
    .fade-up.visible { opacity: 1; transform: none; }
    .fade-up:nth-child(2) { transition-delay: .07s; }
    .fade-up:nth-child(3) { transition-delay: .14s; }
    .fade-up:nth-child(4) { transition-delay: .21s; }

    /* ── Responsive ──────────────────────────────────────── */
    @media (max-width: 900px) {
      .features-grid { grid-template-columns: repeat(2, 1fr); }
      .mock-sidebar { display: none; }
      .mock-metrics { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
      .top-nav { padding: 0 16px; }
      .features-grid { grid-template-columns: 1fr; }
      .metrics-strip { grid-template-columns: repeat(2, 1fr); }
      .m-cell:nth-child(2)::after { display: none; }
      .m-cell:nth-child(odd)::after { display: none; }
      .m-cell { padding: 24px 16px; }
      .hero { padding: 64px 0 56px; }
      .quote-strip { padding: 36px 20px; }
      .mock-metrics { grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>

<!-- NAV -->
<nav class="top-nav">
  <a href="index.php" class="nav-brand">
    <img src="assets/logo2.png" alt="FreeCard">
    <span>FreeCard</span>
  </a>
  <div class="nav-links">
    <a href="auth/login.php" class="nav-link-plain">Entrar</a>
    <a href="auth/register.php" class="btn-nav">Começar grátis</a>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <div class="hero-eyebrow">
      <i class="bi bi-stars"></i> 100% gratuito · Sem cartão de crédito
    </div>
    <h1>Controla os teus <em>cartões</em><br>com simplicidade</h1>
    <p class="hero-sub">Gere cartões, transações e orçamentos num só lugar. Acompanha os teus gastos e mantém-te sempre dentro do orçamento.</p>
    <div class="hero-actions">
      <a href="auth/register.php" class="btn-primary-lg">
        Criar conta gratuita <i class="bi bi-arrow-right"></i>
      </a>
      <a href="auth/login.php" class="btn-outline-lg">
        Já tenho conta <i class="bi bi-box-arrow-in-right"></i>
      </a>
    </div>

    <!-- App mockup -->
    <div class="mockup-wrap fade-up">
      <div class="mockup-titlebar">
        <div class="dot dot-r"></div>
        <div class="dot dot-y"></div>
        <div class="dot dot-g"></div>
        <div class="mockup-bar"><span>freecard.app/dashboard</span></div>
      </div>
      <div class="mockup-body">
        <div class="mock-sidebar">
          <div class="mock-logo">
            <img src="assets/logo2.png" alt=""> FreeCard
          </div>
          <div class="mock-label">Principal</div>
          <div class="mock-item active"><i class="bi bi-speedometer2"></i> Dashboard</div>
          <div class="mock-item"><i class="bi bi-wallet2"></i> Cartões</div>
          <div class="mock-item"><i class="bi bi-arrow-left-right"></i> Transações</div>
          <div class="mock-label">Gestão</div>
          <div class="mock-item"><i class="bi bi-piggy-bank"></i> Orçamentos</div>
          <div class="mock-item"><i class="bi bi-bell"></i> Lembretes</div>
          <div class="mock-item"><i class="bi bi-bar-chart-line"></i> Análise</div>
        </div>
        <div class="mock-content">
          <div class="mock-header">Dashboard</div>
          <div class="mock-metrics">
            <div class="mock-metric">
              <div class="mock-metric-label">Total gasto</div>
              <div class="mock-metric-val blue">€530</div>
            </div>
            <div class="mock-metric">
              <div class="mock-metric-label">Transações</div>
              <div class="mock-metric-val">16</div>
            </div>
            <div class="mock-metric">
              <div class="mock-metric-label">Média</div>
              <div class="mock-metric-val green">€33</div>
            </div>
            <div class="mock-metric">
              <div class="mock-metric-label">Maior gasto</div>
              <div class="mock-metric-val red">€150</div>
            </div>
          </div>
          <div class="mock-chart-area">
            <div class="mock-chart-title"><i class="bi bi-graph-up"></i> Gastos mensais por categoria</div>
            <div class="mock-bars">
              <?php
              $bars = [
                ['jan', 15], ['fev', 22], ['mar', 62], ['abr', 80],
                ['mai', 45], ['jun', 30], ['jul', 18], ['ago', 0],
              ];
              $max = max(array_column($bars, 1));
              foreach ($bars as [$lbl, $h]):
                $pct = $max > 0 ? round(($h/$max)*100) : 0;
              ?>
              <div class="mock-bar-col">
                <div class="mock-bar" style="height:<?=$pct?>%;opacity:<?=$h>0?.85:.15?>"></div>
                <div class="mock-bar-lbl"><?=$lbl?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section style="padding: 64px 0; background: var(--bg);">
  <div class="container">
    <div class="row align-items-end mb-5">
      <div class="col-md-6">
        <p class="sect-label">Funcionalidades</p>
        <h2 class="sect-title">Tudo o que precisas para gerir as tuas finanças</h2>
      </div>
      <div class="col-md-6">
        <p class="sect-sub">Ferramentas simples e poderosas, desenhadas para quem quer controlo total sem complicações.</p>
      </div>
    </div>
    <div class="features-grid">
      <div class="feat-card fade-up">
        <div class="feat-icon"><i class="bi bi-credit-card-2-front"></i></div>
        <h4>Gestão de Cartões</h4>
        <p>Adiciona e organiza todos os teus cartões num só lugar. Acompanha limites e saldos com alertas automáticos.</p>
      </div>
      <div class="feat-card fade-up">
        <div class="feat-icon"><i class="bi bi-receipt"></i></div>
        <h4>Registo de Transações</h4>
        <p>Regista despesas com categorias e associa cada uma ao cartão. Historial completo sempre acessível.</p>
      </div>
      <div class="feat-card fade-up">
        <div class="feat-icon"><i class="bi bi-graph-up"></i></div>
        <h4>Análise de Gastos</h4>
        <p>Gráficos mensais por categoria, comparação entre anos e resumos instantâneos das tuas finanças.</p>
      </div>
      <div class="feat-card fade-up">
        <div class="feat-icon"><i class="bi bi-piggy-bank"></i></div>
        <h4>Orçamentos</h4>
        <p>Define limites por categoria ou cartão. Recebe alertas antes de ultrapassar os teus objetivos.</p>
      </div>
      <div class="feat-card fade-up">
        <div class="feat-icon"><i class="bi bi-qr-code-scan"></i></div>
        <h4>Ler Fatura QR</h4>
        <p>Aponta a câmara a qualquer fatura portuguesa e a transação é criada automaticamente pelo NIF.</p>
      </div>
      <div class="feat-card fade-up">
        <div class="feat-icon"><i class="bi bi-file-earmark-pdf"></i></div>
        <h4>Exportar Relatórios</h4>
        <p>Exporta as tuas transações e cartões em PDF, CSV ou JSON para arquivo pessoal ou partilha.</p>
      </div>
    </div>
  </div>
</section>

<!-- METRICS -->
<section style="padding: 64px 0;">
  <div class="container">
    <div class="metrics-strip fade-up">
      <div class="m-cell">
        <div class="m-val">100%</div>
        <div class="m-lbl">Gratuito, sempre</div>
      </div>
      <div class="m-cell">
        <div class="m-val"><i class="bi bi-shield-check" style="font-size:32px"></i></div>
        <div class="m-lbl">Seguro e protegido</div>
      </div>
      <div class="m-cell">
        <div class="m-val"><i class="bi bi-lightning-charge" style="font-size:32px"></i></div>
        <div class="m-lbl">Rápido e simples</div>
      </div>
      <div class="m-cell">
        <div class="m-val"><i class="bi bi-phone" style="font-size:32px"></i></div>
        <div class="m-lbl">100% responsivo</div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section style="padding: 0 0 80px;">
  <div class="container">
    <div class="quote-strip fade-up">
      <h2>Pronto para começar?</h2>
      <p>Cria a tua conta em segundos e começa a gerir as tuas finanças de forma inteligente — de graça.</p>
      <a href="auth/register.php" class="btn-white">
        <i class="bi bi-check-circle-fill"></i> Criar conta gratuita
      </a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="container">
    <div class="row">
      <div class="col-md-5 mb-4 mb-md-0">
        <a href="index.php" class="footer-brand">
          <img src="assets/logo2.png" alt="FreeCard">
          FreeCard
        </a>
        <p class="footer-copy">Gestão de cartões e transações.<br>Simples, rápido e gratuito.</p>
      </div>
      <div class="col-md-3 mb-4 mb-md-0">
        <p class="footer-heading">Acesso</p>
        <a href="auth/register.php" class="footer-link"><i class="bi bi-person-plus"></i> Criar conta</a>
        <a href="auth/login.php" class="footer-link"><i class="bi bi-box-arrow-in-right"></i> Entrar</a>
      </div>
      <div class="col-md-4">
        <p class="footer-heading">Projeto</p>
        <p class="footer-copy">Prova de Aptidão Profissional<br>Desenvolvido por Diogo Freire e Jandro Antunes</p>
      </div>
    </div>
    <hr class="footer-divider">
    <p class="footer-copy text-center mb-0">&copy; <?=date('Y')?> FreeCard. Todos os direitos reservados.</p>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const io = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); } });
}, { threshold: 0.12 });
document.querySelectorAll('.fade-up').forEach(el => io.observe(el));
</script>
</body>
</html>