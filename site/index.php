<!doctype html>
<html lang="pt-PT" data-theme="light">
<head>
<script>
  // Aplicar tema ANTES de renderizar — elimina o flash
  (function() {
    var t = localStorage.getItem('fc_theme') || 'light';
    document.documentElement.setAttribute('data-theme', t);
  })();
</script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Finora — Gestão de Cartões e Transações gratuita em Portugal</title>

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">
  <link rel="apple-touch-icon" href="/favicon.png">

  <!-- SEO -->
  <meta name="description" content="Finora é uma aplicação gratuita para gerir os teus cartões de crédito e débito, controlar transações e orçamentos mensais. Simples, seguro e 100% gratuito.">
  <meta name="keywords" content="gestão de cartões, controlo de gastos, orçamento mensal, transações, finanças pessoais, app gratuita, Portugal">
  <meta name="author" content="Finora">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://freecard.infinityfreeapp.com/site/">

  <!-- Open Graph (Facebook, WhatsApp, etc.) -->
  <meta property="og:type"        content="website">
  <meta property="og:url"         content="https://freecard.infinityfreeapp.com/site/">
  <meta property="og:title"       content="Finora — Gestão de Cartões gratuita">
  <meta property="og:description" content="Gere cartões, transações e orçamentos num só lugar. Acompanha os teus gastos e mantém-te sempre dentro do orçamento. 100% gratuito.">
  <meta property="og:image"       content="https://freecard.infinityfreeapp.com/site/assets/logo2.png">
  <meta property="og:locale"      content="pt_PT">
  <meta property="og:site_name"   content="Finora">

  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary">
  <meta name="twitter:title"       content="Finora — Gestão de Cartões gratuita">
  <meta name="twitter:description" content="Gere cartões, transações e orçamentos num só lugar. 100% gratuito.">
  <meta name="twitter:image"       content="https://freecard.infinityfreeapp.com/site/assets/logo2.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    /* ── Tokens (espelham a app) ─────────────────────────── */
    :root,
    :root[data-theme="light"] {
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

    :root[data-theme="dark"] {
      --blue:        #3498db;
      --blue-dark:   #2980b9;
      --bg:          #0e1420;
      --bg-card:     #16202e;
      --border:      #34495e;
      --text:        #ecf0f1;
      --text-sub:    #95a5a6;
      --radius:      14px;
      --shadow:      0 1px 4px rgba(0,0,0,.2), 0 4px 20px rgba(0,0,0,.2);
      --shadow-md:   0 4px 24px rgba(0,0,0,.35);
    }

    /* ── Gradientes com cores da logo (navy #002850 + dourado #c8783c) ── */
    [data-theme="dark"] body {
      background-color: #0e1420;
      background-image:
        radial-gradient(ellipse 70% 55% at 0%   0%,   rgba(0,40,80,.80)    0%, transparent 65%),
        radial-gradient(ellipse 50% 40% at 100%  0%,   rgba(200,120,60,.30) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 50% 110%,  rgba(0,20,60,.70)    0%, transparent 65%);
    }

    [data-theme="dark"] .top-nav {
      background: rgba(14,20,32,.88);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border-bottom-color: rgba(0,40,80,.6);
    }

    [data-theme="dark"] footer {
      background: rgba(14,20,32,.80);
      border-top-color: rgba(0,40,80,.5);
    }

    /* ── Theme toggle ────────────────────────────────────── */
    .theme-toggle {
      background: none;
      border: 1px solid var(--border);
      color: var(--text-sub);
      border-radius: 8px;
      width: 34px;
      height: 34px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 15px;
      transition: color .15s, border-color .15s;
      flex-shrink: 0;
    }
    .theme-toggle:hover { color: var(--text); border-color: var(--blue); }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { transition: background-color .35s ease, color .25s ease; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background-color: var(--bg);
      background-image:
        radial-gradient(ellipse 65% 55% at 0%   0%,   rgba(0,40,80,.22)    0%, transparent 65%),
        radial-gradient(ellipse 55% 45% at 100%  0%,   rgba(200,120,60,.20) 0%, transparent 60%),
        radial-gradient(ellipse 55% 45% at 50% 115%,  rgba(0,40,80,.15)    0%, transparent 65%);
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
    .hero-card-icon {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 0;
      margin: 20px 0 24px;
    }
    @keyframes iconFloat {
      0%,100% { transform: translateY(0); }
      50%      { transform: translateY(-8px); }
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
      font-size: clamp(36px, 6vw, 68px);
      font-weight: 800;
      line-height: 1.12;
      color: var(--text);
      margin-bottom: 20px;
      letter-spacing: -.03em;
    }
    .hero h1 em {
      font-style: normal;
      background: linear-gradient(90deg, #002850 0%, #c8783c 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    [data-theme="dark"] .hero h1 em {
      background: linear-gradient(90deg, #5ba3d9 0%, #e8a060 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
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

    /* ── Mockup float ────────────────────────────────────── */
    @keyframes float {
      0%, 100% { transform: translateY(0px);   box-shadow: var(--shadow-md), 0 0 0 6px rgba(0,0,0,.03); }
      50%       { transform: translateY(-10px); box-shadow: 0 24px 60px rgba(0,40,80,.18), 0 0 0 6px rgba(0,0,0,.03); }
    }
    .mockup-wrap {
      max-width: 820px;
      margin: 0 auto;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 18px;
      box-shadow: var(--shadow-md), 0 0 0 6px rgba(0,0,0,.03);
      overflow: hidden;
      animation: float 5s ease-in-out infinite;
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
      background: color-mix(in srgb, var(--bg-card) 85%, transparent);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 28px 24px;
      transition: box-shadow .25s, transform .25s, border-color .25s;
      position: relative;
    }
    .feat-card::before { display: none; }
    .feat-card > * { position: relative; z-index: 1; }
    .feat-card:hover {
      box-shadow: 0 6px 24px rgba(0,0,0,.08);
      transform: translateY(-3px);
      border-color: var(--border);
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
      background: linear-gradient(135deg, #002850 0%, #1a6fa8 50%, #2980b9 100%);
      border-radius: var(--radius);
      padding: 48px 40px;
      text-align: center;
      color: #fff;
      position: relative;
      overflow: hidden;
    }
    .quote-strip::after { display: none; }
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

    /* ── Quem Somos ──────────────────────────────────────── */
    .team-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 24px;
      max-width: 640px;
      margin: 48px auto 0;
    }
    .team-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 32px 24px;
      text-align: center;
      transition: box-shadow .2s, transform .2s;
    }
    .team-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
    .team-avatar {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background: rgba(52,152,219,.12);
      border: 2px solid rgba(52,152,219,.25);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 16px;
      font-size: 28px;
      color: var(--blue);
    }
    .team-name {
      font-size: 16px;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 4px;
    }
    .team-role {
      font-size: 12px;
      font-weight: 600;
      color: var(--blue);
      text-transform: uppercase;
      letter-spacing: .06em;
      margin-bottom: 12px;
    }
    .team-desc {
      font-size: 13px;
      color: var(--text-sub);
      line-height: 1.6;
      margin: 0;
    }
    .quem-somos-inner {
      max-width: 600px;
      margin: 0 auto;
      text-align: center;
    }
    .quem-somos-inner .sect-sub { max-width: 100%; }
    .pap-badge {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      background: rgba(52,152,219,.08);
      border: 1px solid rgba(52,152,219,.2);
      border-radius: 20px;
      padding: 6px 16px;
      font-size: 12px;
      font-weight: 600;
      color: var(--blue);
      letter-spacing: .04em;
      margin-top: 40px;
    }
    @media (max-width: 500px) {
      .team-grid { grid-template-columns: 1fr; max-width: 320px; }
    }

    /* ── Partículas ──────────────────────────────────────── */
    #particles-canvas {
      position: fixed;
      inset: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 0;
    }
    .hero > .container { position: relative; z-index: 1; }
    .top-nav, section, footer { position: relative; z-index: 1; }

    /* ── Parallax 3D mockup ──────────────────────────────── */
    .mockup-wrap {
      transform-style: preserve-3d;
      will-change: transform;
      transition: transform .08s ease-out;
    }

    /* ── Typing effect ───────────────────────────────────── */
    .typed-cursor {
      display: inline-block;
      width: 3px;
      background: currentColor;
      margin-left: 3px;
      border-radius: 2px;
      animation: blink .7s step-end infinite;
      vertical-align: baseline;
      height: .85em;
      position: relative;
      top: .05em;
    }
    @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }

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

    [data-theme="dark"] .nav-brand img,
    [data-theme="dark"] .mock-logo img,
    [data-theme="dark"] .footer-brand img {
      filter:
        drop-shadow(-1px  0px 0 rgba(255,255,255,0.9))
        drop-shadow( 1px  0px 0 rgba(255,255,255,0.9))
        drop-shadow( 0px -1px 0 rgba(255,255,255,0.9))
        drop-shadow( 0px  1px 0 rgba(255,255,255,0.9));
    }

    /* ── Carrossel mobile de funcionalidades ────────────── */
    .feat-carousel { display: none; }

    @media (max-width: 640px) {
      .features-grid-desktop { display: none; }
      .feat-card-ai { margin-top: 20px; }

      .feat-carousel {
        display: block;
        position: relative;
        overflow: hidden;
        margin: 0 -16px;
        padding: 0 16px;
      }
      .feat-carousel-track {
        display: flex;
        gap: 12px;
        transition: transform .35s cubic-bezier(.4,0,.2,1);
        will-change: transform;
        cursor: grab;
        user-select: none;
      }
      .feat-carousel-track.dragging { transition: none; cursor: grabbing; }
      .feat-carousel-track .feat-card {
        min-width: calc(85vw);
        max-width: calc(85vw);
        flex-shrink: 0;
        margin: 0;
      }
      .feat-carousel-dots {
        display: flex;
        justify-content: center;
        gap: 6px;
        margin-top: 18px;
      }
      .feat-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--border);
        transition: background .2s, width .2s;
        cursor: pointer;
      }
      .feat-dot.active {
        background: var(--blue);
        width: 18px;
        border-radius: 4px;
      }
    }

    /* ── Assistente IA destaque ─────────────────────────── */
    .feat-card-ai {
      position: relative;
      margin-top: 16px;
      background: linear-gradient(135deg, #002850 0%, #1a5fa8 60%, #2980b9 100%);
      border: 1px solid rgba(52,152,219,.4);
      border-radius: var(--radius);
      padding: 28px 36px;
      overflow: hidden;
      cursor: default;
      transition: transform .25s, box-shadow .25s;
    }
    .feat-card-ai:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 40px rgba(0,40,80,.25);
    }
    .feat-ai-glow {
      position: absolute;
      top: -60px; right: -60px;
      width: 220px; height: 220px;
      background: radial-gradient(circle, rgba(200,120,60,.35) 0%, transparent 70%);
      pointer-events: none;
    }
    .feat-ai-inner {
      position: relative;
      display: flex;
      align-items: center;
      gap: 24px;
    }
    .feat-ai-icon {
      width: 56px;
      height: 56px;
      min-width: 56px;
      border-radius: 14px;
      background: rgba(255,255,255,.12);
      border: 1px solid rgba(255,255,255,.2);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 26px;
    }
    .feat-card-ai h4 {
      font-size: 17px;
      font-weight: 700;
      color: #fff;
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .feat-card-ai p {
      font-size: 14px;
      color: rgba(255,255,255,.75);
      line-height: 1.6;
      margin: 0;
    }
    .feat-ai-badge {
      font-size: 10px;
      font-weight: 700;
      background: rgba(200,120,60,.85);
      color: #fff;
      padding: 2px 8px;
      border-radius: 20px;
      letter-spacing: .04em;
      text-transform: uppercase;
      vertical-align: middle;
    }
    @media (max-width: 640px) {
      .feat-card-ai { padding: 20px; }
      .feat-ai-inner { gap: 16px; }
      .feat-ai-icon { width: 44px; height: 44px; min-width: 44px; font-size: 20px; }
      .feat-card-ai h4 { font-size: 15px; }
      .feat-card-ai p { font-size: 13px; }
    }

    /* ── Hambúrguer ──────────────────────────────────────── */
    .hamburger {
      display: none;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      gap: 5px;
      background: none;
      border: 1px solid var(--border);
      border-radius: 8px;
      width: 34px;
      height: 34px;
      cursor: pointer;
      padding: 0;
      flex-shrink: 0;
    }
    .hamburger span {
      display: block;
      width: 16px;
      height: 2px;
      background: var(--text-sub);
      border-radius: 2px;
      transition: transform .25s, opacity .25s;
    }
    .hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .hamburger.open span:nth-child(2) { opacity: 0; }
    .hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    /* ── Menu mobile dropdown ────────────────────────────── */
    .mobile-menu {
      display: none;
      position: fixed;
      top: 56px;
      left: 0; right: 0;
      background: var(--bg-card);
      border-bottom: 1px solid var(--border);
      box-shadow: 0 8px 24px rgba(0,0,0,.12);
      z-index: 99;
      padding: 12px 16px 16px;
      flex-direction: column;
      gap: 4px;
    }
    .mobile-menu.open { display: flex; }
    .mobile-menu-link {
      font-size: 15px;
      font-weight: 500;
      color: var(--text-sub);
      text-decoration: none;
      padding: 12px 16px;
      border-radius: 10px;
      transition: background .15s, color .15s;
    }
    .mobile-menu-link:hover { background: var(--bg); color: var(--text); }
    .mobile-menu-cta {
      display: block;
      text-align: center;
      font-size: 15px;
      font-weight: 600;
      color: #fff;
      background: var(--blue);
      text-decoration: none;
      padding: 13px 20px;
      border-radius: 10px;
      margin-top: 4px;
      box-shadow: 0 4px 14px rgba(52,152,219,.35);
    }

    /* ── Responsive ──────────────────────────────────────── */
    @media (max-width: 900px) {
      .features-grid { grid-template-columns: repeat(2, 1fr); }
      .mock-sidebar { display: none; }
      .mock-metrics { grid-template-columns: repeat(2, 1fr); }
    }

    /* ══════════════════════════════════════════════════════
       MOBILE (≤ 640px) — melhorias completas
       ══════════════════════════════════════════════════════ */
    @media (max-width: 640px) {

      /* Navbar */
      .top-nav { padding: 0 16px; height: 56px; }
      .nav-brand { font-size: 15px; }
      .nav-brand img { height: 28px; }
      .nav-link-plain { display: none; }
      .btn-nav { display: none; }
      .theme-toggle { width: 30px; height: 30px; font-size: 13px; }
      .hamburger { display: flex; }

      /* Hero */
      .hero { padding: 48px 0 40px; }
      .hero-card-icon { margin: 12px 0 16px; }
      .hero-eyebrow { font-size: 10px; padding: 4px 10px; margin-bottom: 16px; }
      .hero h1 { font-size: 30px; letter-spacing: -.02em; margin-bottom: 14px; line-height: 1.15; }
      .hero-sub { font-size: 15px; margin-bottom: 28px; padding: 0 4px; }

      /* Botões em coluna no mobile */
      .hero-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        margin-bottom: 36px;
        padding: 0 4px;
      }
      .btn-primary-lg, .btn-outline-lg {
        justify-content: center;
        font-size: 15px;
        padding: 14px 20px;
        border-radius: 12px;
      }

      /* Mockup compacto */
      .mockup-wrap {
        border-radius: 14px;
        animation: none;
        margin-top: 28px !important;
      }
      .mockup-titlebar { padding: 8px 12px; gap: 5px; }
      .dot { width: 8px; height: 8px; }
      .mockup-bar { height: 18px; }
      .mockup-bar span { font-size: 9px; }
      .mock-content { padding: 12px; gap: 8px; }
      .mock-header { font-size: 12px; }
      .mock-metrics { grid-template-columns: repeat(2, 1fr); gap: 6px; }
      .mock-metric { padding: 8px 10px; border-radius: 8px; }
      .mock-metric-label { font-size: 8px; }
      .mock-metric-val { font-size: 14px; }
      .mock-chart-area { padding: 10px 12px; }
      .mock-chart-title { font-size: 10px; }
      .mock-bars { height: 60px; gap: 4px; }
      .mock-bar-lbl { font-size: 7px; }

      /* Secções */
      section { padding: 48px 0; }
      .sect-title { font-size: 22px; }
      .sect-sub { font-size: 14px; }

      /* Features 1 coluna */
      .features-grid { grid-template-columns: 1fr; gap: 10px; }
      .feat-card { padding: 20px 18px; }
      .feat-icon { width: 38px; height: 38px; font-size: 16px; margin-bottom: 12px; }
      .feat-card h4 { font-size: 14px; }
      .feat-card p { font-size: 13px; }

      /* Metrics 2 colunas */
      .metrics-strip { grid-template-columns: repeat(2, 1fr); border-radius: 12px; }
      .m-cell { padding: 20px 12px; }
      .m-val { font-size: 26px; }
      .m-val i { font-size: 24px !important; }
      .m-lbl { font-size: 11px; }
      .m-cell:nth-child(even)::after { display: none; }

      /* Equipa */
      .team-grid { grid-template-columns: 1fr; max-width: 100%; gap: 10px; margin-top: 24px; }
      .team-card { padding: 24px 18px; }
      .team-avatar { width: 60px; height: 60px; font-size: 22px; }
      .team-name { font-size: 15px; }
      .quem-somos-inner { padding: 0 4px; }

      /* CTA */
      .quote-strip { padding: 32px 20px; border-radius: 14px; }
      .quote-strip h2 { font-size: 22px; margin-bottom: 10px; }
      .quote-strip p { font-size: 14px; margin-bottom: 20px; }
      .btn-white { font-size: 14px; padding: 12px 22px; }

      /* Footer */
      footer { padding: 32px 0 20px; }
      .footer-brand { font-size: 14px; }
      .footer-brand img { height: 24px; }
    }
  </style>
