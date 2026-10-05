<?php

declare(strict_types=1);

require __DIR__ . '/../includes/config.php';

exigirPost();
$usuario = exigirLogin();

$email = normalizarEmail((string) ($_POST['email'] ?? ''));
$voltar = 'perfil.php?email=' . rawurlencode($email) . '#comentarios';

exigirCsrf($voltar);
exigirAcessoColaborador($email);

if (!podeGerenciarMetasDe($email)) {
    negarAcesso('Tentativa de comentar sem ser gestor responsável ou RH.', 'Comentarios_Gestor', $email);
}

$comentario = textoLimpo($_POST['comentario'] ?? '', 1000);
$tipo = (string) ($_POST['tipo_comentario'] ?? '');
$tipos = ['Feedback', 'Acompanhamento', 'Reconhecimento'];
$visivel = (($_POST['visivel_colaborador'] ?? '') === 'sim') ? 'sim' : 'nao';

$erros = [];
if ($comentario === '') {
    $erros[] = 'Escreva o comentário antes de publicar.';
}
if (!in_array($tipo, $tipos, true)) {
    $erros[] = 'Selecione um tipo de comentário válido.';
}

$colaborador = buscarUsuario($email);
if ($colaborador === null || !boolValor($colaborador['ativo'])) {
    $erros[] = 'Colaborador não encontrado ou inativo.';
}

if ($erros) {
    foreach ($erros as $erro) {
        flash('erro', $erro);
    }
    redirect($voltar);
}

$id = gerarId('COM');
db()->inserir('Comentarios_Gestor', [
    'id_comentario' => $id,
    'email_colaborador' => $email,
    'gestor_email' => $usuario['email'],
    'comentario' => $comentario,
    'tipo_comentario' => $tipo,
    'data_comentario' => agoraIso(),
    'visivel_colaborador' => $visivel,
]);

registrarLog('comentario_gestor', 'Comentarios_Gestor', $id, 'Comentário de ' . $tipo . ' registrado para ' . $email . '.');
flash('sucesso', 'Comentário publicado.');
redirect($voltar);
