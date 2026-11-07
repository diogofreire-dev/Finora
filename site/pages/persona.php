<?php
// site/pages/persona.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/theme_helper.php';
require_once __DIR__ . '/../helpers/categories.php';
$uid = $_SESSION['user_id'] ?? null;
$currentTheme = getUserTheme($pdo, $uid);

const PERSONA_DAYS    = 90;  // janela analisada
const PERSONA_MIN_TX  = 10;  // abaixo disto ainda não há padrão nenhum
const WEEKEND_NORMAL  = 2 / 7;

// ── Dados dos últimos 90 dias (as contas de datas ficam no MySQL) ───────────
$win = "user_id = :uid AND transaction_date > CURDATE() - INTERVAL " . PERSONA_DAYS . " DAY AND transaction_date <= CURDATE()";

$stmt = $pdo->prepare("SELECT COALESCE(category, 'Outros') AS category, SUM(amount) AS total, COUNT(*) AS n
                       FROM transactions WHERE $win GROUP BY category ORDER BY total DESC");
$stmt->execute([':uid' => $uid]);
$catData = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total, COALESCE(AVG(amount), 0) AS avg,
                              COALESCE(STDDEV_POP(amount), 0) AS sd, COALESCE(SUM(amount < 15), 0) AS small,
                              COALESCE(SUM(CASE WHEN DAYOFWEEK(transaction_date) IN (1, 7) THEN amount END), 0) AS weekend
                       FROM transactions WHERE $win");
$stmt->execute([':uid' => $uid]);
$s = $stmt->fetch();
$totalTx = (int) $s['n']; $total = (float) $s['total']; $avg = (float) $s['avg']; $sd = (float) $s['sd'];

// Segunda a domingo (DAYOFWEEK: 1 = domingo)
$stmt = $pdo->prepare("SELECT DAYOFWEEK(transaction_date) AS dow, SUM(amount) AS total FROM transactions WHERE $win GROUP BY dow");
$stmt->execute([':uid' => $uid]);
$byDow = array_fill(1, 7, 0.0);
foreach ($stmt->fetchAll() as $r) $byDow[(int) $r['dow']] = round((float) $r['total'], 2);
$dowTotals = [$byDow[2], $byDow[3], $byDow[4], $byDow[5], $byDow[6], $byDow[7], $byDow[1]];

// Tendência justa: últimos 30 dias contra os 30 anteriores
$stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN transaction_date > CURDATE() - INTERVAL 30 DAY THEN amount END), 0) AS recent,
                              COALESCE(SUM(CASE WHEN transaction_date <= CURDATE() - INTERVAL 30 DAY
                                                 AND transaction_date > CURDATE() - INTERVAL 60 DAY THEN amount END), 0) AS previous
                       FROM transactions WHERE user_id = :uid AND transaction_date <= CURDATE()");
$stmt->execute([':uid' => $uid]);
$trend = $stmt->fetch();
$trendPct = $trend['previous'] > 0 ? ($trend['recent'] - $trend['previous']) / $trend['previous'] * 100 : null;

// ── Motor de personalidade ──────────────────────────────────────────────────
// Cada traço tem uma pontuação de 0 a 100 na MESMA escala: 0 = no limiar, 100 = muito marcado.
// Só conta como traço se passar o limiar, e cada um traz a prova (números reais).
$clamp = fn($v) => max(0, min(100, (int) round($v)));
$scale = fn($value, $from, $to) => $clamp(($value - $from) / ($to - $from) * 100);
$pctF  = fn($v) => number_format($v, 0, ',', ' ') . '%';

