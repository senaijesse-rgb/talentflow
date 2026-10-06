<?php

declare(strict_types=1);

require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/talentflow_ponte.php';

exigirPost();
$usuario = usuarioAtual();
if ($usuario === null) {
    responderJson(['erro' => 'Faça login no PDI Connect para abrir o TalentFlow.'], 401);
}

$sessao = abrirSessaoTalentFlow($usuario);
if (isset($sessao['erro'])) {
    responderJson(['erro' => $sessao['erro']], (int) ($sessao['status'] ?? 502));
}

registrarLog('talentflow_acesso', 'TF_Sessoes', $usuario['email'], 'Sessão do TalentFlow aberta a partir do PDI Connect.');
responderJson($sessao);
