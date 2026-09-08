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
    $email = trim(strtolower($body['email'] ?? ''));
    $senha = $body['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'Preencha e-mail e senha.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, nome, email, senha_hash FROM clientes WHERE email = ?");
    $stmt->execute([$email]);
    $cliente = $stmt->fetch();

    if (!$cliente || !password_verify($senha, $cliente['senha_hash'])) {
        http_response_code(401);
        echo json_encode(['sucesso' => false, 'erro' => 'E-mail ou senha incorretos.']);
        exit;
    }

    $_SESSION['cliente_id']    = $cliente['id'];
    $_SESSION['cliente_nome']  = $cliente['nome'];
    $_SESSION['cliente_email'] = $cliente['email'];

    echo json_encode([
        'sucesso' => true, 
        'cliente' => [
            'id'    => $cliente['id'], 
            'nome'  => $cliente['nome'], 
            'email' => $cliente['email']
        ]
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro no servidor: ' . $e->getMessage()]);
}