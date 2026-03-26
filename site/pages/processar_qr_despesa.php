<?php
// site/pages/processar_qr_despesa.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$uid = $_SESSION['user_id'] ?? null;
if (!$uid) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'parsear':
        parsearQRFatura();
        break;
    case 'criar':
        criarDespesa();
        break;
    case 'lookup_nif':
        lookupNIF();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
        break;
}

function parsearQRFatura() {
    global $pdo, $uid;

    $qrData = trim($_POST['qr_data'] ?? '');
    if (empty($qrData)) {
        echo json_encode(['success' => false, 'message' => 'Dados QR vazios']);
        exit;
    }

    // Parse QR no formato AT Portugal
    $dados = parseQR($qrData);

    if (!$dados) {
        echo json_encode(['success' => false, 'message' => 'QR Code inválido ou não suportado']);
        exit;
    }

    // Buscar nome da empresa se temos NIF
    $nomeEmpresa = '';
    $categoriaSugerida = '';

    if (!empty($dados['nif'])) {
        // Primeiro verificar cache local
        $stmt = $pdo->prepare("SELECT nome_empresa FROM nif_empresas_cache WHERE nif = ? LIMIT 1");
        $stmt->execute([$dados['nif']]);
        $cache = $stmt->fetch();

        if ($cache) {
            $nomeEmpresa = $cache['nome_empresa'];
        } else {
            // Tentar VIES API
            $viesResult = consultarVIES($dados['nif']);
            if ($viesResult['success']) {
                $nomeEmpresa = $viesResult['nome'];

                // Guardar no cache
                $stmt = $pdo->prepare("INSERT INTO nif_empresas_cache (nif, nome_empresa, fonte) VALUES (?, ?, 'vies') ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa)");
                $stmt->execute([$dados['nif'], $nomeEmpresa]);
            }
        }

        // Verificar categoria aprendida
        $stmt = $pdo->prepare("SELECT categoria, descricao FROM categorias_auto WHERE nif = ? LIMIT 1");
        $stmt->execute([$dados['nif']]);
        $categoriaAprendida = $stmt->fetch();

        if ($categoriaAprendida) {
            $categoriaSugerida = $categoriaAprendida['categoria'];
            if (!empty($categoriaAprendida['descricao'])) {
                $nomeEmpresa = $categoriaAprendida['descricao'];
            }
        }
    }

    // Preparar resposta
    $resposta = [
        'success' => true,
        'dados' => [
            'total' => $dados['total'] ?? 0,
            'data' => $dados['data'] ?? date('Y-m-d'),
            'nif' => $dados['nif'] ?? '',
            'numero' => $dados['numero'] ?? '',
            'empresa' => $nomeEmpresa,
            'categoria' => $categoriaSugerida,
            'tipo' => $dados['tipo'] ?? 'Fatura'
        ]
    ];

    echo json_encode($resposta);
}

