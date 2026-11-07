<?php
// site/helpers/format.php
// Formatação de valores e datas em português de Portugal.

// Moedas que a conta pode usar: código ISO 4217 → [símbolo, nome, casas decimais].
// Os símbolos são os que o Intl.NumberFormat('pt-PT') usa no JavaScript, para o PHP e o JS mostrarem o mesmo.
// Cada conta tem uma só moeda: os valores não são convertidos, só mudam de símbolo.
const CURRENCIES = [
    'EUR' => ['€',   'Euro',             2],
    'USD' => ['US$', 'Dólar americano',  2],
    'GBP' => ['£',   'Libra esterlina',  2],
    'BRL' => ['R$',  'Real brasileiro',  2],
    'CHF' => ['CHF', 'Franco suíço',     2],
    'CAD' => ['CA$', 'Dólar canadiano',  2],
    'AOA' => ['Kz',  'Kwanza angolano',  2],
    'JPY' => ['JP¥', 'Iene japonês',     0],
];

// Como o tema: só devolve códigos da lista (o valor vem da base de dados e vai parar ao HTML e ao JS)
function valid_currency(?string $code): string {
    return isset(CURRENCIES[$code]) ? $code : 'EUR';
}

// Moeda da conta com sessão iniciada. O auth.php chama set_user_currency() a cada pedido;
// sem sessão (landing, cron) fica EUR, e o cron passa a moeda de cada utilizador explicitamente.
function set_user_currency(?string $code): void {
    $GLOBALS['__finora_currency'] = valid_currency($code);
}
function user_currency(): string {
    return $GLOBALS['__finora_currency'] ?? 'EUR';
}

function currency_symbol(?string $code = null): string {
    return CURRENCIES[valid_currency($code ?? user_currency())][0];
}

// 1240.5 → "1 240,50 €" / "1 240,50 US$" (espaço fino não separável entre milhares, como manda a norma portuguesa)
function fmt_money($value, int $decimals = 2, ?string $code = null): string {
    [$symbol, , $max] = CURRENCIES[valid_currency($code ?? user_currency())];
    return number_format((float) $value, min($decimals, $max), ',', "\u{202F}") . "\u{00A0}" . $symbol;
}

// A mesma formatação para o JavaScript das páginas: devolve uma arrow function, ex.
//   const money = (money_js() impresso pela página); money(12.5) → "12,50 €"
// Não usa style:'currency' do Intl porque o símbolo tem de ser o da lista acima (o Intl escreve "AOA" em vez de "Kz").
function money_js(int $decimals = 2): string {
    [$symbol, , $max] = CURRENCIES[valid_currency(user_currency())];
    $d = min($decimals, $max);
    return "(n => Number(n).toLocaleString('pt-PT', { minimumFractionDigits: $d, maximumFractionDigits: $d }) + '\\u00A0' + "
         . json_encode($symbol, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ')';
}

// Sempre em euros: os investimentos guardam os preços já convertidos para EUR (ver helpers/market.php)
function fmt_eur($value, int $decimals = 2): string {
    return fmt_money($value, $decimals, 'EUR');
}

// Nome do mês em português (1–12)
function month_name_pt(int $month): string {
    static $names = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho',
                     'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    return $names[($month - 1) % 12];
}
