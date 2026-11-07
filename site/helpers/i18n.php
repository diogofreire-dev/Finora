<?php
// site/helpers/i18n.php
// Línguas da Finora. O código continua escrito em português: t('Entrar') devolve o texto na língua escolhida.
// A frase portuguesa é a própria chave (como no gettext), por isso uma frase sem tradução aparece em português
// em vez de partir a página. As traduções estão em site/lang/<código>.php.

// Código → [nome na própria língua, valor do atributo lang do HTML, locale do Intl no JavaScript]
const LANGS = [
    'pt-PT' => ['Português', 'pt-PT', 'pt-PT'],
    'en'    => ['English',   'en',    'en-GB'],
];
const LANG_COOKIE = 'finora_lang';

function valid_lang(?string $code): string {
    return isset(LANGS[$code]) ? $code : 'pt-PT';
}

// Sem conta: ?lang= (o seletor da landing) → cookie → língua do browser. Com conta, o auth.php
// chama set_lang() com a língua guardada nas Definições, que ganha a tudo.
function detect_lang(): string {
    $asked = $_GET['lang'] ?? null;
    if (is_string($asked) && isset(LANGS[$asked])) {
        remember_lang($asked);
        return $asked;
    }
    $cookie = $_COOKIE[LANG_COOKIE] ?? null;
    if (is_string($cookie) && isset(LANGS[$cookie])) return $cookie;

    // "en-US,en;q=0.9,pt;q=0.8" → a primeira que temos. Sem pt nem en, fica português.
    foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $part) {
        $tag = strtolower(trim(explode(';', $part)[0]));
        if (str_starts_with($tag, 'pt')) return 'pt-PT';
        if (str_starts_with($tag, 'en')) return 'en';
    }
    return 'pt-PT';
}

// Guarda a escolha num cookie para as páginas sem sessão (login, registo, landing) a lembrarem
function remember_lang(string $code): void {
    if (headers_sent()) return;
    setcookie(LANG_COOKIE, valid_lang($code), [
        'expires'  => time() + 365 * 24 * 3600,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function set_lang(?string $code): void {
    $GLOBALS['__finora_lang'] = valid_lang($code);
}

function current_lang(): string {
    return $GLOBALS['__finora_lang'] ??= detect_lang();
}

function html_lang(): string {
    return LANGS[current_lang()][1];
}

// Locale para o Intl do JavaScript (datas, números)
function js_locale(): string {
    return LANGS[current_lang()][2];
}

// t('Olá, {name}!', ['name' => $user]) → "Hello, Ana!". Devolve texto simples: quem o põe no HTML escapa-o.
function t(string $text, array $vars = []): string {
    static $tables = [];
    $lang = current_lang();
    if ($lang !== 'pt-PT') {
        $tables[$lang] ??= (function () use ($lang) {
            $file = __DIR__ . '/../lang/' . $lang . '.php';
            return is_file($file) ? require $file : [];
        })();
        $text = $tables[$lang][$text] ?? $text;
    }
    foreach ($vars as $k => $v) $text = str_replace('{' . $k . '}', (string) $v, $text);
    return $text;
}

// t() já escapado para HTML, para o caso mais comum: um título ou botão com te('Entrar')
function te(string $text, array $vars = []): string {
    return htmlspecialchars(t($text, $vars), ENT_QUOTES);
}

// Links do seletor de língua: a mesma página com ?lang=, mantendo os outros parâmetros
function lang_url(string $code): string {
    $q = $_GET;
    $q['lang'] = $code;
    return '?' . http_build_query($q);
}

// t() como texto JavaScript entre aspas, para usar dentro de <script>: const msg = (tj('Guardado') impresso aqui);
function tj(string $text, array $vars = []): string {
    return json_encode(t($text, $vars), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
