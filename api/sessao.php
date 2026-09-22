<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../config.php';

if (!empty($_SESSION['cliente_id'])) {
    echo json_encode([
        'logado'  => true,
        'cliente' => [
            'id'    => $_SESSION['cliente_id'],
            'nome'  => $_SESSION['cliente_nome'],
            'email' => $_SESSION['cliente_email'],
        ],
    ]);
} else {
    echo json_encode(['logado' => false]);
}
