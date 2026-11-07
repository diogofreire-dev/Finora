<?php
// tests/i18n_check.php — verifica os ficheiros de língua (site/lang/*.php). Corre no CI e à mão:
//   php tests/i18n_check.php
// 1. Cada tradução tem os mesmos {marcadores} que a frase portuguesa (senão o t() deixava "{n}" no ecrã).
// 2. Nenhum helper tem o fecho do PHP ("?" seguido de ">") num comentário: num comentário de linha, o PHP sai ali mesmo
//    e o resto do ficheiro aparece como texto em todas as páginas (aconteceu duas vezes ao escrever o i18n).
$fail = 0;

foreach (glob(__DIR__ . '/../site/lang/*.php') as $file) {
    $table = require $file;
    if (!is_array($table)) { echo "✗ " . basename($file) . " não devolve um array\n"; $fail = 1; continue; }
    foreach ($table as $pt => $tr) {
        preg_match_all('/\{\w+\}/', $pt, $a);
        preg_match_all('/\{\w+\}/', $tr, $b);
        sort($a[0]); sort($b[0]);
        if ($a[0] !== $b[0]) { echo "✗ " . basename($file) . ": marcadores diferentes em \"$pt\"\n"; $fail = 1; }
        if (trim($tr) === '') { echo "✗ " . basename($file) . ": tradução vazia em \"$pt\"\n"; $fail = 1; }
    }
    echo "✓ " . basename($file) . ': ' . count($table) . " frases\n";
}

foreach (glob(__DIR__ . '/../site/helpers/*.php') as $file) {
    foreach (file($file) as $n => $line) {
        if (preg_match('#^\s*//.*\?>#', $line)) { echo "✗ " . basename($file) . ':' . ($n + 1) . ": fecho do PHP num comentário\n"; $fail = 1; }
    }
}

exit($fail);
