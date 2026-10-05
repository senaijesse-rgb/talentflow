<?php

declare(strict_types=1);

/*
 * Roteador para o servidor embutido do PHP (somente desenvolvimento):
 *   php -S localhost:8000 router.php
 * Replica os bloqueios do .htaccess, que o servidor embutido ignora.
 */

$caminho = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

$bloqueado = preg_match('#/\.#', $caminho)
    || preg_match('#^/(includes|services|storage|data|vendor|credentials)(/|$)#i', $caminho)
    || preg_match('#\.(json|lock|md|example|log)$#i', $caminho)
    || $caminho === '/router.php';

if ($bloqueado) {
    http_response_code(404);
    echo 'Não encontrado.';

    return true;
}

return false;
