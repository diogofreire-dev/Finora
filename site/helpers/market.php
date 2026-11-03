<?php
// site/helpers/market.php — cotações da Bolsa.
//
// Fonte: Yahoo Finance (não oficial, sem chave) para ações, ETFs, fundos e cripto; câmbio do BCE via Frankfurter.
// Tudo passa por estas funções: trocar de fornecedor (ex.: Finnhub) é mudar só este ficheiro.
//
// Segurança:
// - Os pedidos saem só do servidor (nada de chamadas do browser para fora).
// - O símbolo é validado antes de entrar num URL; HTTPS com certificado verificado; tempo máximo curto.
// - A resposta de fora é tratada como não fiável: só se aceitam números positivos e moedas com 3 letras.
// - Cache partilhada (só o servidor escreve) + limite de pedidos por utilizador.
// - Se a fonte falhar, fica o último preço conhecido; a página nunca rebenta.

require_once __DIR__ . '/throttle.php';

const MARKET_TTL_MIN      = 15;      // cotação "fresca" durante 15 minutos
const MARKET_FX_TTL_HOURS = 12;
const MARKET_FETCH_MAX    = 60;      // pedidos à fonte por utilizador…
const MARKET_FETCH_WINDOW = 10;      // …a cada 10 minutos
const MARKET_SEARCH_MAX   = 30;
const MARKET_WATCH_MAX    = 30;      // símbolos na lista de acompanhamento
const MARKET_SYMBOL_RE    = '/^[A-Z0-9^][A-Z0-9.\-=^]{0,19}$/';

// Normaliza e valida um símbolo ("vwce.de" → "VWCE.DE"); null se não for válido
function market_symbol(?string $s): ?string {
    $s = strtoupper(trim((string) $s));
    return preg_match(MARKET_SYMBOL_RE, $s) ? $s : null;
}

// Na cripto o Yahoo precisa do par: "BTC" sozinho é outro produto (um fundo americano)
function market_symbol_for(string $ticker, string $type): ?string {
    $s = market_symbol($ticker);
    if ($s && $type === 'cripto' && strpos($s, '-') === false) $s .= '-EUR';
    return $s;
}

function market_http_json(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 2,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,           // nunca http, file://, etc.
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Finora)',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if (!is_string($body) || $code !== 200 || strlen($body) > 2000000) return null;
    $json = json_decode($body, true, 32);
    return is_array($json) ? $json : null;
}

function market_positive(mixed $v): ?float {
    return (is_int($v) || is_float($v)) && is_finite((float) $v) && $v > 0 && $v < 1e9 ? (float) $v : null;
}

