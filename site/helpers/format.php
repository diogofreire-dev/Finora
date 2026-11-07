<?php
// site/helpers/format.php
// Formatação de valores e datas na língua da conta (português de Portugal ou inglês, ver i18n.php).
require_once __DIR__ . '/i18n.php';

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
];
// Sem moedas de 0 casas decimais (ex.: iene): os formulários aceitam cêntimos e a BD guarda DECIMAL(10,2),
// por isso 1,50 ¥ apareceria arredondado e os totais não batiam com os valores guardados.

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

// pt: 1240.5 → "1 240,50 €" (espaço fino não separável entre milhares, como manda a norma portuguesa)
// en: 1240.5 → "€1,240.50", "CHF 1,240.50" (símbolo à frente; com espaço se for feito de letras)
function fmt_money($value, int $decimals = 2, ?string $code = null): string {
    [$symbol, , $max] = CURRENCIES[valid_currency($code ?? user_currency())];
    $d = min($decimals, $max);
    if (current_lang() === 'en') {
        $v = (float) $value;
        $n = number_format(abs($v), $d, '.', ',');
        return ($v < 0 ? '-' : '') . $symbol . (ctype_alpha($symbol) ? "\u{00A0}" : '') . $n;
    }
    return number_format((float) $value, $d, ',', "\u{202F}") . "\u{00A0}" . $symbol;
}

// Números sem moeda (percentagens, contagens) com o separador decimal da língua
function fmt_num($value, int $decimals = 0): string {
    return current_lang() === 'en'
        ? number_format((float) $value, $decimals, '.', ',')
        : number_format((float) $value, $decimals, ',', "\u{202F}");
}

// A mesma formatação para o JavaScript das páginas: devolve uma arrow function, ex.
//   const money = (money_js() impresso pela página); money(12.5) → "12,50 €"
// Não usa style:'currency' do Intl porque o símbolo tem de ser o da lista acima (o Intl escreve "AOA" em vez de "Kz").
function money_js(int $decimals = 2): string {
    [$symbol, , $max] = CURRENCIES[valid_currency(user_currency())];
    $d   = min($decimals, $max);
    $sym = json_encode($symbol, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $num = "Number(n).toLocaleString('" . js_locale() . "', { minimumFractionDigits: $d, maximumFractionDigits: $d })";
    if (current_lang() === 'en') {
        $gap = ctype_alpha($symbol) ? " + '\\u00A0'" : '';
        return "(n => (n < 0 ? '-' : '') + $sym$gap + $num.replace('-', ''))";
    }
    return "(n => $num + '\\u00A0' + $sym)";
}

// Sempre em euros: os investimentos guardam os preços já convertidos para EUR (ver helpers/market.php)
function fmt_eur($value, int $decimals = 2): string {
    return fmt_money($value, $decimals, 'EUR');
}

// Nome do mês (1–12) na língua da conta. Em português é minúsculo ("em março"); em inglês, maiúsculo.
function month_name(int $month): string {
    static $names = [
        'pt-PT' => ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho',
                    'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'],
        'en'    => ['January', 'February', 'March', 'April', 'May', 'June', 'July',
                    'August', 'September', 'October', 'November', 'December'],
    ];
    return $names[current_lang()][($month - 1) % 12];
}
// Nome antigo, ainda usado nas páginas que não foram traduzidas
function month_name_pt(int $month): string {
    return month_name($month);
}
