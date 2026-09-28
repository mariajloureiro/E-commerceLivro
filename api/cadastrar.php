<?php
// Silencia exibição de erros em HTML para não quebrar o JSON no JavaScript
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Inicia a sessão se ainda não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

try {
    require __DIR__ . '/../config.php';
    require __DIR__ . '/ValidadorCadastro.php';

    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $nome  = trim($body['nome'] ?? '');
    $email = trim(strtolower($body['email'] ?? ''));
    $senha = $body['senha'] ?? '';

    //Chain of Responsibility
    // Cada regra (campos obrigatórios -> formato do e-mail -> tamanho da
    // senha -> e-mail duplicado) roda em sequência. a cadeia para no
    // primeiro handler que encontrar um problema e devolve só essa mensagem
    $cadeia = montarCadeiaValidacaoCadastro($pdo);
    $erroValidacao = $cadeia->validar(['nome' => $nome, 'email' => $email, 'senha' => $senha]);

    if ($erroValidacao !== null) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => $erroValidacao]);
        exit;
    }

    $hash = password_hash($senha, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO clientes (nome, email, senha_hash) VALUES (?, ?, ?)");
    $stmt->execute([$nome, $email, $hash]);
    $clienteId = $pdo->lastInsertId();

    // Salva na sessão para logar o cliente recém-cadastrado
    $_SESSION['cliente_id']    = $clienteId;
    $_SESSION['cliente_nome']  = $nome;
    $_SESSION['cliente_email'] = $email;

    echo json_encode([
        'sucesso' => true,
        'cliente' => [
            'id'    => $clienteId,
            'nome'  => $nome,
            'email' => $email
        ]
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro no servidor: ' . $e->getMessage()]);
}