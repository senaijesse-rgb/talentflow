<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$usuario = exigirLogin();

$tituloPagina = match ($usuario['perfil']) {
    'administrador' => 'Dashboard do RH',
    'gestor' => 'Dashboard da equipe',
    default => 'Meu dashboard',
};

require __DIR__ . '/includes/header.php';

match ($usuario['perfil']) {
    'administrador' => require __DIR__ . '/includes/views/dashboard_admin.php',
    'gestor' => require __DIR__ . '/includes/views/dashboard_gestor.php',
    default => require __DIR__ . '/includes/views/dashboard_colaborador.php',
};

require __DIR__ . '/includes/views/mentoria_painel.php';
require __DIR__ . '/includes/footer.php';
