<?php

declare(strict_types=1);

/**
 * Envia eventos ao n8n via POST JSON.
 * Autenticação: header X-App-Secret (segredo compartilhado) e X-App-Signature (HMAC-SHA256 do corpo).
 * O envio de e-mails (Gmail) é responsabilidade dos fluxos do n8n, guiados pelo campo "notificacoes".
 */
final class N8NWebhookService
{
    public const EVENTOS_EMAIL = [
        'confirmacao_atualizacao_pdi' => 'Confirmação de atualização de PDI para o colaborador',
        'lembrete_pdi_abaixo_50' => 'Lembrete quando o PDI estiver abaixo de 50%',
        'aviso_prazo_proximo' => 'Aviso de prazo próximo',
        'alerta_pdi_atrasado' => 'Alerta de PDI atrasado para colaborador e gestor',
        'aviso_meta_concluida' => 'Aviso de meta concluída',
        'solicitacao_validacao_gestor' => 'Solicitação de validação de meta para o gestor',
        'alerta_risco_alto_rh' => 'Alerta de risco alto para o RH',
        'resumo_semanal_gestor' => 'Resumo semanal para gestor',
        'resumo_mensal_rh' => 'Resumo mensal para RH',
    ];

    /** Textos iniciais. O RH pode substituí-los na aba Regras, chave modelo_email_<codigo>. */
    public const MODELOS_PADRAO = [
        'confirmacao_atualizacao_pdi' => 'Olá, {{nome_colaborador}}. Registramos a atualização da meta "{{meta}}" para {{percentual_novo}}% ({{status}}). Prazo: {{prazo}}.',
        'lembrete_pdi_abaixo_50' => 'Olá, {{nome_colaborador}}. A meta "{{meta}}" está em {{percentual_novo}}%. Vale retomar o plano com {{nome_gestor}}.',
        'aviso_prazo_proximo' => 'Olá, {{nome_colaborador}}. O prazo da meta "{{meta}}" é {{prazo}}. Status atual: {{status}}.',
        'alerta_pdi_atrasado' => 'Olá. A meta "{{meta}}" de {{nome_colaborador}} passou do prazo {{prazo}}. Status: {{status}}.',
        'aviso_meta_concluida' => 'Olá, {{nome_colaborador}}. A meta "{{meta}}" foi concluída. Obrigado pelo acompanhamento, {{nome_gestor}}.',
        'solicitacao_validacao_gestor' => 'Olá, {{nome_gestor}}. {{nome_colaborador}} enviou a meta "{{meta}}" para validação.',
        'alerta_risco_alto_rh' => 'Olá, equipe de RH. Há uma classificação de risco alta para acompanhar no PDI Connect. Este aviso não traz nota nem comentário de bem-estar.',
        'resumo_semanal_gestor' => 'Olá, {{nome_gestor}}. Segue o resumo semanal da equipe: metas, prazos e classificações de risco, sem bem-estar individual.',
        'resumo_mensal_rh' => 'Olá. Segue o resumo mensal de PDIs e indicadores da organização, sem comentários individuais de bem-estar.',
        'alerta' => 'Olá, {{nome_gestor}}. O PDI de {{nome_colaborador}} requer atenção: {{meta}} ({{status}}).',
    ];

    public function __construct(
        private readonly string $url,
        private readonly string $segredo,
        private readonly bool $ativo = true,
        private readonly int $timeoutSegundos = 5,
    ) {
    }

    public static function padrao(): self
    {
        return new self(
            (string) (regra('webhook_n8n_url') ?: env('N8N_WEBHOOK_URL', '')),
            (string) env('N8N_WEBHOOK_SECRET', ''),
            boolValor(regra('webhook_n8n_ativo', 'sim')),
        );
    }

    public function configurado(): bool
    {
        return $this->ativo && $this->urlValida();
    }

    /** @return array{enviado: bool, status: ?int, erro: ?string} */
    public function enviar(array $payload): array
    {
        if (!$this->ativo) {
            return ['enviado' => false, 'status' => null, 'erro' => 'Webhook desativado nas regras.'];
        }
        if (!$this->urlValida()) {
            return ['enviado' => false, 'status' => null, 'erro' => 'URL do webhook n8n não configurada ou inválida.'];
        }
        if (!function_exists('curl_init')) {
            return ['enviado' => false, 'status' => null, 'erro' => 'Extensão cURL indisponível.'];
        }

        $corpo = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $cabecalhos = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: PDI-Connect/1.0',
            'X-PDI-Event: ' . ($payload['evento'] ?? 'desconhecido'),
        ];
        if ($this->segredo !== '') {
            $cabecalhos[] = 'X-App-Secret: ' . $this->segredo;
            $cabecalhos[] = 'X-App-Signature: sha256=' . hash_hmac('sha256', $corpo, $this->segredo);
        }

        $curl = curl_init($this->url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $corpo,
            CURLOPT_HTTPHEADER => $cabecalhos,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => $this->timeoutSegundos,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        ]);

        curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $erroCurl = curl_error($curl);
        unset($curl);

        if ($erroCurl !== '') {
            return ['enviado' => false, 'status' => null, 'erro' => $erroCurl];
        }

        $sucesso = $status >= 200 && $status < 300;

        return ['enviado' => $sucesso, 'status' => $status, 'erro' => $sucesso ? null : "HTTP {$status}"];
    }

    private function urlValida(): bool
    {
        if (filter_var($this->url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $esquema = parse_url($this->url, PHP_URL_SCHEME);

        return $esquema === 'https' || ($esquema === 'http' && APP_ENV === 'local');
    }
}

/**
 * Envia o payload ao n8n e registra o resultado na auditoria.
 * Falhas não interrompem o fluxo do usuário: o dado já foi salvo na planilha.
 */
function chaveModeloEmail(string $codigo): string
{
    return $codigo === 'alerta' ? 'modelo_email_alerta' : 'modelo_email_' . $codigo;
}

function textoModeloEmail(string $codigo): string
{
    $salvo = trim((string) regra(chaveModeloEmail($codigo), ''));

    return $salvo !== '' ? $salvo : (N8NWebhookService::MODELOS_PADRAO[$codigo] ?? '');
}

function enviarWebhookN8N(array $payload): array
{
    $modelos = [];
    foreach ((array) ($payload['notificacoes'] ?? []) as $codigo) {
        if (is_string($codigo) && isset(N8NWebhookService::EVENTOS_EMAIL[$codigo])) {
            $modelos[$codigo] = textoModeloEmail($codigo);
        }
    }
    if ($modelos !== []) {
        $payload['modelos_email'] = $modelos;
    }

    $resultado = N8NWebhookService::padrao()->enviar($payload);
    $evento = (string) ($payload['evento'] ?? 'desconhecido');
    $idEntidade = (string) ($payload['id_pdi'] ?? $payload['id_checkin'] ?? $payload['email_colaborador'] ?? '');

    registrarLog(
        'webhook_n8n',
        'Webhook',
        $idEntidade,
        $resultado['enviado'] ? "Evento {$evento} enviado (HTTP {$resultado['status']})." : "Evento {$evento} não enviado: {$resultado['erro']}",
        $resultado['enviado'] ? 'sucesso' : 'falha'
    );

    return $resultado;
}
