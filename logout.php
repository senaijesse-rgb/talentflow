<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$usuario = usuarioAtual();

if ($usuario === null) {
    redirect('login.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrfValido($_POST['csrf_token'] ?? null)) {
    redirect('dashboard.php');
}

registrarLog('logout', 'Usuarios', $usuario['email'], 'Logout realizado.', 'sucesso', $usuario);
reiniciarSessao();
flash('sucesso', 'Você saiu com segurança.');
redirect('login.php');
