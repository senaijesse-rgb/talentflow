<?php

declare(strict_types=1);

require __DIR__ . '/../includes/config.php';

exigirPost();
$usuario = exigirLogin();
$voltar = 'skills.php';
exigirCsrf($voltar);

$nome = textoLimpo($_POST['nome'] ?? '', 80);
$nivel = (string) ($_POST['nivel'] ?? '');
$evidencia = textoLimpo($_POST['evidencia'] ?? '', 500);
$niveis = ['Básico', 'Intermediário', 'Avançado'];

$erros = [];
if (mb_strlen($nome) < 2) {
    $erros[] = 'Informe o nome da skill.';
}
if (!in_array($nivel, $niveis, true)) {
    $erros[] = 'Selecione o nível da skill.';
}

$jaTem = false;
foreach (db()->buscar('Skills_Colaborador', 'email_colaborador', $usuario['email']) as $skill) {
    if (mb_strtolower($skill['nome']) === mb_strtolower($nome) && $skill['nivel'] === $nivel) {
        $jaTem = true;
    }
}
if ($jaTem) {
    $erros[] = 'Você já registrou esta skill neste nível.';
}

if ($erros) {
    foreach ($erros as $erro) {
        flash('erro', $erro);
    }
    redirect($voltar);
}

$id = gerarId('SKL');
db()->inserir('Skills_Colaborador', [
    'id_skill' => $id,
    'email_colaborador' => $usuario['email'],
    'nome' => $nome,
    'nivel' => $nivel,
    'evidencia' => $evidencia,
    'data_registro' => agoraIso(),
]);

registrarLog('nova_skill', 'Skills_Colaborador', $id, $nome . ' (' . $nivel . ')');
flash('sucesso', 'Skill adicionada.');
redirect($voltar);