$traits = [];
if ($totalTx >= PERSONA_MIN_TX && $total > 0) {
    $weekendShare = $s['weekend'] / $total;
    if ($weekendShare > 0.45) {
        $traits['fds'] = [$scale($weekendShare, 0.45, 0.75), $pctF($weekendShare * 100) . ' dos gastos ao sábado e domingo (o normal seria cerca de ' . $pctF(WEEKEND_NORMAL * 100) . ').'];
    }

    $fun = 0; foreach ($catData as $c) if ($c['category'] === 'Entretenimento') $fun = $c['total'] / $total;
    if ($fun > 0.25) {
        $traits['experiencias'] = [$scale($fun, 0.25, 0.55), $pctF($fun * 100) . ' do dinheiro foi para Entretenimento.'];
    }

    $smallShare = $s['small'] / $totalTx;
    if ($totalTx >= 20 && $smallShare > 0.6) {
        $traits['pequenos'] = [$scale($smallShare, 0.6, 0.9), $s['small'] . ' das ' . $totalTx . ' compras foram abaixo de ' . fmt_money(15, 0) . '.'];
    }

    $perMonth = $totalTx / (PERSONA_DAYS / 30);
    if ($perMonth <= 6 && $avg > 60) {
        $traits['minimalista'] = [$scale($avg, 60, 200), 'Cerca de ' . (int) round($perMonth) . ' compras por mês, de ' . fmt_money($avg) . ' em média.'];
    }

    $topShare = $catData ? $catData[0]['total'] / $total : 0;
    if ($topShare > 0.55) {
        $traits['concentrado'] = [$scale($topShare, 0.55, 0.85), $pctF($topShare * 100) . ' dos gastos numa só categoria: ' . $catData[0]['category'] . '.'];
    }

    if (count($catData) >= 6 && $avg > 0 && $sd / $avg > 1) {
        $traits['variado'] = [$scale($sd / $avg, 1, 2), count($catData) . ' categorias diferentes e valores muito variados (de poucos euros a centenas).'];
    }
}
uasort($traits, fn($a, $b) => $b[0] <=> $a[0]);
$key = $totalTx < PERSONA_MIN_TX ? 'novo' : ($traits ? array_key_first($traits) : 'equilibrado');

