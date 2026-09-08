-- =========================================================================
-- BANCO DE DADOS: Epílogo (Livraria)
-- Status do pedido: A = Aberto | C = Confirmado | F = Falhou | X = Cancelado
-- =========================================================================

CREATE DATABASE IF NOT EXISTS epilogo_livraria
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE epilogo_livraria;

-- Gêneros
CREATE TABLE generos (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  nome  VARCHAR(60) NOT NULL UNIQUE
);

-- Autores
CREATE TABLE autores (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  nome  VARCHAR(120) NOT NULL UNIQUE
);

-- Livros
CREATE TABLE livros (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  titulo    VARCHAR(150) NOT NULL,
  preco     DECIMAL(10,2) NOT NULL,
  estoque   INT NOT NULL DEFAULT 0,
  genero_id INT,
  capa      VARCHAR(255),
  CONSTRAINT fk_livro_genero FOREIGN KEY (genero_id) REFERENCES generos(id)
);

-- Associação: Autores <-> Livros (N:N)
CREATE TABLE autor_livro (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  autor_id  INT NOT NULL,
  livro_id  INT NOT NULL,
  CONSTRAINT fk_autorlivro_autor FOREIGN KEY (autor_id) REFERENCES autores(id),
  CONSTRAINT fk_autorlivro_livro FOREIGN KEY (livro_id) REFERENCES livros(id) ON DELETE CASCADE,
  CONSTRAINT uq_autor_livro UNIQUE (autor_id, livro_id)
);

-- Kits de Livros
CREATE TABLE kits (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  titulo              VARCHAR(150) NOT NULL,
  desconto_percentual DECIMAL(5,2) NOT NULL DEFAULT 0
);

-- Associação: Kits <-> Livros
CREATE TABLE kit_livros (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  kit_id   INT NOT NULL,
  livro_id INT NOT NULL,
  CONSTRAINT fk_kitlivro_kit   FOREIGN KEY (kit_id)   REFERENCES kits(id)   ON DELETE CASCADE,
  CONSTRAINT fk_kitlivro_livro FOREIGN KEY (livro_id) REFERENCES livros(id),
  CONSTRAINT uq_kit_livro UNIQUE (kit_id, livro_id)
);

