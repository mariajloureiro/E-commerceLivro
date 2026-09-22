<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../config.php';

// Autoria normalizada (tabelas autores + autor_livro, N:N): GROUP_CONCAT
// junta os nomes de todos os autores de um livro em uma única string,
// então pro front-end nada muda — `autor` continua chegando como texto.
$stmt = $pdo->query("
    SELECT l.id, l.titulo, l.preco, l.estoque, l.capa,
           g.nome AS genero,
           GROUP_CONCAT(a.nome ORDER BY a.nome SEPARATOR ', ') AS autor
    FROM livros l
    LEFT JOIN generos g      ON g.id = l.genero_id
    LEFT JOIN autor_livro al ON al.livro_id = l.id
    LEFT JOIN autores a      ON a.id = al.autor_id
    GROUP BY l.id, l.titulo, l.preco, l.estoque, l.capa, g.nome
    ORDER BY l.id
");

$livros = $stmt->fetchAll();

// preco vem como string do PDO; converte pra número
foreach ($livros as &$l) {
    $l['id'] = (int) $l['id'];
    $l['preco'] = (float) $l['preco'];
    $l['estoque'] = (int) $l['estoque'];
}
unset($l);

// ---------------------------------------------------------------------
// Kits — padrão Composite: cada kit agrupa vários livros e aplica um
// desconto sobre a soma dos preços. O front monta um objeto KitDeLivros
// (Composite) a partir dos livro_ids retornados aqui, reaproveitando as
// mesmas instâncias de Livro (Leaf) já carregadas em `acervo`.
// ---------------------------------------------------------------------
$stmtKits = $pdo->query("SELECT id, titulo, desconto_percentual FROM kits ORDER BY id");
$kits = $stmtKits->fetchAll();

$stmtItensKit = $pdo->prepare("SELECT livro_id FROM kit_livros WHERE kit_id = ? ORDER BY id");
foreach ($kits as &$k) {
    $k['id'] = (int) $k['id'];
    $k['desconto_percentual'] = (float) $k['desconto_percentual'];
    $stmtItensKit->execute([$k['id']]);
    $k['livro_ids'] = array_map(fn($row) => (int) $row['livro_id'], $stmtItensKit->fetchAll());
}
unset($k);

echo json_encode(['livros' => $livros, 'kits' => $kits]);