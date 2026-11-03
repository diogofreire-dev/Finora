<?php
// site/helpers/savings.php
// Poupanças (tabela savings): valor positivo = pôr de lado, negativo = tirar.
// Também desenha o mealheiro 3D (design/finora-piggy.blend → assets/3d/piggy-*.webp).

// Onde o corpo do porquinho começa e acaba na imagem (calculado no Blender), em % da altura
const PIGGY_TOP    = 11.9;
const PIGGY_BOTTOM = 91.9;
const SAVINGS_MAX  = 1000000;

// Metas de referência para o mealheiro grande: enche até ao próximo marco
const SAVINGS_MILESTONES = [100, 250, 500, 1000, 2500, 5000, 10000, 25000, 50000, 100000, 250000, 500000, 1000000];

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

// Mealheiro: a imagem dourada aparece de baixo para cima até $pct (0–100).
// $markPct (opcional) desenha a linha de "onde devias estar".
function piggy_html(float $pct, string $label, string $size = 'lg', ?float $markPct = null): string {
    $pct  = max(0, min(100, $pct));
    $cut  = $pct >= 100 ? 0 : PIGGY_BOTTOM - $pct / 100 * (PIGGY_BOTTOM - PIGGY_TOP);
    $html = '<div class="piggy piggy-' . $size . ($pct >= 100 ? ' is-full' : '') . '" role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES) . '">'
          . '<img class="piggy-empty" src="../assets/3d/piggy-empty.webp" alt="" width="560" height="610">'
          . '<img class="piggy-full" src="../assets/3d/piggy-full.webp" alt="" width="560" height="610" style="clip-path: inset(' . round($cut, 2) . '% 0 0 0); --cut: ' . round($cut, 2) . '%">';
    if ($markPct !== null && $markPct > 0 && $markPct < 100) {
        $y = PIGGY_BOTTOM - $markPct / 100 * (PIGGY_BOTTOM - PIGGY_TOP);
        $html .= '<span class="piggy-mark" style="top: ' . round($y, 2) . '%"></span>';
    }
    return $html . '</div>';
}
