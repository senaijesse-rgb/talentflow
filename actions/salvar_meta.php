<?php

declare(strict_types=1);

require __DIR__ . '/../includes/config.php';

exigirPost();
$usuario = exigirLogin();
$voltar = 'metas.php';
exigirCsrf($voltar);

$competencia = textoLimpo($_POST['competencia'] ?? '', 80);
$meta = textoLimpo($_POST['meta'] ?? '', 400);
$prazo = (string) ($_POST['prazo'] ?? '');
$hoje = (new DateTimeImmutable('today'))->format('Y-m-d');
$limite = (new DateTimeImmutable('today'))->modify('+2 years')->format('Y-m-d');

$erros = [];
if (mb_strlen($competencia) < 2) {
    $erros[] = 'Informe a competência da meta.';
}
if (mb_strlen($meta) < 8) {
    $erros[] = 'Descreva a meta com pelo menos 8 caracteres.';
}
if (!dataValida($prazo) || $prazo < $hoje || $prazo > $limite) {
    $erros[] = 'Informe um prazo entre hoje e os próximos dois anos.';
}

$colaborador = buscarUsuario($usuario['email']);
if ($colaborador === null || !boolValor($colaborador['ativo'])) {
    $erros[] = 'Seu cadastro não está ativo.';
}

if ($erros) {
    foreach ($erros as $erro) {
        flash('erro', $erro);
    }
    redirect($voltar);
}

$id = gerarId('PDI');
$agora = agoraIso();
db()->inserir('PDIs', [
    'id_pdi' => $id,
    'email_colaborador' => $usuario['email'],
    'competencia' => $competencia,
    'meta' => $meta,
    'percentual_conclusao' => 0,
    'status' => 'Em andamento',
    'prazo' => $prazo,
    'data_inicio' => $hoje,
    'data_ultima_atualizacao' => $agora,
    'data_conclusao' => '',
    'gestor_email' => normalizarEmail($colaborador['gestor_email'] ?? ''),
    'ativo' => 'sim',
]);

registrarLog('nova_meta', 'PDIs', $id, 'Meta criada pelo próprio colaborador: ' . resumirTexto($meta, 80));
flash('sucesso', 'Meta adicionada ao seu PDI.');
redirect($voltar);
