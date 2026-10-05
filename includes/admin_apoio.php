<?php

declare(strict_types=1);

function classeCampoAdmin(): string
{
    return 'mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20';
}

/** @return array<string, array{rotulo: string, descricao: string}> */
function secoesAdmin(): array
{
    return [
        'usuarios' => ['rotulo' => 'Gestão de usuários', 'descricao' => 'Criar, editar, ativar e inativar usuários e perfis.'],
        'equipes' => ['rotulo' => 'Gestores e equipes', 'descricao' => 'Vincular colaboradores aos respectivos gestores.'],
        'projetos' => ['rotulo' => 'Projetos', 'descricao' => 'Cadastro de projetos e participações.'],
        'pdis' => ['rotulo' => 'PDIs', 'descricao' => 'Metas, prazos e status de todos os PDIs.'],
        'regras' => ['rotulo' => 'Regras e faixas', 'descricao' => 'Pesos de risco, prazos, alertas e classificações.'],
        'logs' => ['rotulo' => 'Logs de auditoria', 'descricao' => 'Acessos, alterações e tentativas negadas.'],
        'integracoes' => ['rotulo' => 'Integrações n8n', 'descricao' => 'URL do webhook e status do envio.'],
        'emails' => ['rotulo' => 'Modelos de e-mail', 'descricao' => 'Templates dos e-mails enviados via n8n/Gmail.'],
        'bem-estar' => ['rotulo' => 'Bem-estar individual', 'descricao' => 'Área restrita com registro de acesso.'],
    ];
}

/**
 * Regras numéricas e de política editáveis pelo RH.
 * webhook e modelos de e-mail ficam nas seções próprias.
 *
 * @return array<string, array{rotulo: string, tipo: string, min?: int, max?: int}>
 */
function catalogoRegrasAdmin(): array
{
    return [
        'dias_sem_atualizacao' => ['rotulo' => 'Dias sem atualização de PDI para alerta', 'tipo' => 'inteiro', 'min' => 1, 'max' => 365],
        'dias_prazo_proximo' => ['rotulo' => 'Antecedência do aviso de prazo (dias)', 'tipo' => 'inteiro', 'min' => 1, 'max' => 90],
        'dias_meta_concluida_recente' => ['rotulo' => 'Janela de meta concluída recentemente (dias)', 'tipo' => 'inteiro', 'min' => 1, 'max' => 365],
        'limite_atencao_percentual' => ['rotulo' => 'Percentual abaixo do qual a meta exige atenção', 'tipo' => 'inteiro', 'min' => 0, 'max' => 100],
        'limite_risco_medio' => ['rotulo' => 'Score mínimo para risco médio', 'tipo' => 'inteiro', 'min' => 0, 'max' => 100],
        'limite_risco_alto' => ['rotulo' => 'Score mínimo para risco alto', 'tipo' => 'inteiro', 'min' => 0, 'max' => 100],
        'limite_risco_critico' => ['rotulo' => 'Score mínimo para risco crítico', 'tipo' => 'inteiro', 'min' => 0, 'max' => 100],
        'risco_pontos_saida_alta' => ['rotulo' => 'Pontos: risco de saída alto', 'tipo' => 'inteiro', 'min' => -50, 'max' => 100],
        'risco_pontos_satisfacao_baixa' => ['rotulo' => 'Pontos: satisfação baixa', 'tipo' => 'inteiro', 'min' => -50, 'max' => 100],
        'risco_pontos_pdi_atrasado' => ['rotulo' => 'Pontos: PDI atrasado', 'tipo' => 'inteiro', 'min' => -50, 'max' => 100],
        'risco_pontos_sem_atualizacao' => ['rotulo' => 'Pontos: PDI sem atualização', 'tipo' => 'inteiro', 'min' => -50, 'max' => 100],
        'risco_pontos_bem_estar_baixo' => ['rotulo' => 'Pontos: bem-estar baixo (visível só no cálculo do RH)', 'tipo' => 'inteiro', 'min' => -50, 'max' => 100],
        'risco_pontos_meta_concluida' => ['rotulo' => 'Ajuste: meta concluída recentemente', 'tipo' => 'inteiro', 'min' => -50, 'max' => 50],
        'risco_pontos_satisfacao_alta' => ['rotulo' => 'Ajuste: satisfação alta', 'tipo' => 'inteiro', 'min' => -50, 'max' => 50],
        'risco_pontos_progresso_alto' => ['rotulo' => 'Ajuste: progresso do PDI elevado', 'tipo' => 'inteiro', 'min' => -50, 'max' => 50],
        'limite_progresso_alto' => ['rotulo' => 'Percentual de progresso considerado alto', 'tipo' => 'inteiro', 'min' => 0, 'max' => 100],
        'minimo_respostas_agregado' => ['rotulo' => 'Mínimo de respostas para médias ao gestor', 'tipo' => 'inteiro', 'min' => 1, 'max' => 100],
        'politica_exibir_satisfacao_gestor' => ['rotulo' => 'Gestor pode ver satisfação individual da equipe', 'tipo' => 'simnao'],
    ];
}

function gravarRegra(string $chave, string $valor, string $descricao): void
{
    $dados = [
        'valor' => $valor,
        'descricao' => $descricao,
        'ativo' => 'sim',
        'atualizado_em' => agoraIso(),
        'atualizado_por' => usuarioAtual()['email'] ?? '',
    ];

    if (db()->buscarUm('Regras', 'chave', $chave)) {
        db()->atualizar('Regras', 'chave', $chave, $dados);

        return;
    }

    db()->inserir('Regras', $dados + ['chave' => $chave]);
}

/** @return list<array<string, string>> */
function gestoresElegiveis(): array
{
    return array_values(array_filter(
        usuariosAtivos(),
        static fn (array $u): bool => in_array($u['perfil'], ['gestor', 'administrador'], true)
    ));
}

function quantidadeAdminsAtivos(): int
{
    return count(array_filter(
        usuariosAtivos(),
        static fn (array $u): bool => $u['perfil'] === 'administrador'
    ));
}

function celulaCsv(mixed $valor): string
{
    $texto = str_replace(["\r\n", "\r", "\n", "\t"], ' ', (string) $valor);
    if ($texto !== '' && preg_match('/^[=+\-@]/', $texto) === 1) {
        $texto = "'" . $texto;
    }

    return $texto;
}

function voltarAdmin(string $secao, array $extra = []): never
{
    $permitidas = array_keys(secoesAdmin());
    if (!in_array($secao, $permitidas, true)) {
        $secao = 'usuarios';
    }

    redirect('admin.php?' . http_build_query(['secao' => $secao] + $extra));
}
