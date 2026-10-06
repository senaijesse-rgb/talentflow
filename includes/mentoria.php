<?php

declare(strict_types=1);

/**
 * Orientação de desenvolvimento profissional.
 * Não faz terapia, diagnóstico nem aconselhamento clínico.
 * Usa só o título da meta, a competência, o projeto e o texto que a pessoa escreveu.
 */

function metasParaMentoria(string $email): array
{
    $abertas = array_values(array_filter(
        pdisDoColaborador($email),
        static fn ($pdi) => statusEfetivo($pdi) !== 'Concluído'
    ));

    usort($abertas, static fn ($a, $b) => strcmp((string) $a['prazo'], (string) $b['prazo']));

    return $abertas;
}

function projetoAtualMentoria(string $email): string
{
    $atuais = projetosDoColaborador($email)['atuais'] ?? [];

    return (string) ($atuais[0]['nome_projeto'] ?? '');
}

function mentoriaVeioDoN8n(?string $origem): bool
{
    return in_array($origem ?? '', ['generativa', 'azure-openai'], true);
}

/** @return array{mensagem: string, passos: list<string>, reflexao: string, aviso: ?string} */
function gerarPlanoMentoria(array $pdi, string $dificuldade, string $projeto = ''): array
{
    $titulo = (string) ($pdi['meta'] ?? 'sua meta');
    $competencia = (string) ($pdi['competencia'] ?? 'essa competência');
    $texto = mb_strtolower($dificuldade);
    $sensivel = preg_match('/ansiedad|depress|burnout|esgotad|ass[eé]dio|p[aâ]nico|crise|n[aã]o aguento|ins[oô]nia|chor|adoec|discrimina|medica/u', $texto) === 1;

    $temas = [
        ['re' => '/tempo|prazo|agenda|corrid|demanda|entrega|sobrecarg|prioriz/u', 'foco' => 'organização do tempo e priorização',
            'passos' => [
                'Dias 1–2: liste as atividades da semana e reserve dois blocos fixos de 45 minutos na agenda exclusivamente para a meta “{m}”.',
                'Dias 3–5: divida a meta em uma entrega mínima que caiba nesses blocos (um módulo, um rascunho ou um teste) e conclua-a.',
                'Dias 6–7: registre a evidência na plataforma e leve ao seu gestor uma proposta de priorização caso a demanda continue alta.',
            ],
            'reflexao' => 'Qual atividade da sua semana poderia ser delegada, adiada ou simplificada para abrir espaço para o seu desenvolvimento?'],
        ['re' => '/conhec|t[eé]cnic|aprend|curso|estud|entend|complex|dif[ií]cil|teoria|pr[aá]tica/u', 'foco' => 'aprendizagem técnica',
            'passos' => [
                'Dias 1–2: identifique exatamente qual conceito de “{c}” está travando seu avanço e escolha um único material de referência.',
                'Dias 3–5: pratique com um exercício curto aplicado ao seu contexto{p} e anote dúvidas objetivas.',
                'Dias 6–7: agende 30 minutos com um colega mais experiente para revisar as dúvidas e registre o aprendizado como evidência.',
            ],
            'reflexao' => 'O que você já sabe hoje que pode servir de ponte para o conhecimento que está faltando?'],
        ['re' => '/motiva|desanim|foco|procrastin|interesse|sentido|cansad/u', 'foco' => 'engajamento e ritmo',
            'passos' => [
                'Dias 1–2: reescreva em uma frase por que a meta “{m}” importa para sua carreira e para o seu próximo passo profissional.',
                'Dias 3–5: defina uma micro-meta diária de 20 minutos e marque cada dia cumprido — pequenas vitórias geram ritmo.',
                'Dias 6–7: compartilhe o avanço com seu gestor ou um colega e peça um feedback específico sobre o que já evoluiu.',
            ],
            'reflexao' => 'Em que momento você se sentiu mais motivado com essa meta e o que estava diferente naquele momento?'],
        ['re' => '/acesso|dados|ferrament|recurso|licen[cç]a|sistema|permiss|or[cç]amento|equipamento/u', 'foco' => 'recursos e acessos',
            'passos' => [
                'Dias 1–2: liste quais acessos, ferramentas ou informações estão faltando e o impacto de cada um na meta “{m}”.',
                'Dias 3–5: formalize o pedido ao responsável (gestor, TI ou área dona do dado) com justificativa e prazo sugerido.',
                'Dias 6–7: enquanto aguarda, avance em uma parte da meta que não dependa desse recurso e registre a evidência.',
            ],
            'reflexao' => 'Existe alguma alternativa temporária que permita avançar mesmo sem o recurso ideal?'],
        ['re' => '/gestor|feedback|equipe|colega|comunica|alinhament|expectativa|conflit|relacion/u', 'foco' => 'alinhamento e comunicação',
            'passos' => [
                'Dias 1–2: escreva quais expectativas você entende que existem sobre a meta “{m}” e onde estão as dúvidas.',
                'Dias 3–5: agende uma conversa curta com seu gestor para validar critérios de sucesso, prazo e evidências esperadas.',
                'Dias 6–7: atualize a meta na plataforma com o que foi combinado e defina o próximo ponto de acompanhamento.',
            ],
            'reflexao' => 'Que pergunta, se feita ao seu gestor hoje, eliminaria a maior parte da sua incerteza?'],
    ];

    $tema = [
        'foco' => 'execução da meta',
        'passos' => [
            'Dias 1–2: revise a meta “{m}” e defina qual é a próxima entrega concreta e verificável.',
            'Dias 3–5: execute essa entrega em blocos curtos e anote o que facilitou ou dificultou o avanço.',
            'Dias 6–7: registre a evidência, atualize o percentual de progresso e compartilhe o resultado com seu gestor.',
        ],
        'reflexao' => 'Como você saberá, de forma objetiva, que avançou nesta meta ao final da semana?',
    ];

    foreach ($temas as $candidato) {
        if (preg_match($candidato['re'], $texto) === 1) {
            $tema = $candidato;
            break;
        }
    }

    $contexto = $projeto !== '' ? ' (por exemplo, no projeto ' . $projeto . ')' : '';
    $passos = array_map(static function (string $passo) use ($titulo, $competencia, $contexto): string {
        return str_replace(['{m}', '{c}', '{p}'], [$titulo, $competencia, $contexto], $passo);
    }, $tema['passos']);

    $aviso = null;
    if ($sensivel) {
        $aviso = 'Sua mensagem menciona questões que vão além do plano de desenvolvimento. A MentorIA não realiza diagnóstico nem aconselhamento clínico ou psicológico. Recomendamos conversar com seu gestor ou com o RH, que podem acionar os canais de apoio da empresa de forma confidencial. Em situação de urgência, procure atendimento especializado (CVV: 188 · SAMU: 192).';
    } elseif (statusEfetivo($pdi) === 'Atrasado') {
        $aviso = 'Esta meta está com o prazo vencido. Procure seu gestor para repactuar o prazo e registrar o novo combinado.';
    } elseif (preg_match('/bloque|imposs[ií]vel|n[aã]o consigo/u', $texto) === 1) {
        $aviso = 'Se o bloqueio persistir após esses passos, procure seu gestor ou o RH para avaliar ajustes na meta.';
    }

    return [
        'mensagem' => 'Obrigado por compartilhar. Entendi que sua principal dificuldade na meta “' . $titulo . '” está relacionada a ' . $tema['foco'] . '. Montei um plano curto e prático para os próximos sete dias:',
        'passos' => $passos,
        'reflexao' => $tema['reflexao'],
        'aviso' => $aviso,
    ];
}

/** @return list<array<string, mixed>> */
function historicoMentoria(): array
{
    $itens = $_SESSION['mentoria_historico'] ?? [];

    return is_array($itens) ? array_values($itens) : [];
}

function registrarMentoriaSessao(array $item): void
{
    $historico = historicoMentoria();
    array_unshift($historico, $item);
    $_SESSION['mentoria_historico'] = array_slice($historico, 0, 8);
}
