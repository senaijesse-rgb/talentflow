<?php

declare(strict_types=1);

require __DIR__ . '/../includes/config.php';

exigirPost();
$usuario = exigirLogin();
$voltar = 'vagas.php';
exigirCsrf($voltar);

$idVaga = textoLimpo($_POST['id_vaga'] ?? '', 50);
$mensagem = textoLimpo($_POST['mensagem'] ?? '', 500);
$vaga = $idVaga === '' ? null : db()->buscarUm('Vagas_Internas', 'id_vaga', $idVaga);

if ($vaga === null || ($vaga['status'] ?? '') !== 'Aberta') {
    flash('erro', 'Esta vaga não está aberta.');
    redirect($voltar);
}

foreach (db()->buscar('Candidaturas_Vaga', 'id_vaga', $idVaga) as $candidatura) {
    if (normalizarEmail($candidatura['email_colaborador']) === $usuario['email']) {
        flash('info', 'Você já demonstrou interesse nesta vaga.');
        redirect($voltar);
    }
}

$id = gerarId('CAN');
db()->inserir('Candidaturas_Vaga', [
    'id_candidatura' => $id,
    'id_vaga' => $idVaga,
    'email_colaborador' => $usuario['email'],
    'mensagem' => $mensagem,
    'data_candidatura' => agoraIso(),
]);

registrarLog('candidatura_vaga', 'Candidaturas_Vaga', $id, $vaga['titulo']);
flash('sucesso', 'Interesse registrado. O RH vê a sua candidatura.');
redirect($voltar);