</head>
<body>
<canvas id="particles-canvas"></canvas>

<!-- NAV -->
<nav class="top-nav">
  <a href="index.php" class="nav-brand">
    <img src="assets/logo2.png" alt="Finora">
    <span>Finora</span>
  </a>
  <div class="nav-links">
    <a href="#quem-somos" class="nav-link-plain">Quem somos</a>
    <a href="auth/login.php" class="nav-link-plain">Entrar</a>
    <a href="auth/register.php" class="btn-nav">Começar grátis</a>
    <button class="theme-toggle" id="themeBtn" title="Alternar tema">
      <i class="bi bi-moon-fill" id="themeIcon"></i>
    </button>
    <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- Menu mobile -->
<div class="mobile-menu" id="mobileMenu">
  <a href="#quem-somos" class="mobile-menu-link" id="menuLinkQuem">Quem somos</a>
  <a href="auth/login.php" class="mobile-menu-link">Entrar</a>
  <a href="auth/register.php" class="mobile-menu-cta">Começar grátis</a>
</div>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <div class="hero-eyebrow">
      <i class="bi bi-stars"></i> 100% Gratuito · Sem cartão de crédito
    </div>
    <h1>Controla os teus <em id="typed-word"></em><span class="typed-cursor"></span><br>com simplicidade</h1>
    <p class="hero-sub">Gere cartões, transações e orçamentos num só lugar. Acompanha os teus gastos e mantém-te sempre dentro do orçamento.</p>
    <div class="hero-actions">
      <a href="auth/register.php" class="btn-primary-lg">
        Criar conta gratuita <i class="bi bi-arrow-right"></i>
      </a>
      <a href="auth/login.php" class="btn-outline-lg">
        Já tenho conta <i class="bi bi-box-arrow-in-right"></i>
      </a>
    </div>

    <!-- App mockup desktop -->
    <div class="mockup-wrap fade-up" style="margin-top:56px">
      <div class="mockup-titlebar">
        <div class="dot dot-r"></div>
        <div class="dot dot-y"></div>
        <div class="dot dot-g"></div>
        <div class="mockup-bar"><span>finora.app/dashboard</span></div>
      </div>
      <div class="mockup-body">
        <div class="mock-sidebar">
          <div class="mock-logo">
            <img src="assets/logo2.png" alt=""> Finora
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
            <div class="mock-chart-title"><i class="bi bi-graph-up"></i> Gastos mensais </div>
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
                <div class="mock-bar" style="height:<?=round($pct*.8)?>px;opacity:<?=$h>0?.85:.15?>"></div>
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
<section style="padding: 64px 0;">
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

    <!-- Carrossel mobile -->
    <div class="feat-carousel" id="featCarousel">
      <div class="feat-carousel-track" id="featTrack">
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
        <p>Gráficos mensais por categoria, comparação entre períodos e resumos instantâneos das tuas finanças.</p>
      </div>
      <div class="feat-card fade-up">
        <div class="feat-icon"><i class="bi bi-piggy-bank"></i></div>
        <h4>Orçamentos</h4>
        <p>Define limites por categoria ou cartão. Recebe alertas antes de ultrapassar os teus objetivos mensais.</p>
      </div>
      <div class="feat-card fade-up">
        <div class="feat-icon"><i class="bi bi-bell"></i></div>
        <h4>Lembretes</h4>
        <p>Cria lembretes para pagamentos, renovações ou qualquer data importante. Nunca mais te esqueças de uma prestação.</p>
      </div>
      <div class="feat-card fade-up">
        <div class="feat-icon"><i class="bi bi-person-hearts"></i></div>
        <h4>Personalidade Financeira</h4>
        <p>Descobre o teu perfil financeiro com base nos teus hábitos e recebe dicas adaptadas ao teu estilo de vida.</p>
      </div>
      <div class="feat-card fade-up">
        <div class="feat-icon"><i class="bi bi-hourglass-split"></i></div>
        <h4>Metas</h4>
        <p>Define um objetivo de poupança e simula se os teus hábitos atuais te vão lá chegar — com projeções realistas.</p>
      </div>
      <div class="feat-card fade-up">
        <div class="feat-icon"><i class="bi bi-qr-code-scan"></i></div>
        <h4>Ler Fatura QR</h4>
        <p>Aponta a câmara a qualquer fatura portuguesa e a transação é criada automaticamente pelo NIF.</p>
      </div>
      <div class="feat-card fade-up">
        <div class="feat-icon"><i class="bi bi-file-earmark-pdf"></i></div>
        <h4>Dados & Relatórios</h4>
        <p>Exporta as tuas transações e cartões em PDF, CSV ou JSON para arquivo pessoal ou partilha fácil.</p>
      </div>
      </div><!-- /feat-carousel-track -->
      <div class="feat-carousel-dots" id="featDots"></div>
    </div><!-- /feat-carousel -->

    <!-- Grid desktop (escondida no mobile via CSS) -->
    <div class="features-grid-desktop">
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
          <p>Gráficos mensais por categoria, comparação entre períodos e resumos instantâneos das tuas finanças.</p>
        </div>
        <div class="feat-card fade-up">
          <div class="feat-icon"><i class="bi bi-piggy-bank"></i></div>
          <h4>Orçamentos</h4>
          <p>Define limites por categoria ou cartão. Recebe alertas antes de ultrapassar os teus objetivos mensais.</p>
        </div>
        <div class="feat-card fade-up">
          <div class="feat-icon"><i class="bi bi-bell"></i></div>
          <h4>Lembretes</h4>
          <p>Cria lembretes para pagamentos, renovações ou qualquer data importante. Nunca mais te esqueças de uma prestação.</p>
        </div>
        <div class="feat-card fade-up">
          <div class="feat-icon"><i class="bi bi-person-hearts"></i></div>
          <h4>Personalidade Financeira</h4>
          <p>Descobre o teu perfil financeiro com base nos teus hábitos e recebe dicas adaptadas ao teu estilo de vida.</p>
        </div>
        <div class="feat-card fade-up">
          <div class="feat-icon"><i class="bi bi-hourglass-split"></i></div>
          <h4>Metaso</h4>
          <p>Define um objetivo de poupança e simula se os teus hábitos atuais te vão lá chegar — com projeções realistas.</p>
        </div>
        <div class="feat-card fade-up">
          <div class="feat-icon"><i class="bi bi-qr-code-scan"></i></div>
          <h4>Ler Fatura QR</h4>
          <p>Aponta a câmara a qualquer fatura portuguesa e a transação é criada automaticamente pelo NIF.</p>
        </div>
        <div class="feat-card fade-up">
          <div class="feat-icon"><i class="bi bi-file-earmark-pdf"></i></div>
          <h4>Dados & Relatórios</h4>
          <p>Exporta as tuas transações e cartões em PDF, CSV ou JSON para arquivo pessoal ou partilha fácil.</p>
        </div>
      </div>
    </div><!-- /features-grid-desktop -->

    <!-- Assistente IA — card de destaque (visível em desktop E mobile) -->
    <div class="feat-card-ai fade-up">
      <div class="feat-ai-glow"></div>
      <div class="feat-ai-inner">
        <div class="feat-ai-icon"><i class="bi bi-robot"></i></div>
        <div>
          <h4>Assistente IA <span class="feat-ai-badge">Novo</span></h4>
          <p>Insights automáticos do mês, sugestões de poupança personalizadas e um chat financeiro inteligente — tudo alimentado por IA.</p>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- METRICS -->
