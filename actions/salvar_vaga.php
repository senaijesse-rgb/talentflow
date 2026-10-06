<?php

declare(strict_types=1);

require __DIR__ . '/../includes/config.php';

exigirPost();
$usuario = exigirPerfil('administrador');
$voltar = 'vagas.php';
exigirCsrf($voltar);

$operacao = (string) ($_POST['operacao'] ?? 'publicar');

if ($operacao === 'encerrar') {
    $id = textoLimpo($_POST['id_vaga'] ?? '', 50);
    $vaga = $id === '' ? null : db()->buscarUm('Vagas_Internas', 'id_vaga', $id);
    if ($vaga === null) {
        flash('erro', 'Vaga não encontrada.');
        redirect($voltar);
    }
    db()->atualizar('Vagas_Internas', 'id_vaga', $id, ['status' => 'Encerrada']);
    registrarLog('vaga_encerrada', 'Vagas_Internas', $id, $vaga['titulo']);
    flash('sucesso', 'Vaga encerrada.');
    redirect($voltar);
}

$titulo = textoLimpo($_POST['titulo'] ?? '', 120);
$area = textoLimpo($_POST['area'] ?? '', 80);
$descricao = textoLimpo($_POST['descricao'] ?? '', 1000);
$requisitos = textoLimpo($_POST['requisitos'] ?? '', 500);

$erros = [];
if (mb_strlen($titulo) < 5) {
    $erros[] = 'Informe o título da vaga.';
}
if (mb_strlen($area) < 2) {
    $erros[] = 'Informe a área da vaga.';
}
if (mb_strlen($descricao) < 10) {
    $erros[] = 'Descreva a vaga com pelo menos 10 caracteres.';
}
if ($erros) {
    foreach ($erros as $erro) {
        flash('erro', $erro);
    }
    redirect($voltar);
}

$id = gerarId('VAG');
db()->inserir('Vagas_Internas', [
    'id_vaga' => $id,
    'titulo' => $titulo,
    'area' => $area,
    'descricao' => $descricao,
    'requisitos' => $requisitos,
    'status' => 'Aberta',
    'publicado_por' => $usuario['email'],
    'data_publicacao' => agoraIso(),
]);

registrarLog('vaga_publicada', 'Vagas_Internas', $id, $titulo);
flash('sucesso', 'Vaga interna publicada.');
redirect($voltar);
