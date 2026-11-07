<?php
// tests/i18n_missing.php — lista as frases usadas com t()/te()/tj() que ainda não têm tradução.
//   php tests/i18n_missing.php [língua=en] [ficheiros…]   (sem ficheiros: todo o site/)
// Lê o código com o tokenizer do PHP: apanha todos os textos fixos dentro da chamada,
// incluindo os dois lados de um ternário — t($n === 1 ? '1 cartão' : '{n} cartões').
$lang  = $argv[1] ?? 'en';
$files = array_slice($argv, 2);
if (!$files) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../site'));
    foreach ($it as $f) if ($f->getExtension() === 'php' && !str_contains($f->getPathname(), '/lang/')) $files[] = $f->getPathname();
    $files[] = __DIR__ . '/../config/EmailService.php';
    $files[] = __DIR__ . '/../cron/send_reminders.php';
}
$table = require __DIR__ . "/../site/lang/$lang.php";
$missing = [];
foreach ($files as $file) {
    $tokens = token_get_all(file_get_contents($file));
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        $tk = $tokens[$i];
        if (!is_array($tk) || $tk[0] !== T_STRING || !in_array($tk[1], ['t', 'te', 'tj'], true)) continue;
        $prev = $tokens[$i - 1] ?? null;   // ignora métodos ($x->t) e definições (function t)
        if (is_array($prev) && in_array($prev[0], [T_OBJECT_OPERATOR, T_FUNCTION, T_DOUBLE_COLON], true)) continue;
        $j = $i + 1;
        while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        if (($tokens[$j] ?? null) !== '(') continue;
        // só o 1.º argumento: até à vírgula ou ao ")" do mesmo nível
        $depth = 0;
        for ($k = $j + 1; $k < $n; $k++) {
            $t = $tokens[$k];
            if ($t === '(' || $t === '[') $depth++;
            elseif ($t === ')' || $t === ']') { if ($depth-- === 0) break; }
            elseif ($t === ',' && $depth === 0) break;
            elseif (is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING && $t[1][0] === "'") {
                // $x['category'] e ['n' => …]: chaves de arrays, não frases
                $before = $k - 1; while (is_array($tokens[$before]) && $tokens[$before][0] === T_WHITESPACE) $before--;
                $after  = $k + 1; while (is_array($tokens[$after] ?? null) && $tokens[$after][0] === T_WHITESPACE) $after++;
                if ($tokens[$before] === '[' && ($tokens[$after] === ']' || (is_array($tokens[$after]) && $tokens[$after][0] === T_DOUBLE_ARROW))) continue;
                if (is_array($tokens[$after] ?? null) && $tokens[$after][0] === T_DOUBLE_ARROW) continue;
                $s = str_replace(["\\'", '\\\\'], ["'", '\\'], substr($t[1], 1, -1));
                if (preg_match('/[A-Za-zÀ-ÿ]{2,}/u', $s) && !isset($table[$s])) $missing[$s][] = basename($file) . ':' . $t[2];
            }
        }
    }
}
foreach ($missing as $s => $where) echo $s, '   ← ', implode(', ', array_unique($where)), "\n";
fwrite(STDERR, count($missing) . " frases sem tradução ($lang)\n");

exit($missing ? 1 : 0);
