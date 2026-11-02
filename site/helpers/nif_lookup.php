<?php
/**
 * nif_lookup.php — helper com função nif_lookup($nif)
 * Ordem de consulta:
 *   1. Tabela nif_empresas (cache local da BD)
 *   2. Base local hardcoded (lojas comuns PT)
 *   3. API nif.pt
 *   4. API nunl.pt (fallback)
 */

if (!function_exists('nif_lookup')) {

function _nif_base_local(): array {
    return [
        '500477485' => ['nome' => 'Continente (Sonae MC)', 'cat' => 2],
        '500484522' => ['nome' => 'Pingo Doce (Jerónimo Martins)', 'cat' => 2],
        '501215072' => ['nome' => 'Lidl Portugal', 'cat' => 2],
        '506671278' => ['nome' => 'Aldi Portugal', 'cat' => 2],
        '500377929' => ['nome' => 'Intermarché Portugal', 'cat' => 2],
        '502762449' => ['nome' => 'Mercadona Portugal', 'cat' => 2],
        '504057190' => ['nome' => 'Minipreço (DIA Portugal)', 'cat' => 2],
        '500139024' => ['nome' => 'El Corte Inglés', 'cat' => 1],
        '500826932' => ['nome' => 'Pão de Açúcar', 'cat' => 2],
        '500166798' => ['nome' => 'Jumbo (Auchan)', 'cat' => 2],
        '500189398' => ['nome' => 'Zara Portugal (Inditex)', 'cat' => 1],
        '502387506' => ['nome' => 'H&M Portugal', 'cat' => 1],
        '508276046' => ['nome' => 'Primark Portugal', 'cat' => 1],
        '504255792' => ['nome' => 'C&A Portugal', 'cat' => 1],
        '500163624' => ['nome' => 'Fnac Portugal', 'cat' => 1],
        '500827852' => ['nome' => 'Worten', 'cat' => 1],
        '500167170' => ['nome' => 'Media Markt Portugal', 'cat' => 1],
        '501615064' => ['nome' => 'IKEA Portugal', 'cat' => 7],
        '504255555' => ['nome' => 'Leroy Merlin Portugal', 'cat' => 7],
        '502540427' => ['nome' => 'Decathlon Portugal', 'cat' => 5],
        '504923340' => ['nome' => 'Sport Zone', 'cat' => 5],
        '500192082' => ['nome' => 'Farmácias Portuguesas', 'cat' => 4],
        '506774765' => ['nome' => 'Wells (Saúde e Bem-estar)', 'cat' => 4],
        '500006527' => ['nome' => 'Galp Energia', 'cat' => 3],
        '500276313' => ['nome' => 'BP Portugal', 'cat' => 3],
        '500828470' => ['nome' => 'Repsol Portugal', 'cat' => 3],
        '503264032' => ['nome' => 'Shell Portugal', 'cat' => 3],
        '500703350' => ['nome' => 'Cepsa Portugal', 'cat' => 3],
        '500194641' => ['nome' => "McDonald's Portugal", 'cat' => 2],
        '503264543' => ['nome' => 'Burger King Portugal', 'cat' => 2],
        '501546758' => ['nome' => 'KFC Portugal', 'cat' => 2],
        '502764491' => ['nome' => 'Pizza Hut Portugal', 'cat' => 2],
        '500271507' => ['nome' => 'Telepizza Portugal', 'cat' => 2],
        '501690558' => ['nome' => "Domino's Pizza Portugal", 'cat' => 2],
        '500072126' => ['nome' => 'Starbucks Portugal', 'cat' => 2],
        '500377510' => ['nome' => 'MEO (Altice Portugal)', 'cat' => 8],
        '500379610' => ['nome' => 'NOS Comunicações', 'cat' => 8],
        '500187255' => ['nome' => 'Vodafone Portugal', 'cat' => 8],
        '500278322' => ['nome' => 'CP — Comboios de Portugal', 'cat' => 3],
        '500265522' => ['nome' => 'Carris Metropolitana', 'cat' => 3],
        '500293073' => ['nome' => 'Metro de Lisboa', 'cat' => 3],
        '501320079' => ['nome' => 'Metro do Porto', 'cat' => 3],
        '500244802' => ['nome' => 'TAP Air Portugal', 'cat' => 3],
        '503264331' => ['nome' => 'EDP Comercial', 'cat' => 7],
        '500697256' => ['nome' => 'EDP Distribuição', 'cat' => 7],
        '500143798' => ['nome' => 'Millennium BCP', 'cat' => 8],
        '500269435' => ['nome' => 'Caixa Geral de Depósitos', 'cat' => 8],
        '501214534' => ['nome' => 'Banco Santander Portugal', 'cat' => 8],
        '501322600' => ['nome' => 'BPI — Banco Português de Investimento', 'cat' => 8],
        '500264524' => ['nome' => 'Fidelidade Seguros', 'cat' => 8],
        '500710996' => ['nome' => 'CTT — Correios de Portugal', 'cat' => 8],
        '600073372' => ['nome' => 'AT — Autoridade Tributária', 'cat' => 8],
    ];
}

function _nif_guardar_cache($pdo, string $nif, string $nome, ?int $cat, string $fonte): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO nif_empresas (nif, nome_empresa, id_categoria, fonte)
            VALUES (:nif, :nome, :cat, :fonte)
            ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa), fonte = VALUES(fonte)
        ");
        $stmt->execute([':nif' => $nif, ':nome' => $nome, ':cat' => $cat, ':fonte' => $fonte]);
    } catch (PDOException $e) {
        error_log("[nif_lookup] Erro cache: " . $e->getMessage());
    }
}

