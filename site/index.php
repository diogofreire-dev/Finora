<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FreeCard - Gestão de Cartões e Transações</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    :root {
      --primary-green: #3498db;
      --dark-green: #2980b9;
      --light-green: #a8d0e6;
      --darker-green: #1f4e79;
      --very-light-green: #d5e6f4;
    }
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      overflow-x: hidden;
    }
    
    /* Navbar com glassmorphism */
    .navbar {
      padding: 20px 0;
      background: rgba(255, 255, 255, 0.95) !important;
      backdrop-filter: blur(10px);
      box-shadow: 0 2px 20px rgba(0,0,0,0.08);
      position: sticky;
      top: 0;
      z-index: 1000;
      transition: all 0.3s;
    }
    
    .navbar.scrolled {
      padding: 15px 0;
      box-shadow: 0 5px 30px rgba(46, 88, 204, 0.15);
    }
    
    .navbar-brand {
      font-weight: 700;
      font-size: 24px;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: all 0.3s;
    }
    
    .navbar-brand img {
      height: 40px;
      transition: transform 0.3s;
    }
    
    .navbar-brand:hover img {
      transform: rotate(5deg) scale(1.05);
    }
    
    /* Hero Section com tema verde */
    .hero {
      min-height: 100vh;
      background: linear-gradient(135deg, var(--dark-green) 0%, var(--darker-green) 100%);
      color: white;
      padding: 80px 0;
      position: relative;
      overflow: hidden;
      display: flex;
      align-items: center;
    }
    
    /* Animated background shapes */
    .hero::before {
      content: '';
      position: absolute;
      width: 500px;
      height: 500px;
      background: rgba(255, 255, 255, 0.08);
      border-radius: 50%;
      top: -250px;
      right: -100px;
      animation: float 6s ease-in-out infinite;
    }
    
    .hero::after {
      content: '';
      position: absolute;
      width: 300px;
      height: 300px;
      background: rgba(255, 255, 255, 0.06);
      border-radius: 50%;
      bottom: -150px;
      left: -50px;
      animation: float 8s ease-in-out infinite reverse;
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0) rotate(0deg); }
      50% { transform: translateY(-20px) rotate(5deg); }
    }
    
    .hero-content {
      position: relative;
      z-index: 2;
      animation: fadeInUp 1s ease-out;
    }
    
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .hero h1 {
      font-size: 56px;
      font-weight: 900;
      margin-bottom: 24px;
      line-height: 1.2;
      text-shadow: 0 4px 20px rgba(0,0,0,0.2);
    }
    
    .hero h1 span {
      background: linear-gradient(45deg, #fff, var(--light-green));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      display: inline-block;
      animation: shimmer 3s ease-in-out infinite;
    }
    
    @keyframes shimmer {
      0%, 100% { filter: brightness(1); }
      50% { filter: brightness(1.2); }
    }
    
    .hero p {
      font-size: 22px;
      margin-bottom: 40px;
      opacity: 0.95;
      animation: fadeInUp 1s ease-out 0.2s both;
    }
    
    .hero-buttons {
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
      animation: fadeInUp 1s ease-out 0.4s both;
    }
    
    .btn-hero {
      background: white;
      color: var(--dark-green);
      border: none;
      padding: 16px 40px;
      border-radius: 50px;
      font-weight: 700;
      font-size: 16px;
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-hero:hover {
      transform: translateY(-5px) scale(1.05);
      box-shadow: 0 15px 40px rgba(0,0,0,0.3);
      background: var(--very-light-green);
    }
    
    .btn-hero-outline {
      background: transparent;
      color: white;
      border: 2px solid white;
      padding: 16px 40px;
      border-radius: 50px;
      font-weight: 700;
      font-size: 16px;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-hero-outline:hover {
      background: white;
      color: var(--dark-green);
      transform: translateY(-5px);
      box-shadow: 0 15px 40px rgba(255,255,255,0.3);
    }
    
    /* Mockup de Cartões Flutuantes - tema verde */
    .cards-mockup {
      position: relative;
      height: 500px;
      animation: fadeInUp 1s ease-out 0.3s both;
    }
    
    .card-float {
      position: absolute;
      width: 320px;
      height: 200px;
      border-radius: 20px;
      padding: 24px;
      color: white;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      transition: all 0.3s;
      cursor: pointer;
    }
    
    .card-float:hover {
      transform: translateY(-10px) scale(1.05);
      box-shadow: 0 30px 80px rgba(0,0,0,0.4);
    }
    
    .card-float-1 {
      background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
      top: 50px;
      left: 50px;
      animation: floatCard1 6s ease-in-out infinite;
    }
    
    .card-float-2 {
      background: linear-gradient(135deg, var(--dark-green) 0%, var(--darker-green) 100%);
      top: 150px;
      right: 100px;
      animation: floatCard2 7s ease-in-out infinite;
    }
    
    .card-float-3 {
      background: linear-gradient(135deg, var(--light-green) 0%, var(--primary-green) 100%);
      bottom: 80px;
      left: 150px;
      animation: floatCard3 8s ease-in-out infinite;
    }
    
    @keyframes floatCard1 {
      0%, 100% { transform: translateY(0) rotate(-5deg); }
      50% { transform: translateY(-15px) rotate(-3deg); }
    }
    
    @keyframes floatCard2 {
      0%, 100% { transform: translateY(0) rotate(5deg); }
      50% { transform: translateY(-20px) rotate(3deg); }
    }
    
    @keyframes floatCard3 {
      0%, 100% { transform: translateY(0) rotate(-3deg); }
      50% { transform: translateY(-10px) rotate(-5deg); }
    }
    
    .card-float-number {
      font-size: 20px;
      font-family: 'Courier New', monospace;
      margin-top: 20px;
      letter-spacing: 2px;
    }
    
    .card-float-name {
      font-size: 14px;
      text-transform: uppercase;
      margin-top: 30px;
      opacity: 0.9;
    }
    
    .card-float-chip {
      width: 40px;
      height: 30px;
      background: rgba(255,255,255,0.3);
      border-radius: 6px;
      margin-bottom: 10px;
    }
    
    /* Features Section */
    .feature-section {
      padding: 120px 0;
      background: #f8f9fa;
      position: relative;
    }
    
    .section-title {
      font-size: 48px;
      font-weight: 900;
      color: #2c3e50;
      margin-bottom: 20px;
      text-align: center;
    }
    
    .section-subtitle {
      font-size: 20px;
      color: #7f8c8d;
      margin-bottom: 60px;
      text-align: center;
    }
    
    .feature-card {
      background: white;
      border-radius: 24px;
      padding: 40px;
      border: 1px solid #e0e0e0;
      height: 100%;
      position: relative;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    
    .feature-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 24px;
      color: white;
      font-size: 36px;
      box-shadow: 0 10px 30px rgba(46, 88, 204, 0.15);
    }
    
    .feature-card:nth-child(2) .feature-icon {
      background: linear-gradient(135deg, var(--dark-green), var(--darker-green));
    }
    
    .feature-card:nth-child(3) .feature-icon {
      background: linear-gradient(135deg, var(--light-green), var(--primary-green));
    }

    .feature-card:nth-child(4) .feature-icon {
      background: linear-gradient(135deg, var(--primary-green), var(--light-green));
    }

    .feature-card:nth-child(5) .feature-icon {
      background: linear-gradient(135deg, var(--dark-green), var(--primary-green));
    }
    
    .feature-card h4 {
      font-size: 24px;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 16px;
    }
    
    .feature-card p {
      color: #7f8c8d;
      font-size: 16px;
      line-height: 1.7;
      margin: 0;
    }
    
    /* Stats Section com verde */
    .stats-section {
      background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
      padding: 100px 0;
      color: white;
      position: relative;
      overflow: hidden;
    }
    
    .stats-section::before {
      content: '';
      position: absolute;
      width: 400px;
      height: 400px;
      background: rgba(46, 146, 204, 0.05);
      border-radius: 50%;
      top: -200px;
      right: -100px;
    }
    
    .stat-box {
      text-align: center;
      padding: 30px;
      transition: transform 0.3s;
      cursor: pointer;
    }
    
    .stat-box:hover {
      transform: scale(1.1);
    }
    
    .stat-box h2, .stat-box h2 i {
      font-size: 64px;
      font-weight: 900;
      margin-bottom: 10px;
      background: linear-gradient(45deg, var(--primary-green), var(--light-green));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    
    .stat-box p {
      font-size: 18px;
      opacity: 0.9;
      margin: 0;
    }
    
    /* CTA Section verde */
    .cta-section {
      padding: 120px 0;
      background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
      position: relative;
      overflow: hidden;
    }
    
    .cta-section::before {
      content: '';
      position: absolute;
      width: 300px;
      height: 300px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      bottom: -150px;
      right: -50px;
      animation: float 10s ease-in-out infinite;
    }
    
    .cta-content {
      position: relative;
      z-index: 2;
      text-align: center;
      color: white;
    }
    
    .cta-content h2 {
      font-size: 48px;
      font-weight: 900;
      margin-bottom: 20px;
      text-shadow: 0 4px 20px rgba(0,0,0,0.2);
    }
    
    .cta-content p {
      font-size: 20px;
      margin-bottom: 40px;
      opacity: 0.95;
    }
    
    .btn-cta {
      background: white;
      color: var(--dark-green);
      border: none;
      padding: 20px 50px;
      border-radius: 50px;
      font-weight: 700;
      font-size: 18px;
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      box-shadow: 0 15px 40px rgba(0,0,0,0.3);
      display: inline-flex;
      align-items: center;
      gap: 10px;
    }
    
    .btn-cta:hover {
      transform: translateY(-8px) scale(1.05);
      box-shadow: 0 20px 50px rgba(0,0,0,0.4);
      background: var(--very-light-green);
    }
    
    /* Footer Moderno */
    footer {
      background: #1a1d29;
      color: white;
      padding: 60px 0 30px;
    }
    
    footer h5 {
      font-weight: 700;
      margin-bottom: 20px;
    }
    
    footer a {
      color: var(--light-green);
      text-decoration: none;
      transition: all 0.3s;
      display: inline-block;
    }
    
    footer a:hover {
      color: var(--primary-green);
      transform: translateX(5px);
    }
    
    /* Scroll Animation */
    .fade-in {
      opacity: 0;
      transform: translateY(30px);
      transition: opacity 0.6s, transform 0.6s;
    }
    
    .fade-in.visible {
      opacity: 1;
      transform: translateY(0);
    }
    
    /* Pulse animation for icons */
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }
    
    .stat-box h2 i {
      animation: pulse 2s ease-in-out infinite;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .hero h1 {
        font-size: 36px;
      }
      
      .hero p {
        font-size: 18px;
      }
      
      .cards-mockup {
        display: none;
      }
      
      .section-title {
        font-size: 32px;
      }
      
      .stat-box h2 {
        font-size: 48px;
      }
      
      .hero-buttons {
        flex-direction: column;
      }
      
      .btn-hero,
      .btn-hero-outline {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light">
  <div class="container">
    <a class="navbar-brand" href="index.php">
      <img src="assets/logo2.png" alt="Freecard">
      <span>FreeCard</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item me-3">
          <a class="nav-link" href="auth/login.php">Entrar</a>
        </li>
        <li class="nav-item">
          <a class="btn btn-primary" href="auth/register.php" style="border-radius: 50px; padding: 10px 30px;">Começar agora</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<section class="hero">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6 hero-content">
        <h1>Controla os teus <span>cartões</span> com simplicidade</h1>
        <p>Gere os teus cartões e transações de forma inteligente. Acompanha os teus gastos e mantém-te sempre dentro do orçamento.</p>
        <div class="hero-buttons">
          <a href="auth/register.php" class="btn-hero">
            Começar gratuitamente
            <i class="bi bi-arrow-right"></i>
          </a>
          <a href="auth/login.php" class="btn-hero-outline">
            Já tenho conta
            <i class="bi bi-box-arrow-in-right"></i>
          </a>
        </div>
      </div>
      <div class="col-lg-6 d-none d-lg-block">
        <div class="cards-mockup">
          <div class="card-float card-float-1">
            <div class="card-float-chip"></div>
            <div class="card-float-number">•••• ••••</div>
            <div class="card-float-name">Platinum Card</div>
          </div>
          <div class="card-float card-float-2">
            <div class="card-float-chip"></div>
            <div class="card-float-number">•••• ••••</div>
            <div class="card-float-name">Gold Card</div>
          </div>
          <div class="card-float card-float-3">
            <div class="card-float-chip"></div>
            <div class="card-float-number">•••• ••••</div>
            <div class="card-float-name">Silver Card</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="feature-section">
  <div class="container">
    <h2 class="section-title fade-in">Tudo o que precisas para gerir os teus cartões</h2>
    <p class="section-subtitle fade-in">Ferramentas poderosas para controlo total</p>
    <div class="row g-4">
      <div class="col-lg-4 col-md-6 fade-in">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="bi bi-credit-card-2-front"></i>
          </div>
          <h4>Gestão de Cartões</h4>
          <p>Adiciona e organiza todos os teus cartões de crédito num só lugar. Acompanha limites, saldos e mantém o controlo total com alertas inteligentes.</p>
        </div>
      </div>
      <div class="col-lg-4 col-md-6 fade-in">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="bi bi-receipt"></i>
          </div>
          <h4>Registo de Transações</h4>
          <p>Regista todas as tuas despesas com descrições detalhadas e categorias. Associa cada transação ao cartão correspondente e mantém histórico completo.</p>
        </div>
      </div>
      <div class="col-lg-4 col-md-6 fade-in">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="bi bi-graph-up"></i>
          </div>
          <h4>Análise de Gastos</h4>
          <p>Visualiza resumos mensais com gráficos interativos, recebe alertas quando te aproximas dos limites e analisa o histórico completo das tuas finanças.</p>
        </div>
      </div>
      <div class="col-lg-4 col-md-6 fade-in">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="bi bi-piggy-bank"></i>
          </div>
          <h4>Definição de Orçamentos</h4>
          <p>Define orçamentos para categorias ou cartões específicos. Acompanha os gastos em tempo real e recebe alertas quando te aproximas dos limites estabelecidos.</p>
        </div>
      </div>
      <div class="col-lg-4 col-md-6 fade-in">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="bi bi-calendar-check"></i>
          </div>
          <h4>Lembretes de Pagamento</h4>
          <p>Cria lembretes para pagamentos recorrentes e despesas futuras. Recebe notificações antecipadas e mantém o controlo sobre todas as tuas obrigações financeiras.</p>
        </div>
      </div>
      <div class="col-lg-4 col-md-6 fade-in">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="bi bi-file-earmark-pdf"></i>
          </div>
          <h4>Exportar Relatórios PDF</h4>
          <p>Exporta relatórios detalhados das tuas transações e cartões em formato PDF. Perfeito para arquivo pessoal ou partilha de informações financeiras.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="stats-section">
  <div class="container">
    <div class="row">
      <div class="col-md-3 fade-in">
        <div class="stat-box">
          <h2>100%</h2>
          <p>Gratuito</p>
        </div>
      </div>
      <div class="col-md-3 fade-in">
        <div class="stat-box">
          <h2><i class="bi bi-shield-check"></i></h2>
          <p>Seguro e Protegido</p>
        </div>
      </div>
      <div class="col-md-3 fade-in">
        <div class="stat-box">
          <h2><i class="bi bi-lightning-charge"></i></h2>
          <p>Rápido e Simples</p>
        </div>
      </div>
      <div class="col-md-3 fade-in">
        <div class="stat-box">
          <h2><i class="bi bi-phone"></i></h2>
          <p>100% Responsivo</p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="cta-section">
  <div class="container">
    <div class="cta-content fade-in">
      <h2>Pronto para começar?</h2>
      <p>Cria a tua conta gratuitamente e começa a gerir as tuas finanças de forma inteligente.</p>
      <a href="auth/register.php" class="btn-cta">
        <i class="bi bi-check-circle-fill"></i>
        Criar conta gratuita
      </a>
    </div>
  </div>
</section>

<footer>
  <div class="container">
    <div class="row">
      <div class="col-md-6 mb-4 mb-md-0">
        <h5>
          <img src="assets/logo2.png" alt="Freecard" style="height: 30px; margin-right: 10px; filter: brightness(0) invert(1);">
          FreeCard
        </h5>
        <p class="text-light">Gestão de cartões e transações.<br>Simples, rápido e gratuito.</p>
      </div>
      <div class="col-md-3 mb-4 mb-md-0">
        <h6 class="mb-3">Links Rápidos</h6>
        <ul class="list-unstyled">
          <li class="mb-2"><a href="auth/register.php"><i class="bi bi-arrow-right"></i> Criar Conta</a></li>
          <li class="mb-2"><a href="auth/login.php"><i class="bi bi-arrow-right"></i> Entrar</a></li>
        </ul>
      </div>
      <div class="col-md-3">
        <h6 class="mb-3">Projeto</h6>
        <p class="text-light small">Prova de Aptidão Profissional<br>Desenvolvido por Diogo Freire e Jandro Antunes</p>
      </div>
    </div>
    <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">
    <div class="text-center">
      <p class="mb-0 text-light">&copy; 2025 FreeCard. Todos os direitos reservados.</p>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Navbar scroll effect
window.addEventListener('scroll', function() {
  const navbar = document.querySelector('.navbar');
  if (window.scrollY > 50) {
    navbar.classList.add('scrolled');
  } else {
    navbar.classList.remove('scrolled');
  }
});

// Scroll animation
const observerOptions = {
  threshold: 0.1,
  rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver(function(entries) {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
    }
  });
}, observerOptions);

document.querySelectorAll('.fade-in').forEach(element => {
  observer.observe(element);
});
</script>
</body>
</html>