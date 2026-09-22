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

    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $nome  = trim($body['nome'] ?? '');
    $email = trim(strtolower($body['email'] ?? ''));
    $senha = $body['senha'] ?? '';

    if (empty($nome) || empty($email) || strlen($senha) < 6) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Preencha nome, e-mail e uma senha com pelo menos 6 caracteres.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['sucesso' => false, 'erro' => 'Já existe uma conta com esse e-mail.']);
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