<section style="padding: 64px 0;">
  <div class="container">
    <div class="metrics-strip fade-up">
      <div class="m-cell">
        <div class="m-val" data-count="100" data-suffix="%">0%</div>
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

<!-- QUEM SOMOS -->
<section id="quem-somos" style="padding: 64px 0;">
  <div class="container">
    <div class="quem-somos-inner fade-up">
      <p class="sect-label">Quem somos</p>
      <h2 class="sect-title">A equipa por trás do Finora</h2>
      <p class="sect-sub">O Finora nasceu como Prova de Aptidão Profissional, com o objetivo de criar uma ferramenta simples e acessível para qualquer pessoa gerir os seus cartões e finanças pessoais sem custos.</p>
    </div>
    <div class="team-grid">
      <div class="team-card fade-up">
        <div class="team-avatar"><i class="bi bi-person-fill"></i></div>
        <div class="team-name">Diogo Freire</div>
        <div class="team-role">Desenvolvedor</div>
        <p class="team-desc">Responsável pela arquitetura do projeto, backend em PHP e integração da base de dados.</p>
      </div>
      <div class="team-card fade-up">
        <div class="team-avatar"><i class="bi bi-person-fill"></i></div>
        <div class="team-name">Jandro Antunes</div>
        <div class="team-role">Desenvolvedor</div>
        <p class="team-desc">Responsável pelo design da interface, experiência de utilizador e funcionalidades frontend.</p>
      </div>
    </div>
    <div class="text-center fade-up">
      <span class="pap-badge">
        <i class="bi bi-mortarboard-fill"></i> Prova de Aptidão Profissional
      </span>
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
          <img src="assets/logo2.png" alt="Finora">
          Finora
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
    <p class="footer-copy text-center mb-0">&copy; <?=date('Y')?> Finora. Todos os direitos reservados.</p>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Theme ──────────────────────────────────────────────────
