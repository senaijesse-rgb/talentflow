<?php

declare(strict_types=1);

/*
 * Único ponto de entrada na Vercel.
 * A plataforma só publica Serverless Functions que estejam em api/.
 * Este arquivo encaminha a URL pública (index.php, login.php, actions/...)
 * para o script correspondente na raiz, sem alterar o comportamento no Apache.
 *
 * SCRIPT_NAME é reescrito antes do include: requisicaoApi() trata qualquer
 * caminho /api/ como endpoint JSON, e as páginas HTML não podem cair nisso.
 */

$raiz = dirname(__DIR__);
$relativo = rotaPublica();

if ($relativo === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Não encontrado.';
    exit;
}

$arquivo = $raiz . '/' . $relativo;
$_SERVER['SCRIPT_NAME'] = '/' . $relativo;
$_SERVER['PHP_SELF'] = '/' . $relativo;
$_SERVER['SCRIPT_FILENAME'] = $arquivo;

require $arquivo;

/**
 * Resolve o script público pedido pela rota da Vercel (?__pdi_rota=)
 * ou, no acesso direto, pelo REQUEST_URI. Devolve null quando o caminho
 * não é uma página PHP publicada.
 */
function rotaPublica(): ?string
{
    $bruto = isset($_GET['__pdi_rota']) ? (string) $_GET['__pdi_rota'] : '';
    unset($_GET['__pdi_rota'], $_REQUEST['__pdi_rota']);

    if (isset($_SERVER['QUERY_STRING'])) {
        $consulta = preg_replace('/(?:^|&)__pdi_rota=[^&]*/', '', (string) $_SERVER['QUERY_STRING']) ?? '';
        $_SERVER['QUERY_STRING'] = trim($consulta, '&');
    }

    if ($bruto === '') {
        $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if ($uri === '/' || $uri === '') {
            $bruto = 'index.php';
        } elseif (!str_starts_with($uri, '/api/')) {
            $bruto = ltrim($uri, '/');
        }
    }

    $bruto = str_replace('\\', '/', rawurldecode($bruto));
    $bruto = ltrim($bruto, '/');

    if ($bruto === '' || $bruto === 'index.php/') {
        $bruto = 'index.php';
    }

    if (str_contains($bruto, "\0") || str_contains($bruto, '..') || !str_ends_with($bruto, '.php')) {
        return null;
    }

    if (!preg_match('#^(actions/)?[A-Za-z0-9_-]+\.php$#', $bruto)) {
        return null;
    }

    if ($bruto === 'router.php') {
        return null;
    }

    $raizReal = realpath(dirname(__DIR__));
    $arquivoReal = realpath(dirname(__DIR__) . '/' . $bruto);

    if ($raizReal === false || $arquivoReal === false || !is_file($arquivoReal)) {
        return null;
    }

    $prefixo = rtrim(str_replace('\\', '/', $raizReal), '/') . '/';
    $relativo = str_replace('\\', '/', $arquivoReal);

    if (!str_starts_with($relativo, $prefixo)) {
        return null;
    }

    return substr($relativo, strlen($prefixo));
}
