<?php

declare(strict_types=1);

require __DIR__ . '/../includes/config.php';

exigirPost();
$usuario = exigirLogin();
exigirCsrf('checkin.php');

$email = $usuario['email'];
$colaborador = buscarUsuario($email);

$satisfacao = inteiroEntre($_POST['satisfacao_empresa'] ?? null, 1, 5);
$riscoSaida = inteiroEntre($_POST['risco_saida_percebido'] ?? null, 1, 5);
$bemEstarBruto = $_POST['bem_estar_trabalho'] ?? '';
$bemEstar = $bemEstarBruto === '' ? null : inteiroEntre($bemEstarBruto, 1, 5);
$comentario = textoLimpo($_POST['comentario'] ?? '', 1000);
$idProjeto = textoLimpo($_POST['id_projeto'] ?? '', 50);
$consentimento = ($_POST['consentimento'] ?? '') === '1';

$erros = [];
if ($colaborador === null || !boolValor($colaborador['ativo'])) {
    $erros[] = 'Usuário não encontrado ou inativo.';
}
if ($satisfacao === null) {
    $erros[] = 'Selecione sua satisfação em relação à empresa (1 a 5).';
}
if ($riscoSaida === null) {
    $erros[] = 'Selecione o risco de saída percebido (1 a 5).';
}
if ($bemEstarBruto !== '' && $bemEstar === null) {
    $erros[] = 'O indicador de bem-estar deve estar entre 1 e 5.';
}
if (!$consentimento) {
    $erros[] = 'Confirme que não informará diagnósticos, dados médicos ou informações clínicas.';
}
if ($idProjeto !== '' && !in_array($idProjeto, array_column(projetosParaFormulario($email), 'id_projeto'), true)) {
    $erros[] = 'Projeto selecionado inválido.';
}

if ($erros) {
    foreach ($erros as $erro) {
        flash('erro', $erro);
    }
    redirect('checkin.php');
}

$resumo = resumoColaborador($colaborador);
$risco = calcularScoreRisco([
    'risco_saida_percebido' => $riscoSaida,
    'satisfacao_empresa' => $satisfacao,
    'bem_estar_trabalho' => $bemEstar,
    'pdi_atrasado' => in_array('Atrasado', array_column($resumo['pdis'], 'status_efetivo'), true),
    'dias_sem_atualizacao' => $resumo['dias_sem_atualizacao'],
    'meta_concluida_recente' => array_filter(
        $resumo['pdis'],
        static fn ($p) => $p['status_efetivo'] === 'Concluído' && (diasDesde($p['data_conclusao']) ?? 999) <= (int) regra('dias_meta_concluida_recente', 30)
    ) !== [],
    'percentual_medio' => $resumo['progresso_medio'],
]);

$agora = agoraIso();
$idCheckin = gerarId('CHK');

db()->inserir('Checkins_Clima', [
    'id_checkin' => $idCheckin,
    'email_colaborador' => $email,
    'id_projeto' => $idProjeto,
    'satisfacao_empresa' => $satisfacao,
    'risco_saida_percebido' => $riscoSaida,
    'bem_estar_trabalho' => $bemEstar ?? '',
    'comentario' => $comentario,
    'consentimento_confirmado' => 'sim',
    'data_checkin' => $agora,
    'score_risco' => $risco['score'],
    'classificacao_risco' => $risco['classificacao'],
]);

registrarLog('checkin_registrado', 'Checkins_Clima', $idCheckin, 'Check-in de experiência registrado (dados sensíveis omitidos do log).');

$incluirBemEstar = boolValor(regra('webhook_incluir_bem_estar', 'nao'));
$notificacoes = in_array($risco['classificacao'], ['ALTO', 'CRITICO'], true) ? ['alerta_risco_alto_rh'] : [];

enviarWebhookN8N([
    'evento' => 'checkin_clima',
    'origem' => 'pdi_connect',
    'id_checkin' => $idCheckin,
    'email_colaborador' => $email,
    'nome_colaborador' => $colaborador['nome_completo'],
    'id_projeto' => $idProjeto,
    'satisfacao_empresa' => $satisfacao,
    'risco_saida_percebido' => $riscoSaida,
    'bem_estar_trabalho' => $incluirBemEstar ? $bemEstar : null,
    'score_risco' => $risco['score'],
    'classificacao_risco' => $risco['classificacao'],
    'data_checkin' => $agora,
    'notificacoes' => $notificacoes,
    'privacidade' => [
        'bem_estar_omitido' => !$incluirBemEstar,
        'proibido_uso_em_ia' => true,
        'destinatario_bem_estar' => 'somente_rh',
    ],
]);

flash('sucesso', 'Obrigado! Seu check-in foi registrado de forma confidencial. Suas respostas ajudam a construir um ambiente de trabalho melhor.');
redirect('dashboard.php');
