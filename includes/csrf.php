<?php

declare(strict_types=1);

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfCampo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function csrfValido(?string $token): bool
{
    return is_string($token) && $token !== '' && hash_equals(csrfToken(), $token);
}

/** Interrompe a requisição quando o token é inválido, redirecionando para $voltarPara. */
function exigirCsrf(string $voltarPara = 'dashboard.php'): void
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (csrfValido(is_string($token) ? $token : null)) {
        return;
    }

    registrarLog('csrf_invalido', 'Sistema', basename($_SERVER['SCRIPT_NAME'] ?? ''), 'Token CSRF ausente ou inválido.', 'negado');

    if (requisicaoApi()) {
        responderJson(['erro' => 'Sessão expirada. Recarregue a página.'], 419);
    }

    flash('erro', 'Sua sessão expirou ou o formulário é inválido. Tente novamente.');
    redirect($voltarPara);
}
