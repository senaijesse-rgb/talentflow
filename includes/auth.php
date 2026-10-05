<?php

declare(strict_types=1);

const LOGIN_MAX_TENTATIVAS = 5;
const LOGIN_JANELA_SEGUNDOS = 900;

function usuarioAtual(): ?array
{
    $usuario = $_SESSION['usuario'] ?? null;

    return is_array($usuario) ? $usuario : null;
}

function dadosSessao(array $registro): array
{
    return [
        'id' => $registro['id_usuario'],
        'email' => normalizarEmail($registro['email']),
        'nome' => $registro['nome_completo'],
        'perfil' => $registro['perfil'],
        'cargo' => $registro['cargo'],
        'area' => $registro['area'],
        'gestor_email' => normalizarEmail($registro['gestor_email']),
    ];
}

/** Garante sessão válida; revalida periodicamente se o usuário continua ativo e com o mesmo perfil. */
function exigirLogin(): array
{
    $usuario = usuarioAtual();
    if ($usuario === null) {
        if (requisicaoApi()) {
            responderJson(['erro' => 'Não autenticado.'], 401);
        }
        flash('aviso', 'Faça login para continuar.');
        redirect('login.php');
    }

    $limite = !empty($_SESSION['lembrar']) ? SESSION_REMEMBER_LIFETIME : SESSION_IDLE_TIMEOUT;
    if (time() - (int) ($_SESSION['ultimo_acesso'] ?? 0) > $limite) {
        registrarLog('sessao_expirada', 'Sessao', $usuario['email'], 'Sessão encerrada por inatividade.', 'sucesso', $usuario);
        reiniciarSessao();
        flash('aviso', 'Sua sessão expirou por inatividade. Entre novamente.');
        redirect('login.php');
    }

    if (time() - (int) ($_SESSION['revalidado_em'] ?? 0) > 300) {
        $registro = buscarUsuario($usuario['email']);
        if ($registro === null || !boolValor($registro['ativo']) || !array_key_exists($registro['perfil'], PERFIS)) {
            registrarLog('sessao_revogada', 'Usuarios', $usuario['email'], 'Usuário inativo ou removido durante a sessão.', 'negado', $usuario);
            reiniciarSessao();
            flash('erro', 'Seu acesso não está mais ativo. Procure o Administrador RH.');
            redirect('login.php');
        }
        $_SESSION['usuario'] = $usuario = dadosSessao($registro);
        $_SESSION['revalidado_em'] = time();
    }

    $_SESSION['ultimo_acesso'] = time();

    return $usuario;
}

/**
 * Valida e-mail e senha contra a aba Usuarios.
 * Outros provedores (ex.: Google Workspace) devem resolver o registro do usuário
 * e chamar iniciarSessaoUsuario() com o método correspondente.
 */
function autenticarComSenha(string $email, string $senha): array
{
    $registro = buscarUsuario($email);
    $hash = (string) ($registro['senha_hash'] ?? '');
    $contexto = ['email' => $email, 'perfil' => ''];

    if ($registro === null || $hash === '') {
        password_verify($senha, password_hash('pdi-connect-tempo-constante', PASSWORD_DEFAULT));
        registrarLog('login_falha', 'Usuarios', $email, 'Credenciais inválidas.', 'negado', $contexto);

        return ['ok' => false, 'erro' => 'E-mail ou senha inválidos.'];
    }

    if (!password_verify($senha, $hash)) {
        registrarLog('login_falha', 'Usuarios', $email, 'Credenciais inválidas.', 'negado', $contexto);

        return ['ok' => false, 'erro' => 'E-mail ou senha inválidos.'];
    }

    if (!boolValor($registro['ativo'])) {
        registrarLog('login_inativo', 'Usuarios', $email, 'Tentativa de login com usuário inativo.', 'negado', $contexto);

        return ['ok' => false, 'erro' => 'Seu acesso está inativo. Procure o Administrador RH.'];
    }

    if (!array_key_exists($registro['perfil'], PERFIS)) {
        registrarLog('login_perfil_invalido', 'Usuarios', $email, 'Perfil não reconhecido: ' . $registro['perfil'], 'negado', $contexto);

        return ['ok' => false, 'erro' => 'Seu perfil de acesso não está configurado. Procure o Administrador RH.'];
    }

    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        db()->atualizar('Usuarios', 'email', $email, ['senha_hash' => password_hash($senha, PASSWORD_DEFAULT)]);
    }

    return ['ok' => true, 'usuario' => $registro];
}

