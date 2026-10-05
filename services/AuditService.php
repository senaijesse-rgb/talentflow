<?php

declare(strict_types=1);

/**
 * Registro de auditoria na aba Logs_Auditoria.
 * Nunca inclua valores individuais de bem-estar, senhas ou tokens na descrição.
 */
final class AuditService
{
    public const RESULTADOS = ['sucesso', 'falha', 'negado'];

    public static function registrar(
        string $acao,
        string $entidade,
        string $idEntidade = '',
        string $descricao = '',
        string $resultado = 'sucesso',
        ?array $usuario = null,
    ): void {
        $usuario ??= usuarioAtual() ?? [];

        try {
            db()->inserir('Logs_Auditoria', [
                'id_log' => gerarId('LOG'),
                'data_hora' => agoraIso(),
                'email_usuario' => $usuario['email'] ?? 'anonimo',
                'perfil_usuario' => $usuario['perfil'] ?? '',
                'acao' => $acao,
                'entidade' => $entidade,
                'id_entidade' => $idEntidade,
                'descricao' => mb_substr($descricao, 0, 500),
                'ip' => ipCliente(),
                'resultado' => in_array($resultado, self::RESULTADOS, true) ? $resultado : 'falha',
            ]);
        } catch (Throwable $erro) {
            error_log('[AuditService] Falha ao registrar log: ' . $erro->getMessage());
        }
    }
}

function registrarLog(
    string $acao,
    string $entidade,
    string $idEntidade = '',
    string $descricao = '',
    string $resultado = 'sucesso',
    ?array $usuario = null,
): void {
    AuditService::registrar($acao, $entidade, $idEntidade, $descricao, $resultado, $usuario);
}
