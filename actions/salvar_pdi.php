<?php

declare(strict_types=1);

require __DIR__ . '/../includes/config.php';

exigirPost();
$usuario = exigirLogin();

$email = normalizarEmail((string) ($_POST['email'] ?? $usuario['email']));
$idPdi = textoLimpo($_POST['id_pdi'] ?? '', 50);
$voltar = 'pdi.php?' . http_build_query(array_filter(['id' => $idPdi, 'email' => $email !== $usuario['email'] ? $email : null]));

exigirCsrf($voltar);
exigirAcessoColaborador($email);

$percentual = inteiroEntre($_POST['percentual'] ?? null, 0, 100);
$statusNovo = (string) ($_POST['status'] ?? '');
$prazo = (string) ($_POST['prazo'] ?? '');
$dificuldade = textoLimpo($_POST['dificuldade'] ?? '', 1000);
$idProjeto = textoLimpo($_POST['id_projeto'] ?? '', 50);

$erros = [];
if ($percentual === null) {
    $erros[] = 'O percentual de conclusão deve ser um número entre 0 e 100.';
}
if (!in_array($statusNovo, STATUS_PDI, true)) {
    $erros[] = 'Selecione um status válido.';
}
if (!dataValida($prazo)) {
    $erros[] = 'Informe uma data prevista de conclusão válida.';
}

$colaborador = buscarUsuario($email);
if ($colaborador === null || !boolValor($colaborador['ativo'])) {
    $erros[] = 'O e-mail informado não corresponde a um colaborador ativo.';
}

$pdi = buscarPdi($idPdi);
if ($pdi === null || normalizarEmail($pdi['email_colaborador']) !== $email) {
    registrarLog('atualizacao_pdi', 'PDIs', $idPdi, 'PDI inexistente ou de outro colaborador.', 'negado');
    $erros[] = 'Meta de PDI não encontrada para este colaborador.';
} elseif (!boolValor($pdi['ativo'])) {
    $erros[] = 'Este PDI não está ativo.';
} elseif ($pdi['status'] === 'Concluído') {
    $erros[] = 'Esta meta já foi concluída e validada.';
}

if ($idProjeto !== '' && !in_array($idProjeto, array_column(projetosParaFormulario($email), 'id_projeto'), true)) {
    $erros[] = 'Projeto selecionado inválido.';
}

if ($erros) {
    foreach ($erros as $erro) {
        flash('erro', $erro);
    }
    redirect($voltar);
}

$avisos = [];
if ($statusNovo === 'Concluído' && !podeGerenciarMetasDe($email)) {
    $statusNovo = 'Aguardando validação';
    $avisos[] = 'A meta foi enviada para validação do gestor antes de ser concluída.';
}
if ($percentual === 100 && in_array($statusNovo, ['Em andamento', 'Atenção', 'Atrasado'], true)) {
    $statusNovo = 'Aguardando validação';
    $avisos[] = 'Meta com 100% de conclusão enviada para validação do gestor.';
}

$agora = agoraIso();
$percentualAnterior = (int) $pdi['percentual_conclusao'];
$statusAnterior = $pdi['status'];
$idAtualizacao = gerarId('ATU');

db()->inserir('Atualizacoes_PDI', [
    'id_atualizacao' => $idAtualizacao,
    'id_pdi' => $pdi['id_pdi'],
    'email_colaborador' => $email,
    'percentual_anterior' => $percentualAnterior,
    'percentual_novo' => $percentual,
    'status_anterior' => $statusAnterior,
    'status_novo' => $statusNovo,
    'dificuldade' => $dificuldade,
    'data_atualizacao' => $agora,
    'origem' => $email === $usuario['email'] ? 'formulario_pdi' : 'formulario_pdi_' . $usuario['perfil'],
    'registrado_por' => $usuario['email'],
]);

db()->atualizar('PDIs', 'id_pdi', $pdi['id_pdi'], array_filter([
    'percentual_conclusao' => $percentual,
    'status' => $statusNovo,
    'prazo' => $prazo,
    'data_ultima_atualizacao' => $agora,
    'data_conclusao' => $statusNovo === 'Concluído' ? date('Y-m-d') : null,
], static fn ($v) => $v !== null));

$descricaoLog = "Progresso {$percentualAnterior}% → {$percentual}%; status {$statusAnterior} → {$statusNovo}"
    . ($prazo !== $pdi['prazo'] ? "; prazo {$pdi['prazo']} → {$prazo}" : '')
    . ($dificuldade !== '' ? '; dificuldade registrada' : '');
registrarLog('atualizacao_pdi', 'PDIs', $pdi['id_pdi'], $descricaoLog);

$diasAtePrazo = diasAte($prazo);
$notificacoes = ['confirmacao_atualizacao_pdi'];
if ($percentual < (int) regra('limite_atencao_percentual', 50) && $statusNovo !== 'Concluído') {
    $notificacoes[] = 'lembrete_pdi_abaixo_50';
}
if ($diasAtePrazo !== null && $diasAtePrazo >= 0 && $diasAtePrazo <= (int) regra('dias_prazo_proximo', 7) && $statusNovo !== 'Concluído') {
    $notificacoes[] = 'aviso_prazo_proximo';
}
if ($statusNovo === 'Atrasado' || ($diasAtePrazo !== null && $diasAtePrazo < 0 && !in_array($statusNovo, ['Concluído', 'Aguardando validação'], true))) {
    $notificacoes[] = 'alerta_pdi_atrasado';
}
if ($statusNovo === 'Aguardando validação') {
    $notificacoes[] = 'solicitacao_validacao_gestor';
}
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
    'prazo' => $prazo,
    'dificuldade' => $dificuldade,
    'data_atualizacao' => $agora,
    'id_atualizacao' => $idAtualizacao,
    'id_projeto' => $idProjeto,
    'registrado_por' => $usuario['email'],
    'notificacoes' => $notificacoes,
]);

foreach ($avisos as $aviso) {
    flash('info', $aviso);
}
flash('sucesso', 'Atualização do PDI salva com sucesso!');

redirect($email === $usuario['email'] ? 'dashboard.php' : $voltar);