-- Clientes
CREATE TABLE clientes (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nome       VARCHAR(120) NOT NULL,
  email      VARCHAR(160) NOT NULL UNIQUE,
  senha_hash VARCHAR(255) NOT NULL,
  criado_em  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Formas de Pagamento
CREATE TABLE formas_pagamento (
  id     INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL UNIQUE,
  nome   VARCHAR(60) NOT NULL
);

-- Pedidos
CREATE TABLE pedidos (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id         INT NOT NULL,
  forma_pagamento_id INT,
  status             CHAR(1) NOT NULL DEFAULT 'A',
  total              DECIMAL(10,2) NOT NULL DEFAULT 0,
  criado_em          DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pedido_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  CONSTRAINT fk_pedido_pagamento FOREIGN KEY (forma_pagamento_id) REFERENCES formas_pagamento(id),
  CONSTRAINT chk_pedido_status CHECK (status IN ('A','C','F','X'))
);

-- Itens do Pedido (Livro ou Kit)
CREATE TABLE itens_pedido (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id      INT NOT NULL,
  livro_id       INT NULL,
  kit_id         INT NULL,
  quantidade     INT NOT NULL DEFAULT 1,
  preco_unitario DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_item_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_livro  FOREIGN KEY (livro_id)  REFERENCES livros(id),
  CONSTRAINT fk_item_kit    FOREIGN KEY (kit_id)    REFERENCES kits(id),
  CONSTRAINT chk_item_um_tipo CHECK (
    (livro_id IS NOT NULL AND kit_id IS NULL) OR
    (livro_id IS NULL AND kit_id IS NOT NULL)
  )
);

-- Índices de Desempenho
CREATE INDEX idx_livros_genero      ON livros(genero_id);
CREATE INDEX idx_autorlivro_autor   ON autor_livro(autor_id);
CREATE INDEX idx_autorlivro_livro   ON autor_livro(livro_id);
CREATE INDEX idx_kitlivros_kit      ON kit_livros(kit_id);
CREATE INDEX idx_kitlivros_livro    ON kit_livros(livro_id);
CREATE INDEX idx_pedidos_cliente    ON pedidos(cliente_id);
CREATE INDEX idx_pedidos_status     ON pedidos(status);
CREATE INDEX idx_itens_pedido       ON itens_pedido(pedido_id);
CREATE INDEX idx_itens_livro        ON itens_pedido(livro_id);
CREATE INDEX idx_itens_kit          ON itens_pedido(kit_id);

-- =========================================================================
-- DADOS INICIAIS
-- =========================================================================

INSERT INTO generos (nome) VALUES
  ('Clássico Nacional'), ('Mistério'), ('Realismo Mágico'),
  ('Gótico'), ('Clássico Estrangeiro'), ('Suspense');

INSERT INTO formas_pagamento (codigo, nome) VALUES
  ('cartao', 'Cartão de Crédito'),
  ('pix',    'Pix'),
  ('boleto', 'Boleto Bancário');

INSERT INTO autores (nome) VALUES
  ('Machado de Assis'), ('Umberto Eco'), ('G. G. Márquez'), ('Mary Shelley'),
  ('Guimarães Rosa'), ('Bram Stoker'), ('Clarice Lispector'), ('Franz Kafka'),
  ('Daphne du Maurier'), ('José Saramago'), ('Antoine de Saint-Exupéry'),
  ('John Fowles'), ('Raphael Montes');

INSERT INTO livros (titulo, preco, estoque, genero_id, capa) VALUES
  ('Dom Casmurro',            42.90, 7,  (SELECT id FROM generos WHERE nome='Clássico Nacional'),   'capas/Dom-Casmurro.png'),
  ('O Nome da Rosa',          58.50, 4,  (SELECT id FROM generos WHERE nome='Mistério'),            'capas/O-nome-da-Rosa.png'),
  ('Cem Anos de Solidão',     64.00, 5,  (SELECT id FROM generos WHERE nome='Realismo Mágico'),     'capas/Cem-anos-de-Solidao.png'),
  ('Frankenstein',            39.90, 9,  (SELECT id FROM generos WHERE nome='Gótico'),              'capas/Frankenstein.png'),
  ('Grande Sertão: Veredas',  55.00, 6,  (SELECT id FROM generos WHERE nome='Clássico Nacional'),   'capas/Grande-sertao-veredas.png'),
  ('Drácula',                 36.50, 8,  (SELECT id FROM generos WHERE nome='Gótico'),              'capas/Dracula.png'),
  ('A Hora da Estrela',       33.90, 10, (SELECT id FROM generos WHERE nome='Clássico Nacional'),   'capas/A-hora-da-estrela.png'),
  ('O Processo',              41.00, 5,  (SELECT id FROM generos WHERE nome='Mistério'),            'capas/O-processo.png'),
  ('Rebecca',                 44.90, 6,  (SELECT id FROM generos WHERE nome='Gótico'),              'capas/Rebecca.png'),
  ('Ensaio sobre a Cegueira', 49.90, 4,  (SELECT id FROM generos WHERE nome='Realismo Mágico'),     'capas/Ensaio-sobre-cegueira.png'),
  ('O Pequeno Príncipe',      20.00, 12, (SELECT id FROM generos WHERE nome='Clássico Estrangeiro'), 'capas/pequeno-principe.png'),
  ('O Colecionador',          20.00, 5,  (SELECT id FROM generos WHERE nome='Suspense'),            'capas/colecionador.png'),
  ('Jantar Secreto',          20.00, 6,  (SELECT id FROM generos WHERE nome='Suspense'),            'capas/jantar-secreto.png'),
  ('Uma Família Feliz',       20.00, 6,  (SELECT id FROM generos WHERE nome='Suspense'),            'capas/familia-feliz.png'),
  ('Dias Perfeitos',          20.00, 6,  (SELECT id FROM generos WHERE nome='Suspense'),            'capas/dias-perfeitos.png');

-- Vínculos Autor <-> Livro
INSERT INTO autor_livro (autor_id, livro_id) VALUES
  ((SELECT id FROM autores WHERE nome='Machado de Assis'),        (SELECT id FROM livros WHERE titulo='Dom Casmurro')),
  ((SELECT id FROM autores WHERE nome='Umberto Eco'),             (SELECT id FROM livros WHERE titulo='O Nome da Rosa')),
  ((SELECT id FROM autores WHERE nome='G. G. Márquez'),           (SELECT id FROM livros WHERE titulo='Cem Anos de Solidão')),
  ((SELECT id FROM autores WHERE nome='Mary Shelley'),            (SELECT id FROM livros WHERE titulo='Frankenstein')),
  ((SELECT id FROM autores WHERE nome='Guimarães Rosa'),          (SELECT id FROM livros WHERE titulo='Grande Sertão: Veredas')),
  ((SELECT id FROM autores WHERE nome='Bram Stoker'),             (SELECT id FROM livros WHERE titulo='Drácula')),
  ((SELECT id FROM autores WHERE nome='Clarice Lispector'),       (SELECT id FROM livros WHERE titulo='A Hora da Estrela')),
  ((SELECT id FROM autores WHERE nome='Franz Kafka'),             (SELECT id FROM livros WHERE titulo='O Processo')),
  ((SELECT id FROM autores WHERE nome='Daphne du Maurier'),       (SELECT id FROM livros WHERE titulo='Rebecca')),
  ((SELECT id FROM autores WHERE nome='José Saramago'),           (SELECT id FROM livros WHERE titulo='Ensaio sobre a Cegueira')),
  ((SELECT id FROM autores WHERE nome='Antoine de Saint-Exupéry'),(SELECT id FROM livros WHERE titulo='O Pequeno Príncipe')),
  ((SELECT id FROM autores WHERE nome='John Fowles'),             (SELECT id FROM livros WHERE titulo='O Colecionador')),
  ((SELECT id FROM autores WHERE nome='Raphael Montes'),          (SELECT id FROM livros WHERE titulo='Jantar Secreto')),
  ((SELECT id FROM autores WHERE nome='Raphael Montes'),          (SELECT id FROM livros WHERE titulo='Uma Família Feliz')),
  ((SELECT id FROM autores WHERE nome='Raphael Montes'),          (SELECT id FROM livros WHERE titulo='Dias Perfeitos'));

-- Cliente de exemplo
INSERT INTO clientes (nome, email, senha_hash) VALUES
  ('Nicolly', 'nicolly@correio.com', '$2b$10$tY8px3mmReLR5MsSYpyRf.60QbFESGmMa2MfCNpJYhlKCtc0W34.q');

-- Kits
INSERT INTO kits (titulo, desconto_percentual) VALUES
  ('Kit Clássicos Nacionais',      12.00),
  ('Kit Terror Gótico',            10.00),
  ('Kit Suspense Raphael Montes',  15.00);

-- Vínculos Kit <-> Livro
INSERT INTO kit_livros (kit_id, livro_id) VALUES
  ((SELECT id FROM kits WHERE titulo='Kit Clássicos Nacionais'),     (SELECT id FROM livros WHERE titulo='Dom Casmurro')),
  ((SELECT id FROM kits WHERE titulo='Kit Clássicos Nacionais'),     (SELECT id FROM livros WHERE titulo='Grande Sertão: Veredas')),
  ((SELECT id FROM kits WHERE titulo='Kit Clássicos Nacionais'),     (SELECT id FROM livros WHERE titulo='A Hora da Estrela')),

  ((SELECT id FROM kits WHERE titulo='Kit Terror Gótico'),           (SELECT id FROM livros WHERE titulo='Frankenstein')),
  ((SELECT id FROM kits WHERE titulo='Kit Terror Gótico'),           (SELECT id FROM livros WHERE titulo='Drácula')),
  ((SELECT id FROM kits WHERE titulo='Kit Terror Gótico'),           (SELECT id FROM livros WHERE titulo='Rebecca')),

  ((SELECT id FROM kits WHERE titulo='Kit Suspense Raphael Montes'), (SELECT id FROM livros WHERE titulo='Jantar Secreto')),
  ((SELECT id FROM kits WHERE titulo='Kit Suspense Raphael Montes'), (SELECT id FROM livros WHERE titulo='Uma Família Feliz')),
  ((SELECT id FROM kits WHERE titulo='Kit Suspense Raphael Montes'), (SELECT id FROM livros WHERE titulo='Dias Perfeitos'));