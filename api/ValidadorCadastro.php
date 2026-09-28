<?php
/**
 * Chain of Responsibility: ValidadorCadastro 
 *
 * Antes desta refatoração, api/cadastrar.php validava tudo dentro de um único
 * `if` gigante (campos vazios + senha curta, tudo misturado numa mensagem só)
 * e fazia mais uma checagem isolada (e-mail duplicado). Eram
 * duas formas diferentes de fazer a mesma coisa: "confira uma regra, e se
 * falhar, pare"
 *
 * O Chain of Responsibility formaliza esse fluxo: cada regra de validação
 * vira um "handler" independente. Cada handler só sabe checar UMA coisa e diz
 * "passo pra frente" quando não encontra problema. Quem monta a ordem da
 * cadeia é o próprio api/cadastrar.php 
 * os handlers não sabem (nem precisam saber) quem vem antes ou depois deles
 *
 * Vantagem prática sobre o `if` gigante: para adicionar uma nova regra
 * (exemplo: exigir uma letra maiúscula na senha), só criar mais uma classe
 * e encaixar na cadeia. nenhum validador existente precisa ser tocado
 * (principio fechado aberto).
 */

abstract class ValidadorCadastro
{
    private ?ValidadorCadastro $proximo = null;

    /**
     * Encadeia o próximo validador e o devolve, para permitir chamadas
     * fluentes: $a->definirProximo($b)->definirProximo($c)
     */
    public function definirProximo(ValidadorCadastro $proximo): ValidadorCadastro
    {
        $this->proximo = $proximo;
        return $proximo;
    }

    /**
     * Roda a checagem própria deste handler; se ela passar, repassa os
     * mesmos dados para o próximo elo da cadeia. O primeiro handler que
     * encontrar um problema interrompe a cadeia e devolve a mensagem 
     * os handlers seguintes nem chegam a rodar
     */
    public function validar(array $dados): ?string
    {
        $erro = $this->checar($dados);
        if ($erro !== null) {
            return $erro;
        }
        return $this->proximo?->validar($dados);
    }

    /** Cada handler concreto só precisa implementar isso. */
    abstract protected function checar(array $dados): ?string;
}

/** Handler 1: nenhum campo pode chegar vazio */
final class ValidarCamposObrigatorios extends ValidadorCadastro
{
    protected function checar(array $dados): ?string
    {
        if (empty($dados['nome']) || empty($dados['email']) || empty($dados['senha'])) {
            return 'Preencha nome, e-mail e senha.';
        }
        return null;
    }
}

/** Handler 2: o e-mail precisa ter um formato válido */
final class ValidarFormatoEmail extends ValidadorCadastro
{
    protected function checar(array $dados): ?string
    {
        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Informe um e-mail válido.';
        }
        return null;
    }
}

/** Handler 3: senha precisa ter pelo menos 6 caracteres */
final class ValidarTamanhoSenha extends ValidadorCadastro
{
    protected function checar(array $dados): ?string
    {
        if (strlen($dados['senha']) < 6) {
            return 'A senha precisa ter pelo menos 6 caracteres.';
        }
        return null;
    }
}

/**
 * Handler 4: e-mail não pode já estar cadastrado.
 * É o único handler que depende do banco, então recebe o $pdo no construtor
 * os outros três são puros (só olham para os dados recebidos)
 */
final class ValidarEmailDuplicado extends ValidadorCadastro
{
    public function __construct(private PDO $pdo)
    {
    }

    protected function checar(array $dados): ?string
    {
        $stmt = $this->pdo->prepare('SELECT id FROM clientes WHERE email = ?');
        $stmt->execute([$dados['email']]);
        if ($stmt->fetch()) {
            return 'Já existe uma conta com esse e-mail.';
        }
        return null;
    }
}

/**
 * Monta a cadeia completa na ordem em que as checagens fazem mais sentido
 * (mais barata/genérica primeiro, consulta ao banco por último) e devolve
 * o primeiro elo, que é o único que api/cadastrar.php precisa conhecer
 */
function montarCadeiaValidacaoCadastro(PDO $pdo): ValidadorCadastro
{
    $camposObrigatorios = new ValidarCamposObrigatorios();
    $camposObrigatorios
        ->definirProximo(new ValidarFormatoEmail())
        ->definirProximo(new ValidarTamanhoSenha())
        ->definirProximo(new ValidarEmailDuplicado($pdo));

    return $camposObrigatorios;
}