function _nif_http_get(string $url): string|false {
    $opts = [
        'http' => [
            'timeout'       => 8,
            'method'        => 'GET',
            'header'        => implode("\r\n", [
                'Accept: application/json, text/html, */*',
                'Accept-Language: pt-PT,pt;q=0.9',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                'Referer: https://www.nif.pt/',
            ]),
            'ignore_errors' => true,
        ],
        // Verificar sempre o certificado: sem isto, quem estiver na rede pode responder pelo site
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ];
    return @file_get_contents($url, false, stream_context_create($opts));
}

function _nif_api_nifpt(string $nif): array {
    $url  = "https://www.nif.pt/?json&nif=" . urlencode($nif) . "&q=" . urlencode($nif);
    $resp = _nif_http_get($url);
    if ($resp === false) return ['success' => false];
    $data = json_decode($resp, true);
    if (!$data) return ['success' => false];
    $nome = null;
    if (!empty($data['records'])) {
        $r = reset($data['records']);
        $nome = $r['title'] ?? $r['name'] ?? null;
    } elseif (!empty($data['title'])) { $nome = $data['title'];
    } elseif (!empty($data['name']))  { $nome = $data['name']; }
    if (empty($nome)) return ['success' => false];
    return ['success' => true, 'nome' => trim($nome), 'source' => 'nif.pt', 'fonte' => 'nifpt', 'id_categoria' => null, 'verified' => true];
}

function _nif_api_nunl(string $nif): array {
    $url  = "https://www.nunl.pt/consultar-nif/" . urlencode($nif);
    $resp = _nif_http_get($url);
    if ($resp === false) return ['success' => false];
    if (preg_match('/<h[12][^>]*>\s*([^<]{4,80})\s*<\/h[12]>/i', $resp, $m)) {
        $nome = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        if (strlen($nome) > 3 && !preg_match('/consultar|nif|nunl|pesquis/i', $nome))
            return ['success' => true, 'nome' => $nome, 'source' => 'nunl', 'fonte' => 'nifpt', 'id_categoria' => null, 'verified' => false];
    }
    return ['success' => false];
}

function nif_lookup(string $nif): array {
    global $pdo;
    $nif = preg_replace('/\D/', '', $nif);
    if (strlen($nif) !== 9)
        return ['success' => false, 'error' => 'NIF inválido', 'source' => 'none'];

    // 1. BD cache
    try {
        $stmt = $pdo->prepare("SELECT nome_empresa, id_categoria, fonte FROM nif_empresas WHERE nif = :nif LIMIT 1");
        $stmt->execute([':nif' => $nif]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['nome_empresa']))
            return ['success' => true, 'nome' => $row['nome_empresa'], 'id_categoria' => $row['id_categoria'], 'source' => 'cache', 'fonte' => $row['fonte'], 'verified' => true];
    } catch (PDOException $e) { error_log("[nif_lookup] BD: " . $e->getMessage()); }

    // 2. Base local
    $base = _nif_base_local();
    if (isset($base[$nif])) {
        $e = $base[$nif];
        _nif_guardar_cache($pdo, $nif, $e['nome'], $e['cat'], 'fallback');
        return ['success' => true, 'nome' => $e['nome'], 'id_categoria' => $e['cat'], 'source' => 'fallback', 'fonte' => 'fallback', 'verified' => true];
    }

    // 3. API nif.pt
    $r = _nif_api_nifpt($nif);
    if ($r['success']) { _nif_guardar_cache($pdo, $nif, $r['nome'], null, 'nifpt'); return $r; }

    // 4. API nunl.pt
    $r2 = _nif_api_nunl($nif);
    if ($r2['success']) { _nif_guardar_cache($pdo, $nif, $r2['nome'], null, 'nifpt'); return $r2; }

    return ['success' => false, 'error' => 'NIF não encontrado', 'source' => 'none'];
}

} // fim function_exists

if (!function_exists('save_company_manual')) {
function save_company_manual(string $nif, string $nome, ?int $id_categoria): bool {
    global $pdo;
    $nif = preg_replace('/\D/', '', $nif);
    if (strlen($nif) !== 9 || empty($nome)) return false;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO nif_empresas (nif, nome_empresa, id_categoria, fonte)
            VALUES (:nif, :nome, :cat, 'manual')
            ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa), id_categoria = VALUES(id_categoria), fonte = 'manual'
        ");
        $stmt->execute([':nif' => $nif, ':nome' => $nome, ':cat' => $id_categoria]);
        return true;
    } catch (PDOException $e) {
        error_log("[save_company_manual] Erro: " . $e->getMessage());
        return false;
    }
}
}