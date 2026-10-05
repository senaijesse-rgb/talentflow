<?php

declare(strict_types=1);

require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/mentoria.php';

exigirPost();
exigirCsrf('dashboard.php#mentoria');

$usuario = exigirLogin();
$email = $usuario['email'];
$idPdi = trim((string) ($_POST['id_pdi'] ?? ''));
$dificuldade = trim((string) ($_POST['dificuldade'] ?? ''));

if ($idPdi === '' || mb_strlen($dificuldade) < 8 || mb_strlen($dificuldade) > 1000) {
    flash('erro', 'Escolha uma meta e descreva a dificuldade com pelo menos 8 caracteres.');
    redirect('dashboard.php#mentoria');
}

$pdi = buscarPdi($idPdi);
$permitidas = array_column(metasParaMentoria($email), 'id_pdi');

if ($pdi === null || !in_array($pdi['id_pdi'], $permitidas, true)) {
    registrarLog('mentoria_negada', 'PDIs', $idPdi, 'Tentativa de usar a MentorIA fora das próprias metas em aberto.', 'negado');
    flash('erro', 'A MentorIA só pode ser usada nas suas próprias metas em aberto.');
    redirect('dashboard.php#mentoria');
}

$plano = gerarPlanoMentoria($pdi, $dificuldade, projetoAtualMentoria($email));
registrarMentoriaSessao([
    'id_pdi' => $pdi['id_pdi'],
    'meta' => (string) $pdi['meta'],
    'dificuldade' => $dificuldade,
    'data' => date('Y-m-d H:i:s'),
    'mensagem' => $plano['mensagem'],
    'passos' => $plano['passos'],
    'reflexao' => $plano['reflexao'],
    'aviso' => $plano['aviso'],
]);

registrarLog('mentoria', 'PDIs', $pdi['id_pdi'], 'Conteúdo privado', 'sucesso');
flash('sucesso', 'Plano de ação gerado. Ele fica nesta sessão, só para você.');
redirect('dashboard.php#mentoria');
