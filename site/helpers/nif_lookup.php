<?php
/**
 * nif_lookup.php  (v4)
 * ──────────────────────────────────────────────────────────────────
 * Coloca em: site/helpers/nif_lookup.php
 *
 * Fluxo:
 *   1. Cache local DB (tabela nif_empresas)
 *   2. Fallback local: base de empresas PT conhecidas (sem API key)
 *   3. nif.pt API (se NIFPT_KEY estiver definido no config/db.php)
 *   4. VIES SOAP da Comissão Europeia (valida NIF; PT raro devolver nome)
 *   5. Not found
 *
 * Para ativar nif.pt (100 pedidos/dia grátis):
 *   1. Regista em https://www.nif.pt/api
 *   2. Adiciona no config/db.php:  define('NIFPT_KEY', 'a-tua-chave');
 */

require_once __DIR__ . '/../../config/db.php';

// ─────────────────────────────────────────────────────────────────────────────
// BASE LOCAL DE EMPRESAS PORTUGUESAS CONHECIDAS
// Cobre os NIFs mais comuns em faturas do dia-a-dia.
// Adiciona aqui as empresas que usas frequentemente.
// ─────────────────────────────────────────────────────────────────────────────
function _local_companies(): array
{
    return [
        // Supermercados
        '500762584' => ['nome' => 'Sonae MC (Continente)',       'cat' => 2],  // Continente
        '500769021' => ['nome' => 'Jerónimo Martins (Pingo Doce)','cat' => 2],
        '500189923' => ['nome' => 'Lidl Portugal',               'cat' => 2],
        '501320481' => ['nome' => 'Aldi Portugal',               'cat' => 2],
        '503218183' => ['nome' => 'Mercadona Portugal',          'cat' => 2],
        '502782330' => ['nome' => 'Intermarché Portugal',        'cat' => 2],
        '500677606' => ['nome' => 'Minipreço (DIA Portugal)',    'cat' => 2],
        '501437855' => ['nome' => 'Jumbo (Auchan Portugal)',     'cat' => 2],
        '500792462' => ['nome' => 'El Corte Inglés Portugal',    'cat' => 1],

        // Combustíveis
        '500028642' => ['nome' => 'Galp Energia',                'cat' => 3],
        '500275827' => ['nome' => 'BP Portugal',                 'cat' => 3],
        '500249289' => ['nome' => 'Repsol Portugal',             'cat' => 3],
        '500171582' => ['nome' => 'CEPSA Portugal',              'cat' => 3],

        // Telecomunicações
        '500697440' => ['nome' => 'MEO (Altice Portugal)',       'cat' => 8],
        '500379956' => ['nome' => 'NOS Comunicações',            'cat' => 8],
        '504024199' => ['nome' => 'Vodafone Portugal',           'cat' => 8],

        // Utilities
        '505703627' => ['nome' => 'EDP Comercial',               'cat' => 7],
        '500929385' => ['nome' => 'Galp Gás Natural',            'cat' => 7],
        '503504564' => ['nome' => 'EPAL (Águas de Lisboa)',      'cat' => 7],

        // Farmácias / Saúde
        '500138798' => ['nome' => 'Wells (Sonae Retalho Saúde)', 'cat' => 4],
        '501679491' => ['nome' => 'Farmácias Portuguesas',       'cat' => 4],

        // Transportes
        '500227515' => ['nome' => 'CP (Comboios de Portugal)',   'cat' => 3],
        '500287408' => ['nome' => 'Carris Metropolitana',        'cat' => 3],
        '503226078' => ['nome' => 'Uber Portugal',               'cat' => 3],
        '509819985' => ['nome' => 'Bolt Operations Portugal',    'cat' => 3],

        // Restauração
        '501442945' => ['nome' => 'McDonald\'s Portugal',        'cat' => 2],
        '500218887' => ['nome' => 'Burger King Portugal',        'cat' => 2],

        // Tecnologia / E-commerce
        '980419069' => ['nome' => 'Amazon EU (Luxemburgo)',      'cat' => 1],
        '506129885' => ['nome' => 'Worten (Sonae SR)',           'cat' => 1],
        '500281012' => ['nome' => 'Fnac Portugal',               'cat' => 1],
        '500735341' => ['nome' => 'Media Markt Portugal',        'cat' => 1],

        // Bancos
        '500143119' => ['nome' => 'Caixa Geral de Depósitos',   'cat' => 8],
        '500143043' => ['nome' => 'Banco BPI',                   'cat' => 8],
        '500143007' => ['nome' => 'Millennium BCP',              'cat' => 8],
        '500859863' => ['nome' => 'Banco Santander Totta',       'cat' => 8],

        // Outros
        '500440890' => ['nome' => 'CTT Correios de Portugal',    'cat' => 8],
        '500740349' => ['nome' => 'IKEA Portugal',               'cat' => 7],
        '509799704' => ['nome' => 'Decathlon Portugal',          'cat' => 1],
        '506015294' => ['nome' => 'Zara (Inditex Portugal)',     'cat' => 1],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// PONTO DE ENTRADA PÚBLICO
// ─────────────────────────────────────────────────────────────────────────────
function nif_lookup(string $nif): array
{
    global $pdo;
    $errors = [];

    // 1. Cache local DB
    try {
        $stmt = $pdo->prepare(
            "SELECT nome_empresa, id_categoria, fonte
             FROM nif_empresas WHERE nif = :nif LIMIT 1"
        );
        $stmt->execute([':nif' => $nif]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['nome_empresa'])) {
            return [
                'success'      => true,
                'nome'         => $row['nome_empresa'],
                'id_categoria' => $row['id_categoria'] ?? null,
                'source'       => 'cache',
                'fonte'        => $row['fonte'] ?? 'cache',
                'verified'     => true,
            ];
        }
    } catch (PDOException $e) {
        $errors[] = 'db: ' . $e->getMessage();
    }

    // 2. Fallback local (sem chamada API — instantâneo)
    $local = _local_companies();
    if (isset($local[$nif])) {
        $company = $local[$nif];
        _save_to_cache($nif, $company['nome'], $company['cat'], 'local');
        return [
            'success'      => true,
            'nome'         => $company['nome'],
            'id_categoria' => $company['cat'],
            'source'       => 'local',
            'fonte'        => 'Base local',
            'verified'     => true,
        ];
    }

    // 3. nif.pt API (necessita de chave — gratuita em https://www.nif.pt/api)
    $nifpt = _nifpt_lookup($nif);
    if ($nifpt['success']) {
        _save_to_cache($nif, $nifpt['nome'], null, 'nif.pt');
        return $nifpt;
    }
    $errors[] = 'nif.pt: ' . ($nifpt['error'] ?? 'unknown');

    // 4. VIES SOAP (Portugal raramente devolve nome, mas tenta)
    $vies = _vies_soap_lookup($nif);
    if ($vies['success']) {
        _save_to_cache($nif, $vies['nome'], null, 'VIES');
        return $vies;
    }
    $errors[] = 'vies: ' . ($vies['error'] ?? 'unknown');

    return [
        'success'      => false,
        'nome'         => '',
        'id_categoria' => null,
        'source'       => 'none',
        'fonte'        => null,
        'verified'     => false,
        'vies_valid'   => $vies['valid'] ?? false,
        '_errors'      => $errors, // visível com ?debug=1
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// nif.pt
// ─────────────────────────────────────────────────────────────────────────────
function _nifpt_lookup(string $nif): array
{
    $apiKey = defined('NIFPT_KEY') ? NIFPT_KEY : '';
    if (empty($apiKey)) {
        return ['success' => false, 'source' => 'nif.pt', 'error' => 'no_key — define NIFPT_KEY no config/db.php'];
    }

    $url = 'https://www.nif.pt/?json&nif=' . urlencode($nif)
         . '&key=' . urlencode($apiKey);

    $raw = _http_get($url, 6);
    if ($raw === false) {
        return ['success' => false, 'source' => 'nif.pt', 'error' => 'network_error'];
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || ($data['result'] ?? '') !== 'success') {
        return ['success' => false, 'source' => 'nif.pt', 'error' => $data['message'] ?? 'api_error'];
    }

    $records = $data['records'] ?? [];
    $first   = !empty($records) ? reset($records) : null;
    $nome    = trim($first['title'] ?? '');

    if (empty($nome)) {
        return ['success' => false, 'source' => 'nif.pt', 'error' => 'empty_name'];
    }

    return [
        'success'      => true,
        'nome'         => _clean_name($nome),
        'id_categoria' => null,
        'source'       => 'nif.pt',
        'fonte'        => 'nif.pt',
        'verified'     => true,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// VIES SOAP — ec.europa.eu (Portugal geralmente não devolve nome)
// ─────────────────────────────────────────────────────────────────────────────
function _vies_soap_lookup(string $nif): array
{
    $endpoint = 'https://ec.europa.eu/taxation_customs/vies/services/checkVatService';
    $xml = '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope
    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:tns="urn:ec.europa.eu:taxud:vies:services:checkVat:types">
  <soap:Body>
    <tns:checkVat>
      <tns:countryCode>PT</tns:countryCode>
      <tns:vatNumber>' . htmlspecialchars($nif, ENT_XML1) . '</tns:vatNumber>
    </tns:checkVat>
  </soap:Body>
</soap:Envelope>';

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'timeout'       => 8,
            'ignore_errors' => true,
            'header'        => "Content-Type: text/xml; charset=utf-8\r\nSOAPAction: \"\"\r\nContent-Length: " . strlen($xml),
            'content'       => $xml,
        ],
    ]);

    $raw = @file_get_contents($endpoint, false, $ctx);
    if ($raw === false) {
        return ['success' => false, 'source' => 'vies', 'error' => 'network_error', 'valid' => false];
    }

    $valid = strtolower(trim(_xml_extract($raw, 'valid') ?? 'false')) === 'true';
    $nome  = trim(_xml_extract($raw, 'name') ?? '');

    if (!$valid || empty($nome) || $nome === '---') {
        return [
            'success' => false,
            'source'  => 'vies',
            'error'   => $valid ? 'pt_does_not_return_name' : 'invalid_vat',
            'valid'   => $valid,
        ];
    }

    return [
        'success'      => true,
        'nome'         => _clean_name($nome),
        'id_categoria' => null,
        'source'       => 'vies',
        'fonte'        => 'VIES',
        'verified'     => true,
        'valid'        => true,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// GUARDAR MANUALMENTE (chamado por save_company_nif.php)
// ─────────────────────────────────────────────────────────────────────────────
function save_company_manual(string $nif, string $nome, ?int $id_categoria): bool
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO nif_empresas (nif, nome_empresa, id_categoria, fonte)
            VALUES (:nif, :nome, :cat, 'manual')
            ON DUPLICATE KEY UPDATE
                nome_empresa = VALUES(nome_empresa),
                id_categoria = VALUES(id_categoria),
                fonte        = 'manual'
        ")->execute([':nif' => $nif, ':nome' => $nome, ':cat' => $id_categoria]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// UTILS
// ─────────────────────────────────────────────────────────────────────────────

function _save_to_cache(string $nif, string $nome, ?int $cat, string $fonte): void
{
    global $pdo;
    try {
        $pdo->prepare("
            INSERT INTO nif_empresas (nif, nome_empresa, id_categoria, fonte)
            VALUES (:nif, :nome, :cat, :fonte)
            ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa), fonte = VALUES(fonte)
        ")->execute([':nif' => $nif, ':nome' => $nome, ':cat' => $cat, ':fonte' => $fonte]);
    } catch (PDOException $e) { /* silencioso */ }
}

function _http_get(string $url, int $timeout = 6): string|false
{
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'timeout'       => $timeout,
            'ignore_errors' => true,
            'header'        => "User-Agent: FreeCard/1.0\r\nAccept: application/json",
        ],
    ]);
    return @file_get_contents($url, false, $ctx);
}

function _xml_extract(string $xml, string $tag): ?string
{
    if (preg_match('/<[^:>]*:?' . preg_quote($tag, '/') . '[^>]*>(.*?)<\/[^:>]*:?' . preg_quote($tag, '/') . '>/si', $xml, $m)) {
        return trim(strip_tags($m[1]));
    }
    return null;
}

function _clean_name(string $name): string
{
    $name = trim($name);
    if ($name === mb_strtoupper($name, 'UTF-8')) {
        $name = mb_convert_case(mb_strtolower($name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $name = preg_replace_callback(
            '/\b(Lda\.?|Sa\.?|Unip\.?|Eirl\.?|Sl\.?|Sgps)\b/i',
            fn($m) => strtoupper($m[1]),
            $name
        );
    }
    return preg_replace('/\s{2,}/', ' ', $name);
}

