<?php

declare(strict_types=1);

require __DIR__ . '/../includes/config.php';

exigirPost();
exigirCsrf('login.php');

$email = normalizarEmail((string) ($_POST['email'] ?? ''));
$senha = (string) ($_POST['senha'] ?? '');
$lembrar = ($_POST['lembrar'] ?? '') === '1';

$_SESSION['login_email'] = $email;

if (!emailValido($email) || $senha === '' || strlen($senha) > 200) {
    flash('erro', 'Informe um e-mail corporativo válido e sua senha.');
    redirect('login.php');
}

if (loginBloqueado($email)) {
    registrarLog('login_bloqueado', 'Usuarios', $email, 'Limite de tentativas excedido.', 'negado', ['email' => $email, 'perfil' => '']);
    flash('erro', 'Muitas tentativas sem sucesso. Aguarde 15 minutos e tente novamente.');
    redirect('login.php');
}

$resultado = autenticarComSenha($email, $senha);

if (!$resultado['ok']) {
    registrarFalhaLogin($email);
    flash('erro', $resultado['erro']);
    redirect('login.php');
}

limparFalhasLogin($email);
unset($_SESSION['login_email']);
iniciarSessaoUsuario($resultado['usuario'], $lembrar);

redirect(rotaInicialPorPerfil($resultado['usuario']['perfil']));