const root      = document.documentElement;
const themeBtn  = document.getElementById('themeBtn');
const themeIcon = document.getElementById('themeIcon');

function applyTheme(t) {
  root.setAttribute('data-theme', t);
  themeIcon.className = t === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
  localStorage.setItem('fc_theme', t);
}

// Restaurar tema guardado
const saved = localStorage.getItem('fc_theme') || 'light';
applyTheme(saved);

themeBtn.addEventListener('click', () => {
  applyTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
});

// ── Partículas ─────────────────────────────────────────────
(function() {
  const canvas = document.getElementById('particles-canvas');
  const ctx    = canvas.getContext('2d');
  let   W, H, dots = [];

  function resize() {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
  }
  resize();
  window.addEventListener('resize', resize);

  function isDark() {
    return document.documentElement.getAttribute('data-theme') === 'dark';
  }

  for (let i = 0; i < 65; i++) {
    dots.push({
      x: Math.random() * window.innerWidth,
      y: Math.random() * window.innerHeight,
      vx: (Math.random() - .5) * .45,
      vy: (Math.random() - .5) * .45,
      r: Math.random() * 2 + 1
    });
  }

  function draw() {
    ctx.clearRect(0, 0, W, H);
    const dark     = isDark();
    const dotColor = dark ? 'rgba(200,160,100,.55)' : 'rgba(0,40,80,.30)';
    const lineBase = dark ? '200,160,100'           : '0,40,80';

    dots.forEach(d => {
      d.x += d.vx; d.y += d.vy;
      if (d.x < 0 || d.x > W) d.vx *= -1;
      if (d.y < 0 || d.y > H) d.vy *= -1;
      ctx.beginPath();
      ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
      ctx.fillStyle = dotColor;
      ctx.fill();
    });

    for (let i = 0; i < dots.length; i++) {
      for (let j = i + 1; j < dots.length; j++) {
        const dx = dots[i].x - dots[j].x;
        const dy = dots[i].y - dots[j].y;
        const dist = Math.sqrt(dx*dx + dy*dy);
        if (dist < 120) {
          ctx.beginPath();
          ctx.moveTo(dots[i].x, dots[i].y);
          ctx.lineTo(dots[j].x, dots[j].y);
          ctx.strokeStyle = `rgba(${lineBase},${(.18 * (1 - dist/120)).toFixed(3)})`;
          ctx.lineWidth = .8;
          ctx.stroke();
        }
      }
    }
    requestAnimationFrame(draw);
  }
  draw();
})();

