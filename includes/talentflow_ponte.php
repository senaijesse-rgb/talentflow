<?php

declare(strict_types=1);

/**
 * Leva os usuários ativos do PDI Connect para o TalentFlow sem apagar a base existente,
 * e abre a sessão no fluxo n8n TalentFlow PDI — API.
 */

function perfilTalentFlow(string $perfil): string
{
    return match ($perfil) {
        'administrador' => 'rh',
        'gestor' => 'gestor',
        default => 'colaborador',
    };
}

function urlWebhookTalentFlow(): string
{
    $configurada = (string) env('N8N_WEBHOOK_URL', '');
    if (preg_match('#^(https://[^/]+)/webhook/#', $configurada, $partes)) {
        return $partes[1] . '/webhook/talentflow';
    }

    return 'https://senaipdi.app.n8n.cloud/webhook/talentflow';
}

/** @return array{token: string, user: array<string, string>}|array{erro: string, status: int} */
function abrirSessaoTalentFlow(array $usuario): array
{
    $email = normalizarEmail($usuario['email'] ?? '');
    if ($email === '' || !boolValor(buscarUsuario($email)['ativo'] ?? 'nao')) {
        return ['erro' => 'Seu acesso no PDI Connect não está ativo.', 'status' => 403];
    }

    try {
        garantirUsuariosNoTalentFlow();
    } catch (Throwable $erro) {
        error_log('[TalentFlow] ' . $erro->getMessage());

        return ['erro' => 'Não foi possível preparar o seu acesso no TalentFlow.', 'status' => 502];
    }

    $resposta = chamarWebhookTalentFlow([
        'recurso' => 'login',
        'metodo' => 'POST',
        'email' => $email,
    ]);
    $corpo = $resposta['json'];
    $token = is_array($corpo) ? (string) ($corpo['token'] ?? '') : '';
    $pessoa = is_array($corpo) ? ($corpo['user'] ?? null) : null;

    if (!$resposta['ok'] || $token === '' || !is_array($pessoa)) {
        $mensagem = is_array($corpo) ? (string) ($corpo['message'] ?? '') : '';

        return [
            'erro' => $mensagem !== '' ? $mensagem : 'O fluxo do TalentFlow não confirmou o acesso.',
            'status' => $resposta['status'] >= 400 ? $resposta['status'] : 502,
        ];
    }

    return [
        'token' => $token,
        'user' => [
            'email' => (string) ($pessoa['email'] ?? $email),
            'nome' => (string) ($pessoa['nome'] ?? ''),
            'perfil' => (string) ($pessoa['perfil'] ?? ''),
            'cargo' => (string) ($pessoa['cargo'] ?? ''),
            'departamento' => (string) ($pessoa['departamento'] ?? ''),
        ],
    ];
}

function garantirUsuariosNoTalentFlow(): void
{
    $existentes = [];
    foreach (db()->lerIntervaloTalentFlow('TF_Registros', 'A:B') as $linha) {
        if (($linha[0] ?? '') === 'users' && ($linha[1] ?? '') !== '') {
            $existentes[normalizarEmail($linha[1])] = true;
        }
    }

    $naProjecao = [];
    foreach (db()->lerIntervaloTalentFlow('TF_Usuarios', 'A:A') as $indice => $linha) {
        if ($indice === 0) {
            continue;
        }
        if (($linha[0] ?? '') !== '') {
            $naProjecao[normalizarEmail($linha[0])] = true;
        }
    }

    $registros = [];
    $projecao = [];
    $hoje = (new DateTimeImmutable('today'))->format('Y-m-d');

    foreach (usuariosAtivos() as $pessoa) {
        $email = normalizarEmail($pessoa['email'] ?? '');
        if ($email === '' || isset($existentes[$email])) {
            continue;
        }

        $gestor = normalizarEmail($pessoa['gestor_email'] ?? '');
        $json = json_encode([
            'email' => $email,
            'nome' => (string) ($pessoa['nome_completo'] ?? $email),
            'cargo' => (string) ($pessoa['cargo'] ?? ''),
            'departamento' => (string) ($pessoa['area'] ?? ''),
            'gestorEmail' => $gestor !== '' ? $gestor : null,
            'perfil' => perfilTalentFlow((string) ($pessoa['perfil'] ?? '')),
            'status' => 'Ativo',
            'projetoAtual' => null,
            'projetosAnteriores' => [],
            'disponibilidade' => 'Disponível',
            'satisfacao' => null,
            'ultimaAtualizacao' => $hoje,
            'risco' => 'Baixo',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $registros[] = ['users', $email, $json];
        if (!isset($naProjecao[$email])) {
            $projecao[] = [
                $email,
                (string) ($pessoa['nome_completo'] ?? ''),
                (string) ($pessoa['cargo'] ?? ''),
                (string) ($pessoa['area'] ?? ''),
                perfilTalentFlow((string) ($pessoa['perfil'] ?? '')),
                'Ativo',
                $gestor,
                'Disponível',
                '',
                'Baixo',
            ];
        }
    }

    db()->anexarLinhasTalentFlow('TF_Registros', $registros);
    db()->anexarLinhasTalentFlow('TF_Usuarios', $projecao);
}

/** @return array{ok: bool, status: int, json: ?array} */
function chamarWebhookTalentFlow(array $payload): array
{
    $url = urlWebhookTalentFlow();
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'json' => null];
    }

    $corpo = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $corpo,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $certificados = ROOT_PATH . '/storage/cacert.pem';
    if (is_file($certificados)) {
        curl_setopt($curl, CURLOPT_CAINFO, $certificados);
    }
    $texto = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $erro = curl_error($curl);
    unset($curl);

    if ($erro !== '' || !is_string($texto)) {
        return ['ok' => false, 'status' => 0, 'json' => null];
    }

    $json = json_decode($texto, true);

    return [
        'ok' => $status >= 200 && $status < 300 && is_array($json),
        'status' => $status,
        'json' => is_array($json) ? $json : null,
    ];
}