function criarDespesa() {
    global $pdo, $uid;

    $dados = json_decode(file_get_contents('php://input'), true);

    if (!$dados) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }

    $total = floatval($dados['total'] ?? 0);
    $data = trim($dados['data'] ?? date('Y-m-d'));
    $empresa = trim($dados['empresa'] ?? 'Fatura QR');
    $categoria = trim($dados['categoria'] ?? '');
    $cardId = intval($dados['card_id'] ?? 0);
    $nif = trim($dados['nif'] ?? '');
    $qrRaw = trim($dados['qr_raw'] ?? '');

    if ($total <= 0) {
        echo json_encode(['success' => false, 'message' => 'Valor inválido']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Criar despesa
        $stmt = $pdo->prepare("
            INSERT INTO transactions (user_id, card_id, amount, description, category, transaction_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$uid, $cardId ?: null, $total, $empresa, $categoria, $data]);
        $despesaId = $pdo->lastInsertId();

        // Atualizar saldo do cartão se associado
        if ($cardId) {
            $stmt = $pdo->prepare("UPDATE cards SET balance = balance + ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$total, $cardId, $uid]);
        }

        // Registar importação QR
        $stmt = $pdo->prepare("
            INSERT INTO qr_despesas_import (id_utilizador, id_despesa, qr_raw, nif_emitente, data_fatura, total)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$uid, $despesaId, $qrRaw, $nif ?: null, $data, $total]);

        // Aprender categoria se foi especificada
        if (!empty($nif) && !empty($categoria)) {
            $stmt = $pdo->prepare("
                INSERT INTO categorias_auto (nif, categoria, descricao)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE categoria = VALUES(categoria), descricao = VALUES(descricao)
            ");
            $stmt->execute([$nif, $categoria, $empresa]);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Despesa criada com sucesso',
            'despesa_id' => $despesaId
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro ao criar despesa: ' . $e->getMessage()]);
    }
}

function lookupNIF() {
    global $pdo;

    $nif = trim($_GET['nif'] ?? '');

    if (!preg_match('/^\d{9}$/', $nif)) {
        echo json_encode(['success' => false, 'message' => 'NIF inválido']);
        exit;
    }

    // Verificar cache local
    $stmt = $pdo->prepare("SELECT nome_empresa FROM nif_empresas_cache WHERE nif = ? LIMIT 1");
    $stmt->execute([$nif]);
    $cache = $stmt->fetch();

    if ($cache) {
        echo json_encode([
            'success' => true,
            'nome' => $cache['nome_empresa'],
            'fonte' => 'cache'
        ]);
        exit;
    }

    // Consultar VIES
    $viesResult = consultarVIES($nif);

    if ($viesResult['success']) {
        // Guardar no cache
        $stmt = $pdo->prepare("INSERT INTO nif_empresas_cache (nif, nome_empresa, fonte) VALUES (?, ?, 'vies') ON DUPLICATE KEY UPDATE nome_empresa = VALUES(nome_empresa)");
        $stmt->execute([$nif, $viesResult['nome']]);

        echo json_encode([
            'success' => true,
            'nome' => $viesResult['nome'],
            'fonte' => 'vies'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Empresa não encontrada']);
    }
}

function parseQR($qrData) {
    // Formato AT Portugal: A:NIF B:NIF_Adquirente F:Data G:Numero O:Total
    $campos = [];
    $partes = explode('*', $qrData);

    foreach ($partes as $parte) {
        if (strpos($parte, ':') !== false) {
            list($codigo, $valor) = explode(':', $parte, 2);
            $campos[$codigo] = $valor;
        }
    }

    $dados = [];

    // NIF do emitente
    if (isset($campos['A'])) {
        $dados['nif'] = $campos['A'];
    }

    // Data (formato YYYYMMDD)
    if (isset($campos['F']) && strlen($campos['F']) == 8) {
        $ano = substr($campos['F'], 0, 4);
        $mes = substr($campos['F'], 4, 2);
        $dia = substr($campos['F'], 6, 2);
        $dados['data'] = "$ano-$mes-$dia";
    }

    // Número do documento
    if (isset($campos['G'])) {
        $dados['numero'] = $campos['G'];
    }

    // Total a pagar
    if (isset($campos['O'])) {
        $dados['total'] = floatval(str_replace(',', '.', $campos['O']));
    }

    // Tipo de documento
    if (isset($campos['G'])) {
        if (strpos($campos['G'], 'FT') === 0) {
            $dados['tipo'] = 'Fatura';
        } elseif (strpos($campos['G'], 'FS') === 0) {
            $dados['tipo'] = 'Fatura Simplificada';
        } elseif (strpos($campos['G'], 'FR') === 0) {
            $dados['tipo'] = 'Fatura Recibo';
        } else {
            $dados['tipo'] = 'Documento';
        }
    }

    return !empty($dados) ? $dados : false;
}

function consultarVIES($nif) {
    $url = "https://ec.europa.eu/taxation_customs/vies/rest-api/ms/PT/vat/" . urlencode($nif);

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'header' => "Accept: application/json\r\nUser-Agent: FreeCard/1.0\r\n"
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return ['success' => false];
    }

    $data = json_decode($response, true);

    if (isset($data['isValid']) && $data['isValid'] && isset($data['traderName'])) {
        return [
            'success' => true,
            'nome' => trim($data['traderName'])
        ];
    }

    return ['success' => false];
}
?>