<?php
// site/helpers/format.php
// Formatação de valores e datas em português de Portugal.

// 1240.5 → "1 240,50 €" (espaço fino não separável entre milhares, como manda a norma portuguesa)
function fmt_eur($value, int $decimals = 2): string {
    return number_format((float) $value, $decimals, ',', "\u{202F}") . "\u{00A0}€";
}

// Nome do mês em português (1–12)
function month_name_pt(int $month): string {
    static $names = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho',
                     'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    return $names[($month - 1) % 12];
}
