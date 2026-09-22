<?php
// Configuração da conexão com o MySQL (padrão do XAMPP: usuário root, sem senha)
session_start();

$DB_HOST = 'localhost';
$DB_NAME = 'epilogo_livraria';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => 'Não foi possível conectar ao banco: ' . $e->getMessage()]);
    exit;
}
