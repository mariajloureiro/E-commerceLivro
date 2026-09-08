<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../config.php';

if (empty($_SESSION['cliente_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Você precisa estar logada.']);
    exit;
}
$clienteId = $_SESSION['cliente_id'];

$body     = json_decode(file_get_contents('php://input'), true);
$pedidoId = (int) ($body['pedido_id'] ?? 0);

if (!$pedidoId) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Pedido inválido.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, cliente_id, status FROM pedidos WHERE id = ? FOR UPDATE");
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch();

    if (!$pedido || (int) $pedido['cliente_id'] !== (int) $clienteId) {
        throw new Exception("Pedido não encontrado.");
    }

    // ---------------------------------------------------------------
    // Padrão State no back-end: o status guardado em `pedidos` é o mesmo
    // dado que, no front, decide qual classe concreta de EstadoPedido está
    // ativa. Aqui replicamos a mesma regra de transição: só um pedido em
    // EstadoAberto ('A') ou EstadoConfirmado ('C') pode ir para
    // EstadoCancelado ('X'). EstadoFalhou ('F') e EstadoCancelado ('X')
    // não permitem cancelar de novo.
    // ---------------------------------------------------------------
    if (!in_array($pedido['status'], ['A', 'C'], true)) {
        $nomes = ['A' => 'aberto', 'C' => 'confirmado', 'F' => 'falhou', 'X' => 'cancelado'];
        $nomeAtual = $nomes[$pedido['status']] ?? $pedido['status'];
        throw new Exception("Pedido no estado \"{$nomeAtual}\" não pode mais ser cancelado.");
    }

    // devolve ao estoque cada livro do pedido — inclusive os livros que
    // estavam "escondidos" dentro de um Kit (Composite), resolvendo o
    // composto em seus componentes antes de repor
    $stmtItens = $pdo->prepare("SELECT livro_id, kit_id, quantidade FROM itens_pedido WHERE pedido_id = ?");
    $stmtItens->execute([$pedidoId]);
    $itens = $stmtItens->fetchAll();

    $stmtDevolve  = $pdo->prepare("UPDATE livros SET estoque = estoque + ? WHERE id = ?");
    $stmtKitItens = $pdo->prepare("SELECT livro_id FROM kit_livros WHERE kit_id = ?");

    foreach ($itens as $it) {
        if ($it['livro_id']) {
            $stmtDevolve->execute([$it['quantidade'], $it['livro_id']]);
        } elseif ($it['kit_id']) {
            $stmtKitItens->execute([$it['kit_id']]);
            foreach ($stmtKitItens->fetchAll() as $filho) {
                $stmtDevolve->execute([$it['quantidade'], $filho['livro_id']]);
            }
        }
    }

    $stmt = $pdo->prepare("UPDATE pedidos SET status = 'X' WHERE id = ?");
    $stmt->execute([$pedidoId]);

    $pdo->commit();
    echo json_encode(['sucesso' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
