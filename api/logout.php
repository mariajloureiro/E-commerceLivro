<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../config.php';

$_SESSION = [];
session_destroy();

echo json_encode(['sucesso' => true]);
