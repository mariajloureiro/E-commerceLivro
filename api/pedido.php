<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../config.php';

if (empty($_SESSION['cliente_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Você precisa estar logada para fechar um pedido.']);
    exit;
}
$clienteId = $_SESSION['cliente_id'];

$body        = json_decode(file_get_contents('php://input'), true);
$formaCodigo = $body['forma_pagamento'] ?? '';
$itens       = $body['itens'] ?? []; // [{ tipo: 'livro'|'kit', id, quantidade }]

if (!$formaCodigo || !count($itens)) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Dados incompletos para fechar o pedido.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // forma de pagamento (equivalente à EstrategiaPagamento escolhida)
    $stmt = $pdo->prepare("SELECT id FROM formas_pagamento WHERE codigo = ?");
    $stmt->execute([$formaCodigo]);
    $forma = $stmt->fetch();
    if (!$forma) {
        throw new Exception("Forma de pagamento inválida.");
    }
    $formaId = $forma['id'];

    $stmtLivro    = $pdo->prepare("SELECT id, titulo, preco, estoque FROM livros WHERE id = ? FOR UPDATE");
    $stmtKit      = $pdo->prepare("SELECT id, titulo, desconto_percentual FROM kits WHERE id = ?");
    $stmtKitItens = $pdo->prepare("SELECT livro_id FROM kit_livros WHERE kit_id = ?");

    $itensValidados = []; // linhas prontas para gravar em itens_pedido
    $baixasEstoque  = []; // livro_id => quantidade total a debitar (livro avulso + livros dentro de kits)
    $total = 0;

    foreach ($itens as $item) {
        // ---- Composite na prática: um item do pedido é OU um Livro (Leaf)
        //      OU um Kit (Composite), nunca os dois — igual à classe
        //      ItemPedido no JS, cujo `item` é sempre um ItemCatalogo. ----
        $tipo = $item['tipo'] ?? 'livro';
        $qtd  = (int) ($item['quantidade'] ?? 0);
        if ($qtd <= 0) {
            throw new Exception("Quantidade inválida.");
        }

        if ($tipo === 'kit') {
            $stmtKit->execute([$item['id']]);
            $kit = $stmtKit->fetch();
            if (!$kit) {
                throw new Exception("Kit não encontrado.");
            }

            $stmtKitItens->execute([$kit['id']]);
            $filhosIds = array_column($stmtKitItens->fetchAll(), 'livro_id');
            if (!count($filhosIds)) {
                throw new Exception("Kit \"{$kit['titulo']}\" está sem livros cadastrados.");
            }

            // resolve o Composite em seus componentes (Leaf) para conferir
            // estoque e somar o preço bruto, exatamente como KitDeLivros.getPreco()
            // faz no JS somando item.getPreco() de cada filho
            $precoBruto = 0;
            foreach ($filhosIds as $livroId) {
                $stmtLivro->execute([$livroId]);
                $livro = $stmtLivro->fetch();
                if (!$livro) {
                    throw new Exception("Livro do kit não encontrado.");
                }
                if ($livro['estoque'] < $qtd) {
                    throw new Exception("Estoque insuficiente para \"{$livro['titulo']}\" (parte do kit \"{$kit['titulo']}\").");
                }
                $precoBruto += (float) $livro['preco'];
                $baixasEstoque[$livroId] = ($baixasEstoque[$livroId] ?? 0) + $qtd;
            }

            $precoKit = round($precoBruto * (1 - ((float) $kit['desconto_percentual']) / 100), 2);
            $total += $precoKit * $qtd;

            $itensValidados[] = [
                'livro_id'       => null,
                'kit_id'         => $kit['id'],
                'quantidade'     => $qtd,
                'preco_unitario' => $precoKit,
            ];

        } else {
            $stmtLivro->execute([$item['id']]);
            $livro = $stmtLivro->fetch();

            if (!$livro) throw new Exception("Livro não encontrado.");
            if ($livro['estoque'] < $qtd) {
                throw new Exception("Estoque insuficiente para \"{$livro['titulo']}\".");
            }

            $total += $livro['preco'] * $qtd;
            $baixasEstoque[$livro['id']] = ($baixasEstoque[$livro['id']] ?? 0) + $qtd;

            $itensValidados[] = [
                'livro_id'       => $livro['id'],
                'kit_id'         => null,
                'quantidade'     => $qtd,
                'preco_unitario' => $livro['preco'],
            ];
        }
    }

    // cria o pedido já como confirmado ('C' — ver EstadoConfirmado no JS).
    // o processar() da EstrategiaPagamento e a transição de EstadoPedido
    // (Aberto -> Confirmado/Falhou) já aconteceram no front antes desta
    // chamada; aqui só persistimos o resultado.
    // status: A=aberto, C=confirmado, F=falhou, X=cancelado
    $stmt = $pdo->prepare("
        INSERT INTO pedidos (cliente_id, forma_pagamento_id, status, total)
        VALUES (?, ?, 'C', ?)
    ");
    $stmt->execute([$clienteId, $formaId, $total]);
    $pedidoId = $pdo->lastInsertId();

    // itens do pedido (livro avulso ou kit) + baixa de estoque agregada
    $stmtItem = $pdo->prepare("
        INSERT INTO itens_pedido (pedido_id, livro_id, kit_id, quantidade, preco_unitario)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($itensValidados as $it) {
        $stmtItem->execute([$pedidoId, $it['livro_id'], $it['kit_id'], $it['quantidade'], $it['preco_unitario']]);
    }

    $stmtBaixa = $pdo->prepare("UPDATE livros SET estoque = estoque - ? WHERE id = ?");
    foreach ($baixasEstoque as $livroId => $qtdTotal) {
        $stmtBaixa->execute([$qtdTotal, $livroId]);
    }

    $pdo->commit();

    echo json_encode([
        'sucesso'   => true,
        'pedido_id' => $pedidoId,
        'total'     => (float) $total,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