// ── Parallax 3D ────────────────────────────────────────────
(function() {
  const mockup = document.querySelector('.mockup-wrap');
  if (!mockup) return;
  const hero = document.querySelector('.hero');

  hero.addEventListener('mousemove', e => {
    const rect   = hero.getBoundingClientRect();
    const cx     = rect.left + rect.width  / 2;
    const cy     = rect.top  + rect.height / 2;
    const dx     = (e.clientX - cx) / (rect.width  / 2);
    const dy     = (e.clientY - cy) / (rect.height / 2);
    const rotY   =  dx * 6;
    const rotX   = -dy * 4;
    mockup.style.transform = `perspective(900px) rotateX(${rotX}deg) rotateY(${rotY}deg) translateY(var(--float-y, 0px))`;
  });

  hero.addEventListener('mouseleave', () => {
    mockup.style.transition = 'transform .6s ease-out';
    mockup.style.transform  = 'perspective(900px) rotateX(0deg) rotateY(0deg)';
    setTimeout(() => mockup.style.transition = 'transform .08s ease-out', 600);
  });
})();

// ── Typing effect ──────────────────────────────────────────
const typedEl  = document.getElementById('typed-word');
const words    = ['cartões', 'orçamentos', 'gastos'];
let   wIdx = 0, cIdx = 0, deleting = false;