// Tons da paleta validada (os mesmos do dashboard) e ícones Bootstrap
$defs = [
    'fds' => ['O Impulsivo de Fim de Semana', 7, 'calendar-heart',
        'Os teus fins de semana pesam mais na carteira do que o resto da semana: saídas, refeições ou compras por impulso entre sábado e domingo.',
        ['Define um valor para o fim de semana na sexta-feira. Quando acabar, acabou.', 'Antes de sair, decide quanto vais gastar, não só o que vais fazer.', 'Usa a regra das 24 horas: se te apetecer comprar algo ao sábado, decide no domingo.']],
    'experiencias' => ['O Colecionador de Experiências', 2, 'ticket-perforated',
        'Para ti o dinheiro serve para viver: concertos, saídas, subscrições. Não é mau, mas cada experiência parece justificar-se sozinha e a soma surpreende.',
        ['Cria um orçamento de Entretenimento na Finora. Dentro dele, gasta sem culpa.', 'No fim do mês, marca quais experiências valeram mesmo a pena. Repete essas.', 'Revê as subscrições: quantas usaste este mês?']],
    'pequenos' => ['O Mestre dos Pequenos Gastos', 5, 'cup-hot',
        'Fazes muitas compras pequenas. Cada uma parece inofensiva, mas juntas fazem um valor que se nota no fim do mês.',
        ['Junta as compras: uma ida ao supermercado por semana em vez de várias.', 'Experimenta uma semana por mês sem compras pequenas e vê a diferença.', 'Repara quando acontecem: tédio, pressa, cansaço?']],
    'minimalista' => ['O Minimalista Decidido', 3, 'bullseye',
        'Poucas compras, mas pensadas. Gastas pouco no dia a dia; o teu risco é uma compra grande que não estava planeada.',
        ['Para compras acima de ' . fmt_money(100, 0) . ', espera 72 horas antes de decidir.', 'Cria uma meta na Finora para as compras grandes que já sabes que vêm aí.', 'Revê as últimas compras grandes: quais repetias?']],
    'concentrado' => ['O Especialista de Uma Categoria', 8, 'pie-chart',
        'Mais de metade do teu dinheiro vai para uma única categoria. Pode ser normal (a renda, por exemplo) ou um ponto cego.',
        ['Pergunta-te se essa categoria reflete as tuas prioridades ou só um hábito.', 'Põe um orçamento nessa categoria durante um mês e vê como te sentes.', 'Procura alternativas mais baratas só para essa categoria: é onde o impacto é maior.']],
    'variado' => ['O Explorador Imprevisível', 4, 'shuffle',
        'Os teus gastos mudam muito: muitas categorias e valores muito diferentes. Pode ser uma vida cheia ou falta de plano.',
        ['Começa com três grupos: necessidades, lazer e o resto.', 'Uma vez por mês, revê os gastos e marca o que foi planeado e o que aconteceu.', 'Os orçamentos da Finora ajudam a pôr limites às categorias que mais variam.']],
    'equilibrado' => ['O Equilibrado Consciente', 1, 'yin-yang',
        'Os teus gastos não mostram nenhum padrão extremo: estão bem distribuídos pelas categorias e pela semana.',
        ['Define uma meta concreta: mesmo quem é equilibrado ganha com uma direção.', 'Experimenta um orçamento mensal total e vê quanto sobra.', 'Volta aqui no próximo mês: os hábitos mudam e a análise também.']],
    'novo' => ['Ainda a conhecer-te', 6, 'hourglass-split',
        'Com poucas transações ainda não dá para ver um padrão a sério. Continua a registar e a análise aparece sozinha.',
        ['Regista também as compras pequenas: são as que mais mostram hábitos.', 'Usa "Ler fatura" para registar mais depressa.', 'Associa as despesas a cartões para veres também como os usas.']],
];
[$pName, $pSlot, $pIcon, $pDesc, $pTips] = $defs[$key];
$tone = "var(--cat-$pSlot)";
?>
<!doctype html>
<html lang="<?= html_lang() ?>" data-theme="<?= $currentTheme ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Personalidade - Finora</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/theme.css">
  <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="../assets/apple-touch-icon.png">
  <link rel="stylesheet" href="../css/sidebar.css?v=7">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    .main-content .pg-wrap.ps-wrap { max-width: 1000px; }

    /* Cartão principal na cor da personalidade */
    .ps-hero { position: relative; overflow: hidden; display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); gap: 28px; padding: 28px;
               border-radius: 22px; border: 1px solid color-mix(in srgb, var(--tone) 30%, var(--fn-line)); margin-bottom: 18px;
               background: linear-gradient(135deg, color-mix(in srgb, var(--tone) 16%, var(--fn-surface)), var(--fn-surface) 75%); }
    .ps-hero::after { content: ""; position: absolute; right: -60px; top: -60px; width: 220px; height: 220px; border-radius: 50%;
                      background: color-mix(in srgb, var(--tone) 12%, transparent); pointer-events: none; }
    .ps-badge { width: 64px; height: 64px; border-radius: 18px; display: grid; place-items: center; font-size: 30px; color: #fff; background: var(--tone);
                box-shadow: 0 8px 20px color-mix(in srgb, var(--tone) 35%, transparent); margin-bottom: 16px; }
    .ps-kicker { margin: 0 0 4px; font-size: 14px; font-weight: 600; color: var(--fn-muted); }
    .ps-name { margin: 0 0 10px; font-family: var(--fn-font-brand); font-size: 30px; font-weight: 700; letter-spacing: -0.5px; color: var(--fn-ink); line-height: 1.15; }
    .ps-desc { margin: 0; font-size: 16px; line-height: 1.55; color: var(--fn-text); max-width: 52ch; }
    .ps-why { position: relative; z-index: 1; background: color-mix(in srgb, var(--fn-surface) 80%, transparent); border: 1px solid var(--fn-line); border-radius: 16px; padding: 18px; align-self: start; }
    .ps-why h2 { margin: 0 0 12px; font-size: 15px; font-weight: 700; color: var(--fn-text); }
    .ps-trait + .ps-trait { margin-top: 14px; }
    .ps-trait-head { display: flex; justify-content: space-between; gap: 10px; font-size: 14px; font-weight: 600; color: var(--fn-text); }
    .ps-trait-head span:last-child { color: var(--fn-muted); font-weight: 500; }
    .ps-meter { height: 8px; border-radius: 99px; background: var(--fn-line); margin: 6px 0; overflow: hidden; }
    .ps-meter i { display: block; height: 100%; border-radius: 99px; background: var(--t); min-width: 8px; }
    .ps-trait p { margin: 0; font-size: 13px; color: var(--fn-muted); line-height: 1.4; }
    .ps-empty-why { margin: 0; font-size: 14px; color: var(--fn-muted); }
    .ps-progress { height: 10px; border-radius: 99px; background: var(--fn-line); overflow: hidden; margin: 10px 0 6px; }
    .ps-progress i { display: block; height: 100%; background: var(--tone); border-radius: 99px; }

    /* Números */
    .ps-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
    .ps-stat { background: var(--fn-surface); border: 1px solid var(--fn-line); border-radius: 16px; padding: 16px; display: flex; gap: 12px; align-items: flex-start; }
    .ps-stat b { display: block; font-family: var(--fn-font-brand); font-size: 22px; font-weight: 700; color: var(--fn-text); line-height: 1.1; }
    .ps-stat span { display: block; font-size: 13px; color: var(--fn-muted); margin-top: 2px; }
    .ps-up { color: #b42318 !important; } .ps-down { color: #067647 !important; }
    [data-theme="dark"] .ps-up { color: #fda29b !important; } [data-theme="dark"] .ps-down { color: #75e0a7 !important; }

    .ps-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 18px; }
    .ps-grid .pg-card { margin-bottom: 0; }
    .ps-body { padding: 18px 20px; }
    .ps-chart { position: relative; height: 230px; }

    .ps-cat + .ps-cat { margin-top: 14px; }
    .ps-cat-head { display: flex; align-items: center; gap: 10px; font-size: 14px; }
    .ps-cat-head .dot { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
    .ps-cat-head .name { flex: 1; font-weight: 600; color: var(--fn-text); }
    .ps-cat-head .val { color: var(--fn-muted); font-variant-numeric: tabular-nums; }
    .ps-cat-bar { height: 8px; border-radius: 99px; background: var(--fn-line); margin-top: 6px; overflow: hidden; }
    .ps-cat-bar i { display: block; height: 100%; border-radius: 99px; }

    .ps-tips { list-style: none; margin: 0; padding: 0; counter-reset: tip; }
    .ps-tips li { counter-increment: tip; display: flex; gap: 14px; align-items: flex-start; padding: 14px 20px; font-size: 15px; line-height: 1.5; color: var(--fn-text); }
    .ps-tips li + li { border-top: 1px solid var(--fn-line); }
    .ps-tips li::before { content: counter(tip); width: 28px; height: 28px; border-radius: 9px; flex-shrink: 0; display: grid; place-items: center;
                          font-weight: 700; font-size: 14px; color: var(--tone); background: color-mix(in srgb, var(--tone) 15%, transparent); }
    .ps-note { margin: 16px 2px 0; font-size: 13px; color: var(--fn-muted); text-align: center; }

    @media (max-width: 860px) {
      .ps-hero, .ps-grid { grid-template-columns: minmax(0, 1fr); }
      .ps-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 520px) {
      .ps-hero { padding: 20px; }
      .ps-name { font-size: 25px; }
      .ps-stat { flex-direction: column; gap: 8px; }
    }
  </style>
  <link rel="stylesheet" href="../css/finora.css?v=9">
</head>
<body>
<div class="app-layout">
<?php
$activePage    = 'persona';
$sidebarPrefix = '../';
include __DIR__ . '/../components/sidebar.php';
?>
<main class="main-content">
<div class="page-content">
<div class="pg-wrap ps-wrap" style="--tone:<?= $tone ?>">

  <div class="pg-head">
    <h1>Personalidade</h1>
    <p>O teu estilo de gastar, a partir dos últimos <?= PERSONA_DAYS ?> dias.</p>
  </div>

  <section class="ps-hero" aria-labelledby="psName">
    <div>
      <div class="ps-badge" aria-hidden="true"><i class="bi bi-<?= $pIcon ?>"></i></div>
      <p class="ps-kicker"><?= $key === 'novo' ? 'Personalidade' : 'A tua personalidade' ?></p>
      <h2 class="ps-name" id="psName"><?= htmlspecialchars($pName) ?></h2>
      <p class="ps-desc"><?= htmlspecialchars($pDesc) ?></p>
    </div>
    <div class="ps-why">
      <?php if ($key === 'novo'): ?>
        <h2>Faltam <?= PERSONA_MIN_TX - $totalTx ?> transações</h2>
        <div class="ps-progress" role="progressbar" aria-valuemin="0" aria-valuemax="<?= PERSONA_MIN_TX ?>" aria-valuenow="<?= $totalTx ?>" aria-label="Transações registadas">
          <i style="width:<?= (int) round($totalTx / PERSONA_MIN_TX * 100) ?>%"></i>
        </div>
        <p class="ps-empty-why"><?= $totalTx ?> de <?= PERSONA_MIN_TX ?> nos últimos <?= PERSONA_DAYS ?> dias.</p>
      <?php elseif (!$traits): ?>
        <h2>Porquê</h2>
        <p class="ps-empty-why">Nenhum dos traços passou o limite: fim de semana, experiências, pequenas compras, poucas compras grandes, uma só categoria ou gastos muito variados.</p>
      <?php else: ?>
        <h2>Porquê</h2>
        <?php foreach ($traits as $tk => [$score, $proof]): ?>
          <div class="ps-trait" style="--t:var(--cat-<?= $defs[$tk][1] ?>)">
            <div class="ps-trait-head"><span><?= htmlspecialchars($defs[$tk][0]) ?></span><span><?= $score ?>/100</span></div>
            <div class="ps-meter" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $score ?>" aria-label="<?= htmlspecialchars($defs[$tk][0]) ?>"><i style="width:<?= $score ?>%"></i></div>
            <p><?= htmlspecialchars($proof) ?></p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($totalTx > 0): ?>
  <div class="ps-stats">
    <div class="ps-stat"><span class="pg-ico sm" style="--tone:var(--cat-1)" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
      <div><b><?= fmt_money($total / 3, 0) ?></b><span>por mês, em média</span></div></div>
    <div class="ps-stat"><span class="pg-ico sm" style="--tone:var(--cat-7)" aria-hidden="true"><i class="bi bi-receipt"></i></span>
      <div><b><?= $totalTx ?></b><span>transações em <?= PERSONA_DAYS ?> dias</span></div></div>
    <div class="ps-stat"><span class="pg-ico sm" style="--tone:var(--cat-4)" aria-hidden="true"><i class="bi bi-calculator"></i></span>
      <div><b><?= fmt_money($avg) ?></b><span>por transação</span></div></div>
    <div class="ps-stat"><span class="pg-ico sm" style="--tone:var(--cat-3)" aria-hidden="true"><i class="bi bi-graph-up-arrow"></i></span>
      <div>
        <?php if ($trendPct === null): ?>
          <b>—</b><span>sem 30 dias anteriores para comparar</span>
        <?php else: ?>
          <b class="<?= $trendPct > 5 ? 'ps-up' : ($trendPct < -5 ? 'ps-down' : '') ?>"><?= ($trendPct >= 0 ? '+' : '−') . number_format(abs($trendPct), 0, ',', ' ') ?>%</b>
          <span>últimos 30 dias vs. os 30 antes</span>
        <?php endif; ?>
      </div></div>
  </div>

  <div class="ps-grid">
    <section class="pg-card" style="--tone:var(--cat-7)" aria-labelledby="dowTitle">
      <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-calendar-week"></i></span>
        <div><h2 id="dowTitle">Dias da semana</h2><p>Quanto gastaste em cada dia; o fim de semana está realçado.</p></div></header>
      <div class="ps-body"><div class="ps-chart"><canvas id="dowChart" role="img" aria-label="Gastos por dia da semana"></canvas></div></div>
    </section>

    <section class="pg-card" style="--tone:var(--cat-2)" aria-labelledby="catTitle">
      <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-grid"></i></span>
        <div><h2 id="catTitle">Categorias</h2><p>Para onde foi o dinheiro.</p></div></header>
      <div class="ps-body">
        <?php foreach (array_slice($catData, 0, 6) as $c): $p = $total > 0 ? $c['total'] / $total * 100 : 0; ?>
          <div class="ps-cat">
            <div class="ps-cat-head">
              <span class="dot" style="background:<?= category_color($c['category']) ?>"></span>
              <span class="name"><?= htmlspecialchars($c['category']) ?></span>
              <span class="val"><?= fmt_money($c['total']) ?> · <?= number_format($p, 0, ',', '') ?>%</span>
            </div>
            <div class="ps-cat-bar"><i style="width:<?= round($p, 1) ?>%;background:<?= category_color($c['category']) ?>"></i></div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
  <?php endif; ?>

  <section class="pg-card" style="margin-top:18px" aria-labelledby="tipsTitle">
    <header class="pg-card-head"><span class="pg-ico" aria-hidden="true"><i class="bi bi-lightbulb"></i></span>
      <div><h2 id="tipsTitle">Para ti</h2><p>Três ideias para o teu estilo.</p></div></header>
    <ol class="ps-tips">
      <?php foreach ($pTips as $tip): ?><li><?= htmlspecialchars($tip) ?></li><?php endforeach; ?>
    </ol>
  </section>

  <p class="ps-note"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Atualiza-se sozinha com cada transação. São ideias para refletir, não aconselhamento financeiro.</p>

</div>
</div>
</main>
</div>

<script>
// Cores lidas das variáveis CSS (mudam com o tema claro/escuro)
const css   = getComputedStyle(document.documentElement);
const v     = n => css.getPropertyValue(n).trim();
const toneV = v('--cat-<?= $pSlot ?>');
const data  = <?= json_encode($dowTotals) ?>;
const ctx   = document.getElementById('dowChart');
if (ctx) {
  const eur = <?= money_js() ?>;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'],
      datasets: [{ data, borderRadius: 6, borderSkipped: false, maxBarThickness: 36,
                   backgroundColor: data.map((_, i) => i >= 5 ? toneV : v('--fn-line')) }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => eur(c.parsed.y) } } },
      scales: {
        x: { grid: { display: false }, ticks: { color: v('--fn-muted') } },
        y: { grid: { color: v('--fn-line') }, border: { display: false }, ticks: { color: v('--fn-muted'), callback: n => <?= money_js(0) ?>(n) } }
      }
    }
  });
}
</script>
</body>
</html>
