<?php
// site/helpers/ownership.php
// Proteção contra IDOR: um ID vindo do pedido só é aceite se o recurso pertencer ao utilizador.

// Devolve o ID do cartão se for do utilizador, null se vier vazio.
// Se o cartão não existir ou for de outra pessoa, o pedido é recusado (403).
function owned_card_id(PDO $pdo, int $uid, $cardId): ?int {
    if (empty($cardId)) return null;

    $id   = (int) $cardId;
    $stmt = $pdo->prepare('SELECT 1 FROM cards WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $uid]);
    if (!$stmt->fetchColumn()) {
        http_response_code(403);
        exit('Cartão inválido.');
    }
    return $id;
}