// Quantos euros vale 1 unidade da moeda (cache de 12 h). EUR = 1.
function market_fx(PDO $pdo, string $cur): ?float {
    if ($cur === 'EUR') return 1.0;
    if (!preg_match('/^[A-Z]{3}$/', $cur)) return null;
    $st = $pdo->prepare('SELECT eur_per_unit, fetched_at > NOW() - INTERVAL ' . MARKET_FX_TTL_HOURS . ' HOUR AS fresh FROM fx_rates WHERE currency = :c');
    $st->execute([':c' => $cur]);
    $row = $st->fetch();
    if ($row && $row['fresh']) return (float) $row['eur_per_unit'];

    $json = market_http_json('https://api.frankfurter.app/latest?from=' . $cur . '&to=EUR');
    $rate = market_positive($json['rates']['EUR'] ?? null);
    if ($rate === null) return $row ? (float) $row['eur_per_unit'] : null;      // fica o último conhecido
    $pdo->prepare('INSERT INTO fx_rates (currency, eur_per_unit, fetched_at) VALUES (:c, :r, NOW())
                   ON DUPLICATE KEY UPDATE eur_per_unit = VALUES(eur_per_unit), fetched_at = VALUES(fetched_at)')
        ->execute([':c' => $cur, ':r' => $rate]);
    return $rate;
}

function market_row(array $r, bool $stale): array {
    $prev = $r['prev_close_eur'] !== null ? (float) $r['prev_close_eur'] : null;
    $now  = (float) $r['price_eur'];
    return [
        'symbol' => $r['symbol'], 'name' => $r['name'], 'currency' => $r['currency'],
        'price' => (float) $r['price'], 'price_eur' => $now, 'prev_close_eur' => $prev,
        'change_pct' => $prev ? ($now - $prev) / $prev * 100 : null,
        'spark' => json_decode((string) $r['spark'], true) ?: [],
        'fetched_at' => $r['fetched_at'], 'minutes' => (int) ($r['minutes'] ?? 0), 'stale' => $stale,
    ];
}

// Cotação de um símbolo (cache de 15 min). Se a fonte falhar, devolve o último valor (stale = true) ou null.
function market_quote(PDO $pdo, int $uid, string $symbol): ?array {
    $symbol = market_symbol($symbol);
    if (!$symbol) return null;
    $st = $pdo->prepare('SELECT *, TIMESTAMPDIFF(MINUTE, fetched_at, NOW()) AS minutes FROM market_quotes WHERE symbol = :s');
    $st->execute([':s' => $symbol]);
    $cached = $st->fetch() ?: null;
    if ($cached && (int) $cached['minutes'] < MARKET_TTL_MIN) return market_row($cached, false);

    // Limite por utilizador: sem isto, alguém podia pôr o servidor a fazer milhares de pedidos e ser bloqueado
    if (!throttle_allow($pdo, 'market', 'u:' . $uid, MARKET_FETCH_MAX, MARKET_FETCH_WINDOW)) {
        return $cached ? market_row($cached, true) : null;
    }

    $json  = market_http_json('https://query1.finance.yahoo.com/v8/finance/chart/' . rawurlencode($symbol) . '?range=1mo&interval=1d');
    $res   = $json['chart']['result'][0] ?? null;
    $meta  = is_array($res) ? ($res['meta'] ?? []) : [];
    $price = market_positive($meta['regularMarketPrice'] ?? null);
    $cur   = is_string($meta['currency'] ?? null) ? $meta['currency'] : '';
    if ($price === null || !preg_match('/^[A-Za-z]{3}$/', $cur)) return $cached ? market_row($cached, true) : null;

    // Bolsas que cotam em cêntimos (Londres "GBp", Joanesburgo "ZAc", Telavive "ILA")
    $divide = in_array($cur, ['GBp', 'GBX', 'ZAc', 'ILA'], true) ? 100 : 1;
    $cur    = ['GBp' => 'GBP', 'GBX' => 'GBP', 'ZAc' => 'ZAR', 'ILA' => 'ILS'][$cur] ?? strtoupper($cur);
    $fx     = market_fx($pdo, $cur);
    if ($fx === null) return $cached ? market_row($cached, true) : null;

    $toEur  = fn($v) => round($v / $divide * $fx, 6);
    $prev   = market_positive($meta['chartPreviousClose'] ?? ($meta['previousClose'] ?? null));
    $closes = array_values(array_filter($res['indicators']['quote'][0]['close'] ?? [], fn($v) => market_positive($v) !== null));
    $spark  = array_map(fn($v) => round($toEur($v), 4), array_slice($closes, -31));
    // Variação do dia: fecho de ontem = penúltimo fecho do gráfico (o chartPreviousClose é o do início do mês)
    $yesterday = count($closes) >= 2 ? $closes[count($closes) - 2] : $prev;
    $name   = mb_substr(trim((string) ($meta['longName'] ?? $meta['shortName'] ?? $symbol)), 0, 120);

    $pdo->prepare('INSERT INTO market_quotes (symbol, name, currency, price, price_eur, prev_close_eur, spark, fetched_at)
                   VALUES (:s, :n, :c, :p, :pe, :pc, :sp, NOW())
                   ON DUPLICATE KEY UPDATE name = VALUES(name), currency = VALUES(currency), price = VALUES(price), price_eur = VALUES(price_eur),
                                           prev_close_eur = VALUES(prev_close_eur), spark = VALUES(spark), fetched_at = VALUES(fetched_at)')
        ->execute([':s' => $symbol, ':n' => $name, ':c' => $cur, ':p' => $price / $divide, ':pe' => $toEur($price),
                   ':pc' => $yesterday ? $toEur($yesterday) : null, ':sp' => json_encode($spark)]);
    $st->execute([':s' => $symbol]);
    return market_row($st->fetch(), false);
}

// Pesquisa de símbolos por nome ("edp" → EDP.LS, EDPR.LS…). Limitada por utilizador.
function market_search(PDO $pdo, int $uid, string $q): array {
    $q = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $q) ?? '');
    if (mb_strlen($q) < 1 || mb_strlen($q) > 40) return [];
    if (!throttle_allow($pdo, 'market_search', 'u:' . $uid, MARKET_SEARCH_MAX, MARKET_FETCH_WINDOW)) return [];
    $json = market_http_json('https://query1.finance.yahoo.com/v1/finance/search?quotesCount=8&newsCount=0&q=' . rawurlencode($q));
    $types = ['EQUITY' => 'Ação', 'ETF' => 'ETF', 'CRYPTOCURRENCY' => 'Cripto', 'MUTUALFUND' => 'Fundo', 'INDEX' => 'Índice'];
    $out = [];
    foreach (($json['quotes'] ?? []) as $r) {
        $sym = market_symbol($r['symbol'] ?? '');
        if (!$sym || !isset($types[$r['quoteType'] ?? ''])) continue;
        $out[] = [
            'symbol'   => $sym,
            'name'     => mb_substr((string) ($r['longname'] ?? $r['shortname'] ?? $sym), 0, 120),
            'exchange' => mb_substr((string) ($r['exchDisp'] ?? ''), 0, 40),
            'type'     => $types[$r['quoteType']],
            'kind'     => ['EQUITY' => 'acoes', 'ETF' => 'etf', 'CRYPTOCURRENCY' => 'cripto', 'MUTUALFUND' => 'fundo', 'INDEX' => 'outro'][$r['quoteType']],
        ];
    }
    return $out;
}

// Gráfico pequeno (SVG) com os fechos do último mês
function market_spark_svg(array $values, string $label): string {
    $values = array_values(array_filter($values, 'is_numeric'));
    if (count($values) < 2) return '';
    $min = min($values); $max = max($values); $span = $max - $min ?: 1;
    $n = count($values) - 1; $pts = [];
    foreach ($values as $i => $v) $pts[] = round($i / $n * 100, 2) . ',' . round(28 - ($v - $min) / $span * 26 - 1, 2);
    $dir = end($values) >= $values[0] ? 'up' : 'down';
    return '<svg class="mk-spark ' . $dir . '" viewBox="0 0 100 28" preserveAspectRatio="none" role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES) . '">'
         . '<polyline points="' . implode(' ', $pts) . '" fill="none" vector-effect="non-scaling-stroke"/></svg>';
}
