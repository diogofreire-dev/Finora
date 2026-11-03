<?php
/**
 * nif_lookup.php — helper com função nif_lookup($nif)
 * Ordem de consulta:
 *   1. O nome que ESTE utilizador já deu a este NIF (transações dele lidas por QR)
 *   2. Base local hardcoded (lojas comuns PT) — é código nosso, por isso ganha à cache
 *   3. Tabela nif_empresas (cache partilhada, só com nomes vindos de fontes externas)
 *   4. VIES (Comissão Europeia): serviço oficial e gratuito de validação de números de IVA.
 *      Não precisa de chave e devolve o nome das empresas registadas para IVA (quase todas as lojas).
 *
 * A cache é partilhada por todos os utilizadores, por isso só o servidor escreve nela, e só
 * com o que veio das fontes externas. Um utilizador nunca escreve lá: se pudesse, mudava o
 * nome de uma empresa para todos (cache poisoning).
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

// Nomes que vêm de sites externos são dados de fora: sem tags, sem caracteres de controlo, tamanho limitado
function _nif_limpar_nome(?string $nome): ?string {
    $nome = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1F\x7F]/u', ' ', strip_tags((string) $nome))));
    return ($nome === '' || mb_strlen($nome) > 120) ? null : $nome;
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
                'Accept: application/json',
                'User-Agent: Finora/2.0 (consulta de NIF em faturas)',
            ]),
            'ignore_errors' => true,
        ],
        // Verificar sempre o certificado: sem isto, quem estiver na rede pode responder pelo site
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ];
    return @file_get_contents($url, false, stream_context_create($opts));
}

// O VIES devolve os nomes em maiúsculas ("PINGO DOCE - DISTRIBUIÇÃO ALIMENTAR SA").
// Passa para "Pingo Doce - Distribuição Alimentar SA", mantendo siglas e formas jurídicas.
function _nif_nome_bonito(string $nome): string {
    if ($nome !== mb_strtoupper($nome)) return $nome;            // já vem com maiúsculas e minúsculas
    $manter = ['SA', 'SGPS', 'EPE', 'CRL', 'ACE', 'SU', 'EDP', 'CTT', 'BP', 'NOS', 'MEO', 'TAP', 'IKEA', 'FNAC'];
    $minus  = ['DE', 'DA', 'DO', 'DAS', 'DOS', 'E', 'EM', 'NO', 'NA', 'NOS', 'NAS', 'A', 'O', 'AS', 'OS', 'PARA', 'COM', 'POR'];
    // Formas jurídicas escritas de várias maneiras: "S A", "S.A.", "L.DA"
    $nome   = preg_replace(['/\bS\.? ?A\.?$/u', '/\bL\.?DA\.?$/u'], ['SA', 'LDA'], $nome);
    $partes = explode(' - ', $nome);
    foreach ($partes as $p => $parte) {
        $palavras = explode(' ', $parte);
        // Uma só palavra curta antes de " - " é uma sigla ("CP - Comboios de Portugal", "EPAL - …")
        $sigla = $p === 0 && count($partes) > 1 && count($palavras) === 1 && mb_strlen($parte) <= 5;
        foreach ($palavras as $w => $pal) {
            if ($sigla || (in_array($pal, $manter, true) && !($w > 0 && in_array($pal, $minus, true)))) continue;
            if ($pal === 'LDA' || $pal === 'LDA.') { $palavras[$w] = 'Lda'; continue; }
            $palavras[$w] = ($w > 0 && in_array($pal, $minus, true)) ? mb_strtolower($pal) : mb_convert_case($pal, MB_CASE_TITLE);
        }
        $partes[$p] = implode(' ', $palavras);
    }
    return implode(' - ', $partes);
}

function _nif_api_vies(string $nif): array {
    $resp = _nif_http_get('https://ec.europa.eu/taxation_customs/vies/rest-api/ms/PT/vat/' . $nif);
    if ($resp === false) return ['success' => false];
    $data = json_decode($resp, true);
    // isValid = false: o NIF não está registado para IVA (particulares, algumas associações)
    if (!is_array($data) || empty($data['isValid']) || !is_string($data['name'] ?? null)) return ['success' => false];
    $nome = _nif_limpar_nome($data['name']);
    if (!$nome || $nome === '---') return ['success' => false];       // o VIES usa "---" quando não divulga o nome
    return ['success' => true, 'nome' => _nif_nome_bonito($nome), 'source' => 'vies', 'id_categoria' => null];
}

function nif_lookup(string $nif, ?int $uid = null): array {
    global $pdo;
    $nif = preg_replace('/\D/', '', $nif);
    if (strlen($nif) !== 9)
        return ['success' => false, 'error' => 'NIF inválido', 'source' => 'none'];

    // 1. O nome que o próprio utilizador já usou para este NIF
    if ($uid) {
        try {
            $stmt = $pdo->prepare("SELECT description, category FROM transactions
                                   WHERE user_id = :uid AND source = 'qr' AND source_id = :nif ORDER BY id DESC LIMIT 1");
            $stmt->execute([':uid' => $uid, ':nif' => (int) $nif]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC))
                return ['success' => true, 'nome' => $row['description'], 'categoria' => $row['category'], 'source' => 'teu'];
        } catch (PDOException $e) { error_log("[nif_lookup] BD: " . $e->getMessage()); }
    }

    // 2. Base local
    $base = _nif_base_local();
    if (isset($base[$nif])) {
        $e = $base[$nif];
        return ['success' => true, 'nome' => $e['nome'], 'id_categoria' => $e['cat'], 'source' => 'fallback'];
    }

    // 3. Cache partilhada (ignora entradas 'manual' antigas, escritas por utilizadores)
    try {
        $stmt = $pdo->prepare("SELECT nome_empresa, id_categoria FROM nif_empresas WHERE nif = :nif AND fonte <> 'manual' LIMIT 1");
        $stmt->execute([':nif' => $nif]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && ($nome = _nif_limpar_nome($row['nome_empresa'])))
            return ['success' => true, 'nome' => _nif_nome_bonito($nome), 'id_categoria' => $row['id_categoria'], 'source' => 'cache'];
    } catch (PDOException $e) { error_log("[nif_lookup] BD: " . $e->getMessage()); }

    // 4. VIES
    $r = _nif_api_vies($nif);
    if ($r['success']) { _nif_guardar_cache($pdo, $nif, $r['nome'], null, 'vies'); return $r; }

    return ['success' => false, 'error' => 'NIF não encontrado', 'source' => 'none'];
}

} // fim function_exists
