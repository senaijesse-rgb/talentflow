<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

if (is_file(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

function carregarEnv(string $arquivo): void
{
    if (!is_file($arquivo)) {
        return;
    }

    foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linha) {
        $linha = trim($linha);
        if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
            continue;
        }

        [$chave, $valor] = array_map('trim', explode('=', $linha, 2));
        $valor = trim($valor, "\"'");

        if (getenv($chave) === false && !isset($_ENV[$chave])) {
            $_ENV[$chave] = $valor;
            putenv("{$chave}={$valor}");
        }
    }
}

function env(string $chave, ?string $padrao = null): ?string
{
    $valor = $_ENV[$chave] ?? getenv($chave);

    return ($valor === false || $valor === '' || $valor === null) ? $padrao : (string) $valor;
}

carregarEnv(ROOT_PATH . '/.env');

define('APP_NAME', 'PDI Connect');
define('APP_ENV', (string) env('APP_ENV', 'production'));
define('APP_DEBUG', APP_ENV === 'local');
define('APP_URL', rtrim((string) env('APP_URL', ''), '/'));
define('DATA_SOURCE', env('DATA_SOURCE', 'mock') === 'sheets' ? 'sheets' : 'mock');
define('SESSION_IDLE_TIMEOUT', max(5, (int) env('SESSION_IDLE_MINUTES', '30')) * 60);
define('SESSION_REMEMBER_LIFETIME', max(1, (int) env('SESSION_REMEMBER_DAYS', '7')) * 86400);
define('IS_HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' && env('TRUST_PROXY') === 'true'));

$diretorioScript = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
define('BASE_PATH', rtrim((string) preg_replace('#/(actions|api)$#', '', $diretorioScript), '/'));
unset($diretorioScript);

date_default_timezone_set((string) env('APP_TIMEZONE', 'America/Sao_Paulo'));
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

function diretorioStorage(): string
{
    static $diretorio = null;
    if ($diretorio !== null) {
        return $diretorio;
    }

    $preferido = ROOT_PATH . '/storage';
    if (is_dir($preferido) && is_writable($preferido)) {
        return $diretorio = $preferido;
    }

    // Ambientes serverless (ex.: Vercel) só permitem escrita no diretório temporário.
    $diretorio = rtrim(sys_get_temp_dir(), '/\\') . '/pdi_connect';
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0700, true);
    }

    return $diretorio;
}

require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/services/GoogleSheetsService.php';
require_once ROOT_PATH . '/services/AuditService.php';
require_once ROOT_PATH . '/services/RiskService.php';
require_once ROOT_PATH . '/services/N8NWebhookService.php';
require_once ROOT_PATH . '/includes/csrf.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/permissions.php';

set_exception_handler(static function (Throwable $erro): void {
    error_log('[PDI Connect] ' . $erro);
    $mensagem = APP_DEBUG ? $erro->getMessage() : 'Ocorreu um erro inesperado. Tente novamente em instantes.';

    if (requisicaoApi()) {
        responderJson(['erro' => $mensagem], 500);
    }

    renderizarPaginaErro(500, 'Erro interno', $mensagem);
});

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', (string) SESSION_REMEMBER_LIFETIME);

    session_name('PDICONNECTSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_PATH === '' ? '/' : BASE_PATH . '/',
        'secure' => IS_HTTPS,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!headers_sent()) {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }
}
