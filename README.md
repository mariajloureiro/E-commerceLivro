# 📚 E-Commerce de Livros (Epílogo)

Um projeto de plataforma de comércio eletrônico de livros desenvolvido com **HTML, CSS, JavaScript, PHP e MySQL**, aplicando os padrões de projeto **Strategy**, **Observer**, **State** e **Composite**.

---

## 📌 Visão Geral do Projeto

O sistema simula uma livraria virtual completa, gerenciando a navegação do catálogo, o fluxo de compras e a gestão de usuários com autenticação de sessão. O foco principal da arquitetura é a aplicação de **Padrões de Projeto GoF (Gang of Four)** para manter a solução legível e desacoplada.

---

## 🧠 Padrões de Projeto Utilizados

### 1. 🎯 Strategy Pattern (Padrão Estratégia)
* **Cálculo de Frete:** Define estratégias intercambiáveis (ex: *PAC*, *Sedex*, *Frete Grátis*) sem poluir a regra principal com condicionais encadeadas (`if/else`).
* **Processamento de Pagamento:** Algoritmos específicos para validar e executar pagamentos em diferentes modalidades (*Cartão*, *Pix*, *Boleto*).

### 2. 👁️ Observer Pattern (Padrão Observador)
* **Gestão de Eventos e Carrinho:** Notificação automática para atualização dinâmica de componentes da interface (contador do carrinho, cálculo de totais e status de pedidos) quando o estado do pedido é alterado.

### 3. 🔄 State Pattern (Padrão Estado)
* **Ciclo de vida do Pedido:** o `Pedido` delega para um objeto `EstadoPedido` (`EstadoAberto`, `EstadoConfirmado`, `EstadoFalhou`, `EstadoCancelado`) a decisão sobre o que é permitido fazer em cada momento — adicionar item, finalizar ou cancelar. Isso substitui um campo `status: String` cheio de `if/else` espalhados pelo código por uma classe por estado.
* Persistido no banco na coluna `pedidos.status` e replicado no back-end (`api/pedido.php` e `api/cancelar_pedido.php`), que aplicam as mesmas regras de transição.

### 4. 🧩 Composite Pattern (Padrão Composto)
* **Catálogo (Livro + Kit):** `Livro` (folha) e `KitDeLivros` (composto) implementam a mesma interface `ItemCatalogo`. Um `KitDeLivros` agrupa vários livros e aplica um desconto sobre a soma dos preços, mas para o carrinho/checkout se comporta exatamente como um item único.
* Persistido no banco pelas tabelas `kits` e `kit_livros`, e resolvido em seus componentes por `api/pedido.php` (baixa de estoque) e `api/cancelar_pedido.php` (devolução de estoque).

---

## 🗄️ Correções de modelagem (revisão da professora)

* **Sem `cor1`/`cor2` no banco:** essas colunas só existiam para colorir o card de um livro/kit sem capa. Passaram a ser **calculadas no front-end** (`gerarCorDoTexto()`, a partir do título) em vez de ocupar espaço em `livros` e `kits`.
* **`capa` foi mantida:** é o caminho real da imagem de capa (string curta, ~30 bytes), efetivamente usada para exibir a arte do livro — diferente de `cor1`/`cor2`, que eram um valor decorativo redundante.
* **Autoria normalizada:** a coluna `livros.autor` (texto solto) virou as tabelas `autores` e `autor_livro` (relação N:N — um livro pode ter mais de um autor). `api/livros.php` junta os nomes com `GROUP_CONCAT`, então o front-end continua recebendo `autor` como uma string, sem precisar mudar.
* **`pedidos.status` compacto:** deixou de guardar a palavra inteira (`"confirmado"`, `"cancelado"`...) e agora guarda **1 caractere** (`CHAR(1)`): `A` = aberto, `C` = confirmado, `F` = falhou, `X` = cancelado — o mesmo código usado pelo padrão State no JS (`EstadoAberto`, `EstadoConfirmado`, `EstadoFalhou`, `EstadoCancelado`), só que em 1 byte por linha.

---

## 🛠️ Tecnologias Utilizadas

| Camada | Tecnologias |
| :--- | :--- |
| **Front-end** | HTML5, CSS3, JavaScript, PHP |
| **Back-end / API** | PHP |
| **Banco de Dados** | MySQL |
| **Design Patterns** | Strategy Pattern, Observer Pattern, State Pattern, Composite Pattern |

---

## 📁 Estrutura de Pastas

```text
.
├── api/                     # Endpoints PHP para comunicação do sistema
│   ├── cadastrar.php        # Processa o cadastro de novos usuários
│   ├── cancelar_pedido.php  # Cancela um pedido (padrão State) e devolve o estoque
│   ├── livros.php           # Gerencia a busca do catálogo (livros + kits)
│   ├── login.php            # Realiza a autenticação de usuários
│   ├── logout.php           # Encerra a sessão do usuário
│   ├── pedido.php           # Processa os pedidos e regras de negócio (Composite: livro ou kit)
│   └── sessao.php           # Valida o estado das sessões ativas
├── capas/                   # Imagens das capas dos livros do catálogo
├── Diagrama/                # Diagrama de classes (.drawio) atualizado com os 4 padrões
├── config.php               # Configuração de conexão com o MySQL
├── epilogo_banco.sql        # Script de criação do banco de dados MySQL (livros, kits, pedidos, estados)
├── epilogo.html             # Interface principal da aplicação (Livraria)
├── login.html                # Interface para autenticação e cadastro de usuários
└── README.md                 # Documentação do projeto
```