function iniciarSessaoUsuario(array $registro, bool $lembrar = false, string $metodo = 'senha'): void
{
    session_regenerate_id(true);

    $_SESSION['usuario'] = dadosSessao($registro);
    $_SESSION['lembrar'] = $lembrar;
    $_SESSION['metodo_login'] = $metodo;
    $_SESSION['ultimo_acesso'] = time();
    $_SESSION['revalidado_em'] = time();
    unset($_SESSION['csrf_token']);

    $parametros = session_get_cookie_params();
    $opcoesCookie = ['path' => $parametros['path'], 'secure' => IS_HTTPS, 'httponly' => true, 'samesite' => 'Lax'];

    if ($lembrar) {
        setcookie(session_name(), session_id(), $opcoesCookie + ['expires' => time() + SESSION_REMEMBER_LIFETIME]);
        setcookie('pdi_email', $_SESSION['usuario']['email'], $opcoesCookie + ['expires' => time() + 30 * 86400]);
    } else {
        setcookie('pdi_email', '', $opcoesCookie + ['expires' => time() - 3600]);
    }

    db()->atualizar('Usuarios', 'email', $registro['email'], ['ultimo_login' => agoraIso()]);
    registrarLog('login', 'Usuarios', $registro['email'], "Login realizado via {$metodo}.", 'sucesso', $_SESSION['usuario']);
}

function encerrarSessao(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $p['path'],
            'domain' => $p['domain'],
            'secure' => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Lax',
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/** Encerra a sessão atual e abre uma nova (limpa), útil para exibir mensagens após logout. */
function reiniciarSessao(): void
{
    encerrarSessao();
    session_start();
    session_regenerate_id(true);
}

function rotaInicialPorPerfil(string $perfil): string
{
    return match ($perfil) {
        'administrador' => 'dashboard.php#visao-rh',
        'gestor' => 'dashboard.php#minha-equipe',
        default => 'dashboard.php',
    };
}

/* Limite de tentativas de login por IP + e-mail, persistido em arquivo. */

function arquivoTentativasLogin(string $email): string
{
    return diretorioStorage() . '/login_' . hash('sha256', ipCliente() . '|' . $email) . '.json';
}

function loginBloqueado(string $email): bool
{
    $arquivo = arquivoTentativasLogin($email);
    if (!is_file($arquivo)) {
        return false;
    }

    $dados = json_decode((string) file_get_contents($arquivo), true) ?: [];
    if (time() - (int) ($dados['inicio'] ?? 0) > LOGIN_JANELA_SEGUNDOS) {
        @unlink($arquivo);

        return false;
    }

    return (int) ($dados['tentativas'] ?? 0) >= LOGIN_MAX_TENTATIVAS;
}

function registrarFalhaLogin(string $email): void
{
    $arquivo = arquivoTentativasLogin($email);
    $dados = is_file($arquivo) ? (json_decode((string) file_get_contents($arquivo), true) ?: []) : [];

    if (time() - (int) ($dados['inicio'] ?? 0) > LOGIN_JANELA_SEGUNDOS) {
        $dados = ['inicio' => time(), 'tentativas' => 0];
    }
    $dados['tentativas'] = (int) $dados['tentativas'] + 1;

    file_put_contents($arquivo, json_encode($dados), LOCK_EX);
}

function limparFalhasLogin(string $email): void
{
    @unlink(arquivoTentativasLogin($email));
}