function tick() {
  const word = words[wIdx];
  if (deleting) {
    cIdx--;
    typedEl.textContent = word.substring(0, cIdx);
    if (cIdx === 0) {
      deleting = false;
      wIdx = (wIdx + 1) % words.length;
      setTimeout(tick, 400);
      return;
    }
  } else {
    typedEl.textContent = word.substring(0, cIdx + 1);
    cIdx++;
    if (cIdx === word.length) {
      setTimeout(() => { deleting = true; tick(); }, 2000);
      return;
    }
  }
  setTimeout(tick, deleting ? 55 : 100);
}
setTimeout(tick, 600);

// ── Contadores animados ─────────────────────────────────────
function animCount(el, target, suffix, dur) {
  const step = target / (dur / 16);
  let cur = 0;
  const t = setInterval(() => {
    cur = Math.min(cur + step, target);
    el.textContent = Math.round(cur) + suffix;
    if (cur >= target) clearInterval(t);
  }, 16);
}

const counterObs = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (!e.isIntersecting) return;
    e.target.querySelectorAll('[data-count]').forEach(el => {
      animCount(el, +el.dataset.count, el.dataset.suffix || '', 1400);
    });
    counterObs.unobserve(e.target);
  });
}, { threshold: 0.4 });

const metricsStrip = document.querySelector('.metrics-strip');
if (metricsStrip) counterObs.observe(metricsStrip);

