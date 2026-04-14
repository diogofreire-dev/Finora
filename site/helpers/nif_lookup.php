<?php
/**
 * nif_lookup.php  (v3 — SOAP correcto + nif.pt)
 * ──────────────────────────────────────────────
 * Coloca em: site/helpers/nif_lookup.php
 *
 * Fluxo:
 *   1. Cache local (tabela nif_empresas)
 *   2. nif.pt API  → devolve o nome da empresa em PT (requer chave gratuita)
 *   3. VIES SOAP   → valida o NIF mas PT geralmente NAO devolve o nome
 *   4. Not found
 *
 * Para a chave nif.pt:
 *   Regista em https://www.nif.pt/api (gratuito até 100 pedidos/dia)
 *   Depois define no config/db.php:  define('NIFPT_KEY', 'a-tua-chave');
 */

require_once __DIR__ . '/../../config/db.php';

// ─────────────────────────────────────────────────────────────────────────────
// PONTO DE ENTRADA PÚBLICO
// ─────────────────────────────────────────────────────────────────────────────
function nif_lookup(string $nif): array
{
    global $pdo;

    // 1. Cache local
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
    } catch (PDOException $e) { /* continua */ }

    // 2. nif.pt (melhor fonte para PT — devolve o nome da empresa)
    $nifpt = _nifpt_lookup($nif);
    if ($nifpt['success']) {
        _save_to_cache($nif, $nifpt['nome'], null, 'nif.pt');
        return $nifpt;
    }

    // 3. VIES SOAP (valida o NIF; pode devolver nome se PT cooperar)
    $vies = _vies_soap_lookup($nif);
    if ($vies['success']) {
        _save_to_cache($nif, $vies['nome'], null, 'VIES');
        return $vies;
    }

    // 4. Não encontrado
    return [
        'success'      => false,
        'nome'         => '',
        'id_categoria' => null,
        'source'       => 'none',
        'fonte'        => null,
        'verified'     => false,
        'vies_valid'   => $vies['valid'] ?? false, // NIF válido mas sem nome
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// nif.pt  —  API gratuita portuguesa, devolve nome + actividade
// Documentação: https://www.nif.pt/api
// ─────────────────────────────────────────────────────────────────────────────
function _nifpt_lookup(string $nif): array
{
    $apiKey = defined('NIFPT_KEY') ? NIFPT_KEY : '';
    if (empty($apiKey)) {
        return ['success' => false, 'source' => 'nif.pt', 'error' => 'no_key'];
    }

    $url = 'https://www.nif.pt/?json&nif=' . urlencode($nif)
         . '&key=' . urlencode($apiKey);

    $raw = _http_get($url, 6);
    if ($raw === false) {
        return ['success' => false, 'source' => 'nif.pt', 'error' => 'network'];
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || ($data['result'] ?? '') !== 'success') {
        $err = $data['message'] ?? 'unknown';
        return ['success' => false, 'source' => 'nif.pt', 'error' => $err];
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
        'activity'     => $first['activity'] ?? null,
        'address'      => isset($first['address']) ? $first['address'] : null,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// VIES SOAP  —  serviço oficial da Comissão Europeia
// WSDL: https://ec.europa.eu/taxation_customs/vies/services/checkVatService.wsdl
//
// NOTA: Portugal (PT) frequentemente NÃO devolve o nome por razões de
// privacidade (devolve "---"). O VIES serve principalmente para VALIDAR
// se o NIF é um VAT number activo.
// ─────────────────────────────────────────────────────────────────────────────
function _vies_soap_lookup(string $nif): array
{
    if (!class_exists('SoapClient')) {
        return ['success' => false, 'source' => 'vies', 'error' => 'soap_not_available'];
    }

    // Monta o envelope SOAP manualmente (mais rápido que carregar o WSDL)
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
            'header'        => implode("\r\n", [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ""',
                'Content-Length: ' . strlen($xml),
            ]),
            'content' => $xml,
        ],
    ]);

    $raw = @file_get_contents($endpoint, false, $ctx);
    if ($raw === false) {
        return ['success' => false, 'source' => 'vies', 'error' => 'network', 'valid' => false];
    }

    // Extrai campos da resposta XML
    $valid = _xml_extract($raw, 'valid')   ?? 'false';
    $nome  = _xml_extract($raw, 'name')    ?? '';

    $isValid = (strtolower(trim($valid)) === 'true');
    $nome    = trim($nome);

    // Portugal muitas vezes devolve "---" como nome
    if (!$isValid || empty($nome) || $nome === '---') {
        return [
            'success' => false,
            'source'  => 'vies',
            'error'   => $isValid ? 'name_not_returned' : 'invalid_vat',
            'valid'   => $isValid,
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
// GUARDAR NIF MANUALMENTE (chamado por save_company_nif.php)
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
        ");
        $stmt->execute([':nif' => $nif, ':nome' => $nome, ':cat' => $id_categoria]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS INTERNOS
// ─────────────────────────────────────────────────────────────────────────────

function _save_to_cache(string $nif, string $nome, ?int $cat, string $fonte): void
{
    global $pdo;
    try {
        $pdo->prepare("
            INSERT INTO nif_empresas (nif, nome_empresa, id_categoria, fonte)
            VALUES (:nif, :nome, :cat, :fonte)
            ON DUPLICATE KEY UPDATE
                nome_empresa = VALUES(nome_empresa),
                fonte        = VALUES(fonte)
        ")->execute([':nif' => $nif, ':nome' => $nome, ':cat' => $cat, ':fonte' => $fonte]);
    } catch (PDOException $e) { /* silencioso */ }
}

/** HTTP GET simples com timeout */
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

/** Extrai o conteúdo de uma tag XML simples (sem namespace) */
function _xml_extract(string $xml, string $tag): ?string
{
    if (preg_match('/<[^:>]*:?' . preg_quote($tag, '/') . '[^>]*>(.*?)<\/[^:>]*:?' . preg_quote($tag, '/') . '>/si', $xml, $m)) {
        return trim(strip_tags($m[1]));
    }
    return null;
}

/** Normaliza o nome: MAIÚSCULAS → Title Case, remove espaços duplos */
function _clean_name(string $name): string
{
    $name = trim($name);
    if ($name === mb_strtoupper($name, 'UTF-8')) {
        $name = mb_convert_case(mb_strtolower($name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        // Abreviaturas que ficam em maiúsculas
        $name = preg_replace_callback(
            '/\b(Lda\.?|Sa\.?|Unip\.?|Eirl\.?|Sl\.?|Sgps)\b/i',
            function($m) { return strtoupper($m[1]); },
            $name
        );
    }
    return preg_replace('/\s{2,}/', ' ', $name);
}

