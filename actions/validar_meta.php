<?php

declare(strict_types=1);

require __DIR__ . '/../includes/config.php';

exigirPost();
$usuario = exigirLogin();

$email = normalizarEmail((string) ($_POST['email'] ?? ''));
$idPdi = textoLimpo($_POST['id_pdi'] ?? '', 50);
$decisao = (string) ($_POST['decisao'] ?? '');
$voltar = 'perfil.php?email=' . rawurlencode($email) . '#pdis';

exigirCsrf($voltar);
exigirAcessoColaborador($email);

if (!podeGerenciarMetasDe($email)) {
    negarAcesso('Tentativa de validar meta sem ser gestor responsável ou RH.', 'PDIs', $idPdi);
}

if (!in_array($decisao, ['concluir', 'devolver'], true)) {
    flash('erro', 'Decisão de validação inválida.');
    redirect($voltar);
}

$colaborador = buscarUsuario($email);
$pdi = buscarPdi($idPdi);

if ($colaborador === null || !boolValor($colaborador['ativo'])) {
    flash('erro', 'Colaborador não encontrado ou inativo.');
    redirect($voltar);
}

if ($pdi === null || normalizarEmail($pdi['email_colaborador']) !== $email || !boolValor($pdi['ativo'])) {
    registrarLog('validacao_meta', 'PDIs', $idPdi, 'PDI inexistente ou de outro colaborador.', 'negado');
    flash('erro', 'Meta de PDI não encontrada para este colaborador.');
    redirect($voltar);
}

if ($pdi['status'] !== 'Aguardando validação') {
    flash('erro', 'Somente metas aguardando validação podem ser decididas.');
    redirect($voltar);
}

$statusNovo = $decisao === 'concluir' ? 'Concluído' : 'Em andamento';
$percentualAnterior = (int) $pdi['percentual_conclusao'];
$percentual = $decisao === 'concluir' ? 100 : $percentualAnterior;
$agora = agoraIso();
$idAtualizacao = gerarId('ATU');

db()->inserir('Atualizacoes_PDI', [
    'id_atualizacao' => $idAtualizacao,
    'id_pdi' => $pdi['id_pdi'],
    'email_colaborador' => $email,
    'percentual_anterior' => $percentualAnterior,
    'percentual_novo' => $percentual,
    'status_anterior' => $pdi['status'],
    'status_novo' => $statusNovo,
    'dificuldade' => '',
    'data_atualizacao' => $agora,
    'origem' => 'validacao_' . $usuario['perfil'],
    'registrado_por' => $usuario['email'],
]);

db()->atualizar('PDIs', 'id_pdi', $pdi['id_pdi'], [
    'percentual_conclusao' => $percentual,
    'status' => $statusNovo,
    'data_ultima_atualizacao' => $agora,
    'data_conclusao' => $statusNovo === 'Concluído' ? date('Y-m-d') : '',
]);

registrarLog(
    'validacao_meta',
    'PDIs',
    $pdi['id_pdi'],
    'Status ' . $pdi['status'] . ' → ' . $statusNovo . ' por ' . $usuario['email'] . '.'
);

$notificacoes = ['confirmacao_atualizacao_pdi'];
if ($statusNovo === 'Concluído') {
    $notificacoes[] = 'aviso_meta_concluida';
}

enviarWebhookN8N([
    'evento' => 'atualizacao_pdi',
    'origem' => 'pdi_connect',
    'id_pdi' => $pdi['id_pdi'],
    'email_colaborador' => $email,
    'nome_colaborador' => $colaborador['nome_completo'],
    'gestor_email' => normalizarEmail($pdi['gestor_email'] ?: $colaborador['gestor_email']),
    'competencia' => $pdi['competencia'],
    'meta' => $pdi['meta'],
    'percentual_anterior' => $percentualAnterior,
    'percentual_novo' => $percentual,
    'status' => $statusNovo,
    'prazo' => $pdi['prazo'],
    'dificuldade' => '',
    'data_atualizacao' => $agora,
    'id_atualizacao' => $idAtualizacao,
    'id_projeto' => '',
    'registrado_por' => $usuario['email'],
    'notificacoes' => $notificacoes,
]);

flash('sucesso', $statusNovo === 'Concluído' ? 'Meta validada como concluída.' : 'Meta devolvida para ajuste.');
redirect($voltar);