const io = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); } });
}, { threshold: 0.12 });
document.querySelectorAll('.fade-up').forEach(el => io.observe(el));

// ── Carrossel mobile de funcionalidades ───────────────────
(function() {
  const track  = document.getElementById('featTrack');
  const dotsEl = document.getElementById('featDots');
  if (!track || !dotsEl) return;

  function isMobile() { return window.innerWidth <= 640; }

  let current = 0, startX = 0, diffX = 0, dragging = false;

  function getCards() { return Array.from(track.querySelectorAll('.feat-card')); }

  function buildDots() {
    dotsEl.innerHTML = '';
    if (!isMobile()) return;
    getCards().forEach((_, i) => {
      const d = document.createElement('span');
      d.className = 'feat-dot' + (i === 0 ? ' active' : '');
      d.addEventListener('click', () => goTo(i));
      dotsEl.appendChild(d);
    });
  }

  function goTo(idx) {
    const cards = getCards();
    if (!cards.length) return;
    current = Math.max(0, Math.min(idx, cards.length - 1));
    const cardW = cards[0].offsetWidth + 12;
    track.style.transform = `translateX(${-current * cardW}px)`;
    dotsEl.querySelectorAll('.feat-dot').forEach((d, i) => {
      d.classList.toggle('active', i === current);
    });
  }

  // Touch / mouse swipe
  function onStart(e) {
    if (!isMobile()) return;
    dragging = true;
    startX = e.touches ? e.touches[0].clientX : e.clientX;
    diffX = 0;
    track.classList.add('dragging');
  }
  function onMove(e) {
    if (!dragging) return;
    diffX = (e.touches ? e.touches[0].clientX : e.clientX) - startX;
    const cards = getCards();
    const cardW = cards[0].offsetWidth + 12;
    track.style.transform = `translateX(${-current * cardW + diffX}px)`;
  }
  function onEnd() {
    if (!dragging) return;
    dragging = false;
    track.classList.remove('dragging');
    if (diffX < -50) goTo(current + 1);
    else if (diffX > 50) goTo(current - 1);
    else goTo(current);
  }

  track.addEventListener('touchstart', onStart, { passive: true });
  track.addEventListener('touchmove',  onMove,  { passive: true });
  track.addEventListener('touchend',   onEnd);
  track.addEventListener('mousedown',  onStart);
  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup',   onEnd);

  window.addEventListener('resize', () => { buildDots(); goTo(0); });
  buildDots();
})();

// ── Menu hambúrguer mobile ─────────────────────────────────
(function() {
  const btn  = document.getElementById('hamburgerBtn');
  const menu = document.getElementById('mobileMenu');
  const linkQuem = document.getElementById('menuLinkQuem');
  if (!btn || !menu) return;

  function closeMenu() {
    btn.classList.remove('open');
    menu.classList.remove('open');
  }

  btn.addEventListener('click', () => {
    btn.classList.toggle('open');
    menu.classList.toggle('open');
  });

  // Fechar ao clicar num link
  menu.querySelectorAll('a').forEach(a => a.addEventListener('click', closeMenu));

  // Fechar ao clicar fora
  document.addEventListener('click', e => {
    if (!btn.contains(e.target) && !menu.contains(e.target)) closeMenu();
  });
})();
</script>
</body>
</html>