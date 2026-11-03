<?php
// site/helpers/savings.php
// Poupanças (tabela savings): valor positivo = pôr de lado, negativo = tirar.
// Também desenha o mealheiro 3D de vidro (design/finora-piggy-glass.blend → assets/3d/piggy-glass-NNN.webp):
// 11 renders no Cycles com as moedas e notas até 0%, 10%, … 100% da barriga.

// Altura (em % da imagem) do monte de moedas a 0% e a 100% — calculada no Blender (world_to_camera_view)
const PIGGY_Y0    = 64.6;
const PIGGY_Y100  = 20.6;
const SAVINGS_MAX = 1000000;

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

// Mealheiro: escolhe o render do nível mais próximo (0, 10, …, 100).
// Qualquer valor acima de zero mostra pelo menos o nível 10, para se ver que já lá há dinheiro.
// $markPct (opcional) desenha a linha de "onde devias estar".
function piggy_html(float $pct, string $label, string $size = 'lg', ?float $markPct = null, string $extraClass = ''): string {
    $pct   = max(0, min(100, $pct));
    $level = $pct <= 0 ? 0 : max(10, (int) (round($pct / 10) * 10));
    $html  = '<div class="piggy piggy-' . $size . ($level >= 100 ? ' is-full' : '') . ($extraClass !== '' ? ' ' . $extraClass : '') . '" role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES) . '">'
           . '<img src="../assets/3d/piggy-glass-' . sprintf('%03d', $level) . '.webp" alt="" width="620" height="580" decoding="async">';
    if ($markPct !== null && $markPct > 0 && $markPct < 100) {
        $y = PIGGY_Y0 - $markPct / 100 * (PIGGY_Y0 - PIGGY_Y100);
        $html .= '<span class="piggy-mark" style="top: ' . round($y, 2) . '%"></span>';
    }
    return $html . '</div>';
}
