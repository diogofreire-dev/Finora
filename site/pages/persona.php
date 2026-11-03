<?php
// site/pages/persona.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
$uid = $_SESSION['user_id'] ?? null;
require_once __DIR__ . '/../helpers/theme_helper.php';
$currentTheme = getUserTheme($pdo, $uid);

// Gastos por categoria (últimos 3 meses)
$stmt = $pdo->prepare("
    SELECT COALESCE(category,'Outros') as category, SUM(amount) as total, COUNT(*) as cnt
    FROM transactions WHERE user_id=:uid AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    GROUP BY category ORDER BY total DESC
");
$stmt->execute([':uid'=>$uid]); $catData = $stmt->fetchAll();
$totalSpend3m = array_sum(array_column($catData,'total'));

// Gastos por dia da semana
$stmt = $pdo->prepare("
    SELECT DAYOFWEEK(transaction_date) as dow, COUNT(*) as cnt, SUM(amount) as total
    FROM transactions WHERE user_id=:uid AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    GROUP BY dow
");
$stmt->execute([':uid'=>$uid]); $dowData = [];
foreach ($stmt->fetchAll() as $r) $dowData[intval($r['dow'])] = $r;

$weekendSpend = ($dowData[1]['total'] ?? 0) + ($dowData[7]['total'] ?? 0);
$weekdaySpend = 0;
for ($d=2; $d<=6; $d++) $weekdaySpend += ($dowData[$d]['total'] ?? 0);

// Estatísticas gerais
$stmt = $pdo->prepare("SELECT COUNT(*), COALESCE(AVG(amount),0), COALESCE(STDDEV(amount),0) FROM transactions WHERE user_id=:uid AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)");
$stmt->execute([':uid'=>$uid]); [$totalTx, $avgTx, $stdTx] = array_values($stmt->fetch(PDO::FETCH_NUM));
$totalTx = intval($totalTx); $avgTx = floatval($avgTx); $stdTx = floatval($stdTx);

$topCat    = $catData[0]['category'] ?? null;
$topCatPct = $totalSpend3m > 0 ? (($catData[0]['total'] ?? 0) / $totalSpend3m) * 100 : 0;
$numCats   = count($catData);

// Tendência de gastos
$stmt = $pdo->prepare("
    SELECT YEAR(transaction_date) as y, MONTH(transaction_date) as m, SUM(amount) as total
    FROM transactions WHERE user_id=:uid AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 4 MONTH)
    GROUP BY y,m ORDER BY y,m
");
$stmt->execute([':uid'=>$uid]); $monthRows = array_values($stmt->fetchAll());
$isGrowing = count($monthRows) >= 2 && end($monthRows)['total'] > $monthRows[0]['total'];

// Motor de persona
$personas = [];
if ($weekendSpend > 0 && $weekdaySpend > 0) {
    $wr = $weekendSpend / ($weekendSpend + $weekdaySpend);
    if ($wr > 0.45) $personas['impulsivo_fds'] = $wr * 100;
}
$expCats = ['Restaurantes','Lazer','Viagens','Entretenimento','Comida','Cafés','Bar'];
$expSpend = 0;
foreach ($catData as $c) foreach ($expCats as $ec) if (stripos($c['category'],$ec)!==false) { $expSpend += $c['total']; break; }
if ($totalSpend3m > 0 && $expSpend / $totalSpend3m > 0.35) $personas['gastador_exp'] = ($expSpend/$totalSpend3m)*100;
if ($totalTx > 20 && $avgTx < 30) $personas['poupador_ansioso'] = 100 - ($avgTx*2);
if ($totalTx > 0 && $totalTx <= 10 && $avgTx > 80) $personas['minimalista'] = $avgTx/2;
if ($topCatPct > 55 && $numCats <= 4) $personas['concentrado'] = $topCatPct;
if ($numCats >= 8 && $stdTx > $avgTx) $personas['caotico'] = $numCats*5;

arsort($personas);
$topPersonaKey = !empty($personas) ? array_key_first($personas) : 'equilibrado';

$personaDefs = [
    'impulsivo_fds'    => ['emoji'=>'🎉','nome'=>'O Impulsivo de Fim de Semana','desc'=>'Os teus fins de semana são épicos — para a tua carteira também. Gastas uma fatia desproporcional do teu dinheiro entre sábado e domingo, provavelmente em saídas, refeições ou compras por impulso.','color'=>'#9b59b6','dicas'=>['Define um "orçamento de fim de semana" fixo e retira o dinheiro em cash no sábado de manhã. Quando acaba, acaba.','Antes de sair, decide o que vais gastar — não o que vais fazer. A ordem importa.','Usa a regra das 24h: se vires algo que queres comprar, espera até amanhã. A maioria das vontades passa.']],
    'gastador_exp'     => ['emoji'=>'🍽️','nome'=>'O Gastador de Experiências','desc'=>'Para ti, o dinheiro serve para viver — restaurantes, viagens, concertos. Não é necessariamente mau, mas é fácil perder o controlo quando cada "experiência" parece justificada por si mesma.','color'=>'#e67e22','dicas'=>['Cria uma categoria "Experiências do Mês" com um limite. Dentro dele, gasta sem culpa. Fora dele, pausa.','Faz um diário de experiências: quais foram mesmo memoráveis vs. quais foram apenas hábito?','Experimenta um "mês sem restaurantes" — substitui por cozinhar com amigos. Muitas vezes é mais memorável e custa 10x menos.']],
    'poupador_ansioso' => ['emoji'=>'😬','nome'=>'O Poupador Ansioso','desc'=>'Fazes muitas pequenas compras, mas cada uma parece inofensiva. O problema é que a soma de muitos "só €3" chega ao fim do mês e surpreende-te — é o efeito latte escalado.','color'=>'#3498db','dicas'=>['Agrupa as tuas pequenas compras. Em vez de comprar 5 vezes por semana, vai às compras 1 vez e leva tudo.','Usa a regra "1 semana sem compras pequenas" por mês. É mais fácil do que parece e o impacto é enorme.','Identifica o teu padrão emocional: compras mais quando estás entediado, stressado ou a celebrar?']],
    'minimalista'      => ['emoji'=>'🎯','nome'=>'O Minimalista Decidido','desc'=>'Poucas transações, mas impactantes. Não gastas muito — mas quando gastas, é a sério. Tens mais disciplina que a maioria, o teu risco é uma compra grande mal calculada.','color'=>'#27ae60','dicas'=>['Para compras acima de €100, usa sempre a regra das 72h antes de decidir.','Tens perfil para investir — a tua disciplina de não gastar é uma vantagem enorme. Já tens um plano de poupança?','Revisita as tuas grandes compras passadas: quantas foram mesmo necessárias? Isso dá-te o teu padrão real.']],
    'concentrado'      => ['emoji'=>'🔍','nome'=>'O Especialista de Uma Categoria','desc'=>'Mais de metade do teu dinheiro vai para uma única categoria. Pode ser totalmente legítimo — ou pode ser um ponto cego que nunca questionaste.','color'=>'#e74c3c','dicas'=>['Pergunta-te: esta categoria reflete as tuas prioridades ou é apenas hábito?','Define um tecto para essa categoria durante 1 mês e vê o que acontece ao teu nível de satisfação.','Diversifica os teus gastos intencionalmente — às vezes descobrimos que gastamos muito numa área só porque nunca experimentámos alternativas.']],
    'caotico'          => ['emoji'=>'🌪️','nome'=>'O Caótico Criativo','desc'=>'Os teus gastos são imprevisíveis — muitas categorias diferentes, valores muito variados. Provavelmente não há dois meses iguais. Isso pode ser riqueza de vida ou falta de intenção.','color'=>'#f39c12','dicas'=>['Começa por apenas 3 categorias: Necessidades, Lazer, e Resto. Simplifica antes de optimizar.','Faz uma auditoria mensal de 10 minutos: olha para os teus gastos e pergunta "isto era planeado ou aconteceu e pronto?"','A imprevisibilidade não é o problema — a falta de consciência é. Um diário financeiro semanal muda tudo.']],
    'equilibrado'      => ['emoji'=>'⚖️','nome'=>'O Equilibrado Consciente','desc'=>'Os teus padrões de gasto não revelam um perfil extremo. Tens uma distribuição relativamente equilibrada — isso é bom, mas pode também significar que ainda não há dados suficientes para uma análise mais profunda.','color'=>'#3498db','dicas'=>['Continua a registar transações — com mais histórico a análise fica muito mais precisa e útil.','Experimenta definir um objectivo financeiro concreto. Mesmo quem é equilibrado beneficia de uma direcção clara.','Revisita esta página daqui a 1 mês — a tua persona pode mudar com os hábitos.']],
];
$persona = $personaDefs[$topPersonaKey];

$scoreLabels = [
    'impulsivo_fds'=>['🎉','Impulsivo de Fim de Semana'],
    'gastador_exp'=>['🍽️','Gastador de Experiências'],
    'poupador_ansioso'=>['😬','Poupador Ansioso'],
    'minimalista'=>['🎯','Minimalista Decidido'],
    'concentrado'=>['🔍','Concentrado numa Categoria'],
    'caotico'=>['🌪️','Caótico Criativo'],
];

// Dados para gráfico
$dowLabels = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
$dowTotals = []; for ($d=1; $d<=7; $d++) $dowTotals[] = round($dowData[$d]['total'] ?? 0, 2);

$activePage='persona'; $sidebarPrefix='../';
?>
<!doctype html>
<html lang="pt-PT" data-theme="<?=$currentTheme?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Personalidade Financeira — Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="stylesheet" href="../css/sidebar.css?v=6">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background-color:var(--bg-primary); color:var(--text-primary); }
    .persona-wrapper { max-width:1000px; margin:0 auto; padding:0 16px 60px; }
    .page-header { display:flex; align-items:center; gap:14px; margin-bottom:28px; padding-top:8px; }
    .page-header-icon { width:42px; height:42px; border-radius:12px; background:var(--bg-secondary); border:1px solid var(--border-color); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
    .page-header h2 { font-size:20px; font-weight:600; margin:0 0 2px; }
    .page-header p  { font-size:13px; color:var(--text-secondary); margin:0; }
    .a-group { margin-bottom:20px; }
    .group-label { font-size:11px; font-weight:600; letter-spacing:.07em; text-transform:uppercase; color:var(--text-secondary); margin:0 0 8px 4px; }
    .a-card { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; overflow:hidden; }
    .a-card-body { padding:20px; }
    .a-card-title { font-size:14px; font-weight:600; color:var(--text-primary); margin:0 0 16px; display:flex; align-items:center; gap:8px; }
    .a-card-title i { color:var(--fn-accent); font-size:16px; }
    /* Métricas */
    .metrics-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:12px; }
    .metric { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:12px; padding:16px; }
    .metric-label { font-size:11px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
    .metric-value { font-size:22px; font-weight:700; color:var(--text-primary); line-height:1; }
    .metric-value.blue  { color:var(--fn-accent); }
    .metric-value.green { color:#1abc9c; }
    .metric-value.amber { color:#f39c12; }
    .metric-value.red   { color:#e74c3c; }
    .metric-sub { font-size:11px; color:var(--text-secondary); margin-top:4px; }
    /* Persona banner */
    .persona-banner { border-radius:14px; border:1px solid var(--border-color); padding:24px; margin-bottom:0; }
    .persona-emoji  { font-size:44px; line-height:1; margin-bottom:10px; display:block; }
    .persona-badge  { display:inline-block; font-size:10px; font-weight:700; letter-spacing:.8px; text-transform:uppercase; padding:3px 8px; border-radius:20px; margin-bottom:6px; }
    .persona-name   { font-size:22px; font-weight:700; margin:0 0 10px; }
    .persona-desc   { font-size:13px; line-height:1.7; color:var(--text-secondary); margin:0; }
    /* Dicas */
    .tip-item { display:flex; gap:12px; align-items:flex-start; padding:12px 0; border-bottom:1px solid var(--border-color); }
    .tip-item:last-child { border-bottom:none; padding-bottom:0; }
    .tip-num { width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:800; color:#fff; flex-shrink:0; margin-top:1px; }
    .tip-text { font-size:13px; line-height:1.6; color:var(--text-primary); }
    /* Scores */
    .score-bar { height:5px; border-radius:3px; background:var(--border-color); margin:4px 0 10px; overflow:hidden; }
    .score-fill { height:100%; border-radius:3px; }
    .score-label { display:flex; justify-content:space-between; font-size:12px; color:var(--text-secondary); }
    /* Barras de categoria */
    .cat-bar-item { margin-bottom:12px; }
    .cat-bar-header { display:flex; justify-content:space-between; margin-bottom:5px; font-size:13px; }
    .cat-bar-name { font-weight:500; color:var(--text-primary); }
    .cat-bar-val  { color:var(--text-secondary); font-size:12px; }
    .cat-bar-track { height:6px; background:var(--border-color); border-radius:3px; overflow:hidden; }
    .cat-bar-fill  { height:100%; border-radius:3px; }
    /* Top/bottom items */
    .top-item { display:flex; align-items:center; gap:12px; padding:12px 0; }
    .top-item:not(:last-child) { border-bottom:1px solid var(--border-color); }
    /* Empty */
    .empty-state { text-align:center; padding:48px 20px; }
    .empty-state i { font-size:48px; color:var(--border-color); display:block; margin-bottom:12px; }
    .empty-state h5 { font-size:15px; color:var(--text-secondary); margin-bottom:6px; }
    .empty-state p  { font-size:13px; color:var(--text-secondary); }
    @media(max-width:900px) { .metrics-grid { grid-template-columns:repeat(3,1fr); } .two-col { grid-template-columns:1fr !important; } }
    @media(max-width:560px) { .metrics-grid { grid-template-columns:1fr 1fr; } }
  </style>
  <link rel="stylesheet" href="../css/finora.css?v=6">
</head>
<body>
<div class="app-layout">
<?php include __DIR__ . '/../components/sidebar.php'; ?>
<main class="main-content">
<div class="page-content">
<div class="persona-wrapper">

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-person-badge"></i></div>
    <div>
      <h2>Personalidade Financeira</h2>
      <p>Com base nos teus padrões reais de gasto — actualiza-se automaticamente</p>
    </div>
  </div>

  <?php if ($totalSpend3m == 0): ?>
  <div class="a-card">
    <div class="empty-state">
      <i class="bi bi-person-bounding-box"></i>
      <h5>Sem dados suficientes</h5>
      <p>Regista algumas transações e volta aqui para descobrir o teu perfil financeiro.</p>
    </div>
  </div>
  <?php else: ?>

  <!-- Métricas -->
  <div class="a-group">
    <p class="group-label">Resumo dos últimos 3 meses</p>
    <div class="metrics-grid">
      <div class="metric">
        <p class="metric-label">Total gasto</p>
        <p class="metric-value blue"><?=fmt_eur($totalSpend3m, 0)?></p>
        <p class="metric-sub">~<?=fmt_eur($totalSpend3m/3, 0)?>/mês</p>
      </div>
      <div class="metric">
        <p class="metric-label">Transações</p>
        <p class="metric-value"><?=$totalTx?></p>
        <p class="metric-sub">em 3 meses</p>
      </div>
      <div class="metric">
        <p class="metric-label">Média por transação</p>
        <p class="metric-value amber"><?=fmt_eur($avgTx, 2)?></p>
        <p class="metric-sub">±<?=fmt_eur($stdTx, 2)?></p>
      </div>
      <div class="metric">
        <p class="metric-label">Categorias usadas</p>
        <p class="metric-value"><?=$numCats?></p>
        <p class="metric-sub">tipos distintos</p>
      </div>
      <div class="metric">
        <p class="metric-label">Fim de semana</p>
        <p class="metric-value <?= ($weekendSpend+$weekdaySpend)>0 && $weekendSpend/($weekendSpend+$weekdaySpend)>0.45 ? 'red' : 'green'?>">
          <?=($weekendSpend+$weekdaySpend)>0 ? round($weekendSpend/($weekendSpend+$weekdaySpend)*100).'%' : '—'?>
        </p>
        <p class="metric-sub">dos gastos</p>
      </div>
    </div>
  </div>

  <!-- Persona banner + dicas -->
  <div class="a-group">
    <p class="group-label">A tua personalidade</p>
    <div style="display:grid;grid-template-columns:1fr 1.1fr;gap:12px" class="two-col">

      <div class="a-card">
        <div class="a-card-body">
          <span class="persona-emoji"><?=$persona['emoji']?></span>
          <span class="persona-badge" style="background:<?=$persona['color']?>22;color:<?=$persona['color']?>">Personalidade detectada</span>
          <p class="persona-name" style="color:<?=$persona['color']?>"><?=$persona['nome']?></p>
          <p class="persona-desc"><?=$persona['desc']?></p>
          <?php if (count($personas) > 1): ?>
          <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--border-color)">
            <p style="font-size:11px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px">Outros traços detectados</p>
            <?php foreach ($personas as $key => $score):
              $sl  = $scoreLabels[$key] ?? ['❓',$key];
              $pct = min(100, round($score));
            ?>
            <div class="score-label"><span><?=$sl[0]?> <?=$sl[1]?></span><span><?=$pct?>%</span></div>
            <div class="score-bar"><div class="score-fill" style="width:<?=$pct?>%;background:<?=$persona['color']?>"></div></div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <p style="font-size:11px;color:var(--text-secondary);margin-top:14px">
            <?= $isGrowing
              ? '<span style="color:#e74c3c"><i class="bi bi-arrow-up"></i> Gastos a crescer vs meses anteriores</span>'
              : '<span style="color:#1abc9c"><i class="bi bi-arrow-down"></i> Gastos estáveis ou a diminuir</span>'
            ?>
          </p>
        </div>
      </div>

      <div class="a-card">
        <div class="a-card-body">
          <p class="a-card-title"><i class="bi bi-lightbulb"></i> Dicas para o teu perfil</p>
          <?php foreach ($persona['dicas'] as $i => $dica): ?>
          <div class="tip-item">
            <div class="tip-num" style="background:<?=$persona['color']?>"><?=$i+1?></div>
            <div class="tip-text"><?=$dica?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>
  </div>

  <!-- Gráfico + categorias -->
  <div class="a-group">
    <p class="group-label">Detalhe</p>
    <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:12px" class="two-col">

      <div class="a-card">
        <div class="a-card-body">
          <p class="a-card-title"><i class="bi bi-calendar3-week"></i> Gastos por dia da semana</p>
          <div style="position:relative;height:200px">
            <canvas id="dowChart"></canvas>
          </div>
        </div>
      </div>

      <div class="a-card">
        <div class="a-card-body">
          <p class="a-card-title"><i class="bi bi-grid-3x3-gap"></i> Top categorias</p>
          <?php foreach (array_slice($catData,0,6) as $c):
            $pct = $totalSpend3m > 0 ? ($c['total']/$totalSpend3m)*100 : 0;
          ?>
          <div class="cat-bar-item">
            <div class="cat-bar-header">
              <span class="cat-bar-name"><?=htmlspecialchars($c['category'])?></span>
              <span class="cat-bar-val"><?=fmt_eur($c['total'], 2)?> (<?=round($pct)?>%)</span>
            </div>
            <div class="cat-bar-track">
              <div class="cat-bar-fill" style="width:<?=$pct?>%;background:<?=$persona['color']?>"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>
  </div>

  <p style="font-size:12px;color:var(--text-secondary);text-align:center;margin-top:4px">
    <i class="bi bi-arrow-repeat"></i> Análise baseada nos últimos 3 meses · Actualiza-se com cada nova transação
  </p>

  <?php endif; ?>

</div>
</div>
</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const isDark       = document.documentElement.dataset.theme === 'dark';
const gridColor    = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
const labelColor   = isDark ? '#95a5a6' : '#7f8c8d';
const personaColor = '<?=$persona['color']?>';
const dowData      = <?=json_encode($dowTotals)?>;
const dowCtx       = document.getElementById('dowChart');
if (dowCtx) {
    new Chart(dowCtx, {
        type: 'bar',
        data: {
            labels: <?=json_encode($dowLabels)?>,
            datasets: [{
                label: 'Gasto (€)',
                data: dowData,
                backgroundColor: dowData.map((_, i) =>
                    (i === 0 || i === 6) ? personaColor : (isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.07)')),
                borderRadius: 5,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: gridColor }, ticks: { color: labelColor, font: { size: 11 } } },
                y: { grid: { color: gridColor }, ticks: { color: labelColor, font: { size: 11 }, callback: v => '€'+v } }
            }
        }
    });
}
</script>
</body>
</html>