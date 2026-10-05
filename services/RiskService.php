<?php

declare(strict_types=1);

/**
 * Score de risco de perda do colaborador (0 a 100), com pesos e limites configuráveis na aba Regras.
 * Fatores marcados como "sensivel" (bem-estar) nunca devem ser exibidos fora do perfil Administrador RH.
 */
final class RiskService
{
    public const PADROES = [
        'risco_pontos_saida_alta' => 35,
        'risco_pontos_satisfacao_baixa' => 25,
        'risco_pontos_pdi_atrasado' => 20,
        'risco_pontos_sem_atualizacao' => 15,
        'risco_pontos_bem_estar_baixo' => 10,
        'risco_pontos_meta_concluida' => -10,
        'risco_pontos_satisfacao_alta' => -10,
        'risco_pontos_progresso_alto' => -5,
        'dias_sem_atualizacao' => 14,
        'limite_progresso_alto' => 80,
        'limite_risco_medio' => 25,
        'limite_risco_alto' => 50,
        'limite_risco_critico' => 75,
    ];

    public const ROTULOS = [
        'BAIXO' => 'Baixo risco',
        'MEDIO' => 'Médio risco',
        'ALTO' => 'Alto risco',
        'CRITICO' => 'Crítico',
    ];

    public function __construct(private readonly array $regras = [])
    {
    }

    /**
     * @param array{
     *   risco_saida_percebido?: ?int, satisfacao_empresa?: ?int, bem_estar_trabalho?: ?int,
     *   pdi_atrasado?: bool, dias_sem_atualizacao?: ?int, meta_concluida_recente?: bool, percentual_medio?: ?float
     * } $dados
     * @return array{score: int, classificacao: string, rotulo: string, fatores: list<array>, recomendacoes: list<array>}
     */
    public function calcularScoreRisco(array $dados): array
    {
        $fatores = [];
        $adicionar = function (string $chave, string $descricao, string $recomendacao, bool $sensivel = false) use (&$fatores): void {
            $fatores[] = [
                'descricao' => $descricao,
                'pontos' => (int) $this->valor($chave),
                'sensivel' => $sensivel,
                'recomendacao' => $recomendacao,
            ];
        };

        $saida = $dados['risco_saida_percebido'] ?? null;
        $satisfacao = $dados['satisfacao_empresa'] ?? null;
        $bemEstar = $dados['bem_estar_trabalho'] ?? null;
        $diasSemAtualizacao = $dados['dias_sem_atualizacao'] ?? null;
        $percentual = $dados['percentual_medio'] ?? null;

        if ($saida !== null && $saida >= 4) {
            $adicionar('risco_pontos_saida_alta', 'Risco de saída percebido alto', 'Agendar conversa individual (1:1) sobre carreira, expectativas e reconhecimento.');
        }
        if ($satisfacao !== null && $satisfacao <= 2) {
            $adicionar('risco_pontos_satisfacao_baixa', 'Satisfação com a empresa baixa', 'Investigar fatores de insatisfação em conversa individual e definir ações concretas.');
        }
        if (!empty($dados['pdi_atrasado'])) {
            $adicionar('risco_pontos_pdi_atrasado', 'PDI com meta atrasada', 'Revisar prazos do PDI e remover impedimentos junto ao colaborador.');
        }
        if ($diasSemAtualizacao !== null && $diasSemAtualizacao > (int) $this->valor('dias_sem_atualizacao')) {
            $adicionar('risco_pontos_sem_atualizacao', 'PDI sem atualização recente', 'Solicitar atualização do PDI e acompanhar em check-in rápido.');
        }
        if ($bemEstar !== null && $bemEstar <= 2) {
            $adicionar('risco_pontos_bem_estar_baixo', 'Indicador de bem-estar no trabalho baixo', 'RH: oferecer acolhimento e canais de apoio de forma confidencial.', true);
        }
        if (!empty($dados['meta_concluida_recente'])) {
            $adicionar('risco_pontos_meta_concluida', 'Meta concluída recentemente', 'Reconhecer publicamente a conquista e planejar o próximo desafio.');
        }
        if ($satisfacao !== null && $satisfacao >= 4) {
            $adicionar('risco_pontos_satisfacao_alta', 'Satisfação com a empresa alta', '');
        }
        if ($percentual !== null && $percentual > (float) $this->valor('limite_progresso_alto')) {
            $adicionar('risco_pontos_progresso_alto', 'Progresso do PDI elevado', '');
        }

        $score = max(0, min(100, array_sum(array_column($fatores, 'pontos'))));
        $classificacao = $this->classificar($score);

        $recomendacoes = [];
        foreach ($fatores as $fator) {
            if ($fator['recomendacao'] !== '' && $fator['pontos'] > 0) {
                $recomendacoes[] = ['texto' => $fator['recomendacao'], 'sensivel' => $fator['sensivel']];
            }
        }

        return [
            'score' => $score,
            'classificacao' => $classificacao,
            'rotulo' => self::ROTULOS[$classificacao],
            'fatores' => $fatores,
            'recomendacoes' => $recomendacoes,
        ];
    }

    public function classificar(int $score): string
    {
        return match (true) {
            $score >= (int) $this->valor('limite_risco_critico') => 'CRITICO',
            $score >= (int) $this->valor('limite_risco_alto') => 'ALTO',
            $score >= (int) $this->valor('limite_risco_medio') => 'MEDIO',
            default => 'BAIXO',
        };
    }

    /** Visão do gestor: apenas classificação e recomendações operacionais, sem score nem fatores sensíveis. */
    public static function visaoGestor(array $resultado): array
    {
        return [
            'classificacao' => $resultado['classificacao'],
            'rotulo' => $resultado['rotulo'],
            'recomendacoes' => array_values(array_column(
                array_filter($resultado['recomendacoes'], static fn ($r) => !$r['sensivel']),
                'texto'
            )),
        ];
    }

    private function valor(string $chave): float
    {
        $valor = $this->regras[$chave] ?? null;

        return is_numeric($valor) ? (float) $valor : (float) self::PADROES[$chave];
    }
}

function calcularScoreRisco(array $dados, ?array $regras = null): array
{
    return (new RiskService($regras ?? regras()))->calcularScoreRisco($dados);
}
