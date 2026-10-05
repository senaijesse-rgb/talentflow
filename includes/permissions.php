<?php

declare(strict_types=1);

const PERFIS = [
    'colaborador' => 'Colaborador',
    'gestor' => 'Gestor',
    'administrador' => 'Administrador RH',
];

function rotuloPerfil(string $perfil): string
{
    return PERFIS[$perfil] ?? 'Desconhecido';
}

function ehAdmin(?array $usuario = null): bool
{
    return (($usuario ?? usuarioAtual())['perfil'] ?? '') === 'administrador';
}

function ehGestor(?array $usuario = null): bool
{
    return (($usuario ?? usuarioAtual())['perfil'] ?? '') === 'gestor';
}

function exigirPerfil(string ...$perfis): array
{
    $usuario = exigirLogin();
    if (!in_array($usuario['perfil'], $perfis, true)) {
        negarAcesso('Perfil ' . $usuario['perfil'] . ' sem permissão para ' . basename($_SERVER['SCRIPT_NAME'] ?? ''));
    }

    return $usuario;
}

/** Colaborador: apenas a si. Gestor: a si e à própria equipe. Administrador RH: todos. */
function podeAcessarColaborador(string $emailAlvo, ?array $usuario = null): bool
{
    $usuario ??= usuarioAtual();
    if ($usuario === null) {
        return false;
    }

    $alvo = normalizarEmail($emailAlvo);
    if ($usuario['perfil'] === 'administrador' || $alvo === $usuario['email']) {
        return true;
    }

    if ($usuario['perfil'] === 'gestor') {
        $colaborador = buscarUsuario($alvo);

        return $colaborador !== null && normalizarEmail($colaborador['gestor_email']) === $usuario['email'];
    }

    return false;
}

function exigirAcessoColaborador(string $emailAlvo): void
{
    if (!podeAcessarColaborador($emailAlvo)) {
        negarAcesso('Tentativa de acesso a dados de colaborador não autorizado.', 'Usuarios', normalizarEmail($emailAlvo));
    }
}

/** Dado individual de bem-estar: exclusivo do Administrador RH. */
function podeVerBemEstarIndividual(?array $usuario = null): bool
{
    return ehAdmin($usuario);
}

function podeVerSatisfacaoIndividual(string $emailAlvo, ?array $usuario = null): bool
{
    $usuario ??= usuarioAtual();
    if ($usuario === null) {
        return false;
    }

    if ($usuario['perfil'] === 'administrador' || normalizarEmail($emailAlvo) === $usuario['email']) {
        return true;
    }

    return $usuario['perfil'] === 'gestor'
        && podeAcessarColaborador($emailAlvo, $usuario)
        && boolValor(regra('politica_exibir_satisfacao_gestor', 'nao'));
}

/** Gestor responsável pelo colaborador ou Administrador RH. */
function podeGerenciarMetasDe(string $emailColaborador, ?array $usuario = null): bool
{
    $usuario ??= usuarioAtual();
    if ($usuario === null || (normalizarEmail($emailColaborador) === $usuario['email'] && $usuario['perfil'] !== 'administrador')) {
        return false;
    }

    return $usuario['perfil'] === 'administrador'
        || ($usuario['perfil'] === 'gestor' && podeAcessarColaborador($emailColaborador, $usuario));
}

/** Remove de um check-in os campos que o usuário atual não pode visualizar. */
function filtrarCheckinParaUsuario(array $checkin, ?array $usuario = null): array
{
    if (!podeVerBemEstarIndividual($usuario)) {
        unset($checkin['bem_estar_trabalho']);
    }

    if (!podeVerSatisfacaoIndividual($checkin['email_colaborador'] ?? '', $usuario)) {
        unset($checkin['satisfacao_empresa'], $checkin['risco_saida_percebido'], $checkin['comentario']);
    }

    if (!ehAdmin($usuario)) {
        unset($checkin['score_risco']);
    }

    return $checkin;
}

function negarAcesso(string $motivo, string $entidade = 'Sistema', string $idEntidade = ''): never
{
    registrarLog('acesso_negado', $entidade, $idEntidade, $motivo, 'negado');

    if (requisicaoApi()) {
        responderJson(['erro' => 'Acesso negado.'], 403);
    }

    renderizarPaginaErro(403, 'Acesso negado', 'Você não tem permissão para acessar este conteúdo. A tentativa foi registrada.');
}

function itensMenu(string $perfil): array
{
    $itens = [
        ['arquivo' => 'dashboard.php', 'rotulo' => 'Dashboard', 'icone' => 'home', 'perfis' => ['colaborador', 'gestor', 'administrador']],
        ['arquivo' => 'dashboard.php#mentoria', 'rotulo' => 'MentorIA', 'icone' => 'chat', 'perfis' => ['colaborador', 'gestor', 'administrador']],
        ['arquivo' => 'equipe.php', 'rotulo' => 'Minha equipe', 'icone' => 'equipe', 'perfis' => ['gestor']],
        ['arquivo' => 'admin.php', 'rotulo' => 'Administração', 'icone' => 'ajustes', 'perfis' => ['administrador']],
        ['arquivo' => 'relatorios.php', 'rotulo' => 'Relatórios', 'icone' => 'download', 'perfis' => ['administrador']],
        ['arquivo' => 'pdi.php', 'rotulo' => $perfil === 'colaborador' ? 'Atualizar meu PDI' : 'Meu PDI', 'icone' => 'prancheta', 'perfis' => ['colaborador', 'gestor']],
        ['arquivo' => 'checkin.php', 'rotulo' => 'Check-in de experiência', 'icone' => 'sorriso', 'perfis' => ['colaborador', 'gestor', 'administrador']],
        ['arquivo' => 'perfil.php', 'rotulo' => 'Meu perfil', 'icone' => 'usuario', 'perfis' => ['colaborador', 'gestor', 'administrador']],
    ];

    return array_values(array_filter($itens, static fn ($i) => in_array($perfil, $i['perfis'], true)));
}
