<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../helpers/auth.php';
require_pro_json();   // o assistente é do Finora Pro
require_once __DIR__ . '/../helpers/ai_assistant.php';   // Groq, ferramentas e limpeza da resposta

// Limites: cada pedido custa quota da Groq, por isso ninguém pode fazer pedidos sem fim
const AI_MAX_PER_HOUR    = 30;
const AI_MAX_MESSAGE_LEN = 500;
const AI_MAX_HISTORY     = 8;   // últimas mensagens da conversa que voltam a ser enviadas

function jsonOut($data, int $status = 200) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonOut(['error' => 'Pedido inválido.'], 405);

$uid = (int) ($_SESSION['user_id'] ?? 0);
if (!$uid) jsonOut(['error' => 'Não autenticado.'], 401);
session_write_close(); // a Groq pode demorar; não prende a sessão

$body    = json_decode(file_get_contents('php://input'), true);
$type    = is_array($body) && in_array($body['t'] ?? '', ['chat', 'insights', 'savings'], true) ? $body['t'] : 'chat';
$message = is_array($body) && is_string($body['m'] ?? null) ? trim($body['m']) : '';
if ($type === 'chat' && $message === '') jsonOut(['error' => 'Escreve uma pergunta.'], 422);
if (mb_strlen($message) > AI_MAX_MESSAGE_LEN) jsonOut(['error' => 'A pergunta é demasiado longa (máx. ' . AI_MAX_MESSAGE_LEN . ' caracteres).'], 422);

// Conversa anterior (vem do browser, por isso é validada: só user/assistant, texto, curta)
$history = [];
if ($type === 'chat' && is_array($body['h'] ?? null)) {
    foreach (array_slice($body['h'], -AI_MAX_HISTORY) as $h) {
        if (!is_array($h) || !in_array($h['r'] ?? '', ['user', 'assistant'], true) || !is_string($h['c'] ?? null)) continue;
        $history[] = ['role' => $h['r'], 'content' => mb_substr(ai_text($h['c'], 1000), 0, $h['r'] === 'user' ? AI_MAX_MESSAGE_LEN : 1000)];
    }
    while ($history && $history[0]['role'] !== 'user') array_shift($history); // a conversa começa sempre no utilizador
}

try {
    // Limite por utilizador na última hora (na BD: um novo login não o contorna)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_requests WHERE user_id = :uid AND created_at > NOW() - INTERVAL 1 HOUR");
    $stmt->execute([':uid' => $uid]);
    if ((int) $stmt->fetchColumn() >= AI_MAX_PER_HOUR) {
        jsonOut(['error' => 'Chegaste ao limite de ' . AI_MAX_PER_HOUR . ' pedidos por hora. Tenta daqui a pouco.'], 429);
    }
    $pdo->prepare("INSERT INTO ai_requests (user_id) VALUES (:uid)")->execute([':uid' => $uid]);
    $pdo->prepare("DELETE FROM ai_requests WHERE user_id = :uid AND created_at < NOW() - INTERVAL 1 DAY")->execute([':uid' => $uid]);

    $d = ai_context($pdo, $uid, $type === 'chat');   // resumo do mês; o chat leva só o essencial (tem ferramentas)
} catch (Exception $e) {
    error_log('[fdata] Erro BD: ' . $e->getMessage());
    jsonOut(['error' => 'Não foi possível carregar os teus dados. Tenta novamente.'], 500);
}

$tasks = [
    'insights' => 'Escreve exatamente 3 observações úteis sobre o meu mês, uma por linha, sem numeração nem marcadores. Cada uma com no máximo 25 palavras. Compara com o mês passado só usando a diferença que já vem calculada.',
    'savings'  => 'Escreve exatamente 3 sugestões de poupança concretas, uma por linha, sem numeração nem marcadores. Formato: ação concreta — poupa cerca de **X ' . currency_symbol() . '**/mês. Baseia cada uma numa categoria ou gasto real dos dados e usa valores modestos e realistas (nunca mais do que o que gastei nessa categoria).',
];

if ($type === 'chat') {
    // Resumo do mês + conversa anterior + pergunta nova, cada uma no seu papel.
    // Para o resto (outros períodos, metas, orçamentos, simulações) o modelo usa as ferramentas.
    $messages = [['role' => 'system', 'content' => ai_system_prompt(true) . "\n\n" . $d]];
    foreach ($history as $h) $messages[] = $h;
    $messages[] = ['role' => 'user', 'content' => ai_text($message, AI_MAX_MESSAGE_LEN) . "\n\n(Responde em 1 a 4 frases.)"];
    $reply = ai_chat_with_tools($pdo, $uid, $messages);
} else {
    $msg = ai_groq_request([
        ['role' => 'system', 'content' => ai_system_prompt(false)],
        ['role' => 'user',   'content' => $d . "\n\n" . $tasks[$type]],
    ]);
    $reply = is_string($msg['content'] ?? null) ? $msg['content'] : null;
}

if (!empty($GLOBALS['ai_rate_limited'])) jsonOut(['error' => t('O assistente está com muitos pedidos. Tenta outra vez daqui a um minuto.')], 429);
if ($reply === null || trim($reply) === '') jsonOut(['error' => 'O assistente não está disponível de momento. Tenta mais tarde.'], 502);
jsonOut(['reply' => ai_clean_reply($reply)]);
