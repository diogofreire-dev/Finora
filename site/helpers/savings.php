<?php
// site/helpers/savings.php
// Poupanças (tabela savings): valor positivo = pôr de lado, negativo = tirar.
// Também desenha o mealheiro: uma ilustração em SVG (estilo dos bonecos da página inicial)
// com moedas lá dentro até à percentagem exata. Cores em css/piggy.css (tema claro e escuro).

// Altura (no viewBox 0 0 240 200) do monte de moedas a 0% e a 100% da barriga
const PIGGY_Y0    = 166;
const PIGGY_Y100  = 50;
const SAVINGS_MAX = 1000000;

// Metas de referência para o mealheiro grande: enche até ao próximo marco
const SAVINGS_MILESTONES = [100, 250, 500, 1000, 2500, 5000, 10000, 25000, 50000, 100000, 250000, 500000, 1000000];

const SAVINGS_PAGE = 30;   // movimentos por página ("Ver mais" junta outros 30)

// Filtros da lista de movimentos, validados: só valores conhecidos passam
function savings_filters(array $src, array $goalIds): array {
    $p = in_array($src['p'] ?? '', ['mes', '3m', '12m', 'tudo'], true) ? $src['p'] : '3m';
    $g = (string) ($src['g'] ?? 'todas');
    $g = $g === 'sem' ? 'sem' : (in_array((int) $g, $goalIds, true) ? (string) (int) $g : 'todas');
    $t = in_array($src['t'] ?? '', ['in', 'out'], true) ? $src['t'] : 'todos';
    $n = max(1, min(50, (int) ($src['n'] ?? 1)));
    return ['p' => $p, 'g' => $g, 't' => $t, 'n' => $n];
}

function savings_next_milestone(float $total): float {
    foreach (SAVINGS_MILESTONES as $m) if ($total < $m) return $m;
    return end(SAVINGS_MILESTONES);
}

// Total guardado por meta (id => valor) e no fundo geral (chave 0)
function savings_by_goal(PDO $pdo, int $uid): array {
    $stmt = $pdo->prepare('SELECT COALESCE(goal_id, 0) AS g, SUM(amount) AS total FROM savings WHERE user_id = :u GROUP BY g');
    $stmt->execute([':u' => $uid]);
    $out = [];
    foreach ($stmt as $r) $out[(int) $r['g']] = (float) $r['total'];
    return $out;
}

// Linha de "onde devias estar hoje": a meta repartida igualmente entre a criação e o prazo
function goal_expected_today(array $goal, string $today): float {
    $start = strtotime(substr((string) $goal['created_at'], 0, 10));
    $end   = strtotime($goal['target_date']);
    $now   = strtotime($today);
    if ($end <= $start) return (float) $goal['target_amount'];
    $ratio = max(0, min(1, ($now - $start) / ($end - $start)));
    return round((float) $goal['target_amount'] * $ratio, 2);
}

// Mealheiro: porquinho com moedas até $pct % da barriga.
// $markPct (opcional) desenha a linha de "onde devias estar".
function piggy_html(float $pct, string $label, string $size = 'lg', ?float $markPct = null, string $extraClass = ''): string {
    static $n = 0;
    $id    = 'pg' . (++$n);                       // ids únicos: as Metas têm vários mealheiros na página
    $pct   = max(0, min(100, $pct));
    // Qualquer valor acima de zero mostra pelo menos um fundo de moedas, para se ver que já lá há dinheiro
    $shown = $pct <= 0 ? 0 : max(6, $pct);
    $y     = round(PIGGY_Y0 - $shown / 100 * (PIGGY_Y0 - PIGGY_Y100), 1);
    $coins = '';
    if ($shown > 0) {
        // Fila de moedas por cima do monte, para o topo não ser uma linha reta
        for ($x = 22, $k = 0; $x <= 210; $x += 13, $k++) {
            $coins .= '<ellipse class="pg-coin' . ($k % 3 === 1 ? ' pg-coin-alt' : '') . '" cx="' . $x . '" cy="' . ($y + ($k % 2 ? 2 : -1)) . '" rx="7.5" ry="4"/>';
        }
        $coins = '<g class="pg-fill">'
               . '<rect x="20" y="' . $y . '" width="190" height="' . round(178 - $y, 1) . '" fill="url(#' . $id . 'p)"/>' . $coins . '</g>';
    }
    $mark = '';
    if ($markPct !== null && $markPct > 0 && $markPct < 100) {
        $my = round(PIGGY_Y0 - $markPct / 100 * (PIGGY_Y0 - PIGGY_Y100), 1);
        $mark = '<line class="pg-mark" x1="24" x2="200" y1="' . $my . '" y2="' . $my . '"/><circle class="pg-mark-dot" cx="204" cy="' . $my . '" r="4"/>';
    }
    return '<div class="piggy piggy-' . $size . ($pct >= 100 ? ' is-full' : '') . ($extraClass !== '' ? ' ' . $extraClass : '') . '" role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES) . '">'
         . '<svg viewBox="0 0 240 200" aria-hidden="true" focusable="false">'
         . '<defs>'
         .   '<clipPath id="' . $id . 'c"><ellipse cx="112" cy="110" rx="82" ry="62"/></clipPath>'
         // Monte de moedas: fundo dourado com moedas desencontradas por cima
         .   '<pattern id="' . $id . 'p" width="32" height="16" patternUnits="userSpaceOnUse">'
         .     '<rect class="pg-pile" width="32" height="16"/>'
         .     '<ellipse class="pg-coin" cx="8" cy="5" rx="8.5" ry="4.2"/><ellipse class="pg-coin pg-coin-alt" cx="24" cy="13" rx="8.5" ry="4.2"/>'
         .   '</pattern>'
         . '</defs>'
         // Patas de trás (mais escuras), cauda e orelha de trás
         . '<rect class="pg-part pg-back" x="58" y="152" width="22" height="30" rx="8"/><rect class="pg-part pg-back" x="140" y="152" width="22" height="30" rx="8"/>'
         . '<path class="pg-tail" d="M31 104 C18 100 16 86 26 84 C35 82 34 96 24 96 C14 96 12 88 14 82"/>'
         . '<path class="pg-part pg-back" d="M146 56 L154 30 L172 52Z"/>'
         // Corpo: vidro claro com as moedas lá dentro
         . '<ellipse class="pg-glass" cx="112" cy="110" rx="82" ry="62"/>'
         . '<g clip-path="url(#' . $id . 'c)">' . $coins . $mark . '</g>'
         . '<ellipse class="pg-line" cx="112" cy="110" rx="82" ry="62"/>'
         . '<path class="pg-shine" d="M52 84 C60 66 76 56 96 52"/>'
         // Ranhura, orelha da frente, focinho, olho, patas da frente
         . '<rect class="pg-slot" x="94" y="45" width="34" height="7" rx="3.5"/>'
         . '<path class="pg-part" d="M160 60 L172 34 L186 60Z"/>'
         . '<rect class="pg-part" x="186" y="94" width="30" height="36" rx="13"/>'
         . '<ellipse class="pg-nose" cx="197" cy="106" rx="2.6" ry="4"/><ellipse class="pg-nose" cx="206" cy="106" rx="2.6" ry="4"/>'
         . '<g class="pg-eye"><circle cx="168" cy="88" r="5"/></g>'
         . '<path class="pg-cheek" d="M160 104 Q166 108 172 104"/>'
         . '<rect class="pg-part" x="74" y="156" width="24" height="30" rx="9"/><rect class="pg-part" x="128" y="156" width="24" height="30" rx="9"/>'
         . '</svg></div>';
}
