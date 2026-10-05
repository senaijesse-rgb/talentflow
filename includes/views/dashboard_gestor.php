<?php
/** @var array $usuario */
$equipe = equipeDoGestor($usuario['email']);
$exibirSatisfacaoIndividual = boolValor(regra('politica_exibir_satisfacao_gestor', 'nao'));
$limiteDias = (int) regra('dias_sem_atualizacao', 14);

$contagem = ['andamento' => 0, 'atencao' => 0, 'atrasadas' => 0, 'concluidas' => 0, 'alto_risco' => 0, 'sem_atualizacao' => 0];
$linhas = [];
$alertas = [];

foreach ($equipe as $membro) {
    $resumo = resumoColaborador($membro);
    $risco = RiskService::visaoGestor($resumo['risco']);
    $nome = $membro['nome_completo'];

    foreach ($resumo['pdis'] as $pdi) {
        match ($pdi['status_efetivo']) {
            'Em andamento' => $contagem['andamento']++,
            'Atenção' => $contagem['atencao']++,
            'Atrasado' => $contagem['atrasadas']++,
            'Concluído' => $contagem['concluidas']++,
            default => null,
        };

        if ($pdi['status_efetivo'] === 'Atrasado') {
            $alertas[] = ['tom' => 'vermelho', 'icone' => 'alerta', 'titulo' => "Meta atrasada · {$nome}", 'texto' => $pdi['meta'] . ' (prazo ' . formatarData($pdi['prazo']) . ')', 'email' => $membro['email']];
        }
        if ($pdi['status_efetivo'] === 'Aguardando validação') {
            $alertas[] = ['tom' => 'azul', 'icone' => 'check', 'titulo' => "Meta aguardando validação · {$nome}", 'texto' => $pdi['meta'], 'email' => $membro['email']];
        }
    }

    if (in_array($risco['classificacao'], ['ALTO', 'CRITICO'], true)) {
        $contagem['alto_risco']++;
        $alertas[] = ['tom' => 'vermelho', 'icone' => 'bandeira', 'titulo' => "Risco de saída: {$risco['rotulo']} · {$nome}", 'texto' => $risco['recomendacoes'] ? implode(' ', $risco['recomendacoes']) : 'Agende uma conversa individual de acompanhamento.', 'email' => $membro['email']];
    }

    if ($resumo['dias_sem_atualizacao'] !== null && $resumo['dias_sem_atualizacao'] > $limiteDias) {
        $contagem['sem_atualizacao']++;
        $alertas[] = ['tom' => 'amarelo', 'icone' => 'relogio', 'titulo' => "Sem atualização de PDI · {$nome}", 'texto' => 'Última atualização ' . textoRelativoDias($resumo['dias_sem_atualizacao']) . '.', 'email' => $membro['email']];
    }

    $linhas[] = ['membro' => $membro, 'resumo' => $resumo, 'risco' => $risco];
}

$emailsEquipe = array_column($equipe, 'email');
$satisfacaoEquipe = agregadoCheckin($emailsEquipe, 'satisfacao_empresa', 0, 90, $exibirSatisfacaoIndividual ? 1 : null);
$mediaSatisfacao = $satisfacaoEquipe['media'];
$bemEstarAtual = agregadoCheckin($emailsEquipe, 'bem_estar_trabalho', 0, 30);
$bemEstarAnterior = agregadoCheckin($emailsEquipe, 'bem_estar_trabalho', 31, 60);

$tendencia = 'Sem base de comparação';
if ($bemEstarAtual['media'] !== null && $bemEstarAnterior['media'] !== null) {
    $diferenca = $bemEstarAtual['media'] - $bemEstarAnterior['media'];
    $tendencia = match (true) {
        $diferenca > 0.2 => 'Tendência de alta vs. mês anterior',
        $diferenca < -0.2 => 'Tendência de queda vs. mês anterior',
        default => 'Estável vs. mês anterior',
    };
}
$legendaBemEstar = $bemEstarAtual['suficiente']
    ? "{$bemEstarAtual['respondentes']} respostas · {$bemEstarAtual['alertas']} alerta(s) · {$tendencia}"
    : "Exibido a partir de {$bemEstarAtual['minimo']} respostas para preservar o anonimato";

$tonsAlerta = ['vermelho' => 'bg-red-50 text-red-700', 'amarelo' => 'bg-amber-50 text-amber-700', 'azul' => 'bg-blue-50 text-blue-700'];
$perfilDisponivel = paginaDisponivel('perfil.php');
?>
<section id="minha-equipe" class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-2xl font-semibold text-marinho-900">Olá, <?= e(primeiroNome($usuario['nome'])) ?>!</h2>
        <p class="text-sm text-slate-500">Acompanhe o desenvolvimento e os indicadores da sua equipe.</p>
    </div>
    <p class="text-xs text-slate-500">Atualizado em <?= e(date('d/m/Y H:i')) ?></p>
</section>

<section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Indicadores da equipe">
    <?= cardKpi('Colaboradores na equipe', (string) count($equipe), 'Vinculados a você', 'equipe', 'marinho') ?>
    <?= cardKpi('PDIs em andamento', (string) $contagem['andamento'], 'Metas em execução', 'prancheta', 'azul') ?>
    <?= cardKpi('Metas em atenção', (string) $contagem['atencao'], 'Precisam de acompanhamento', 'info', $contagem['atencao'] ? 'amarelo' : 'cinza') ?>
    <?= cardKpi('Metas atrasadas', (string) $contagem['atrasadas'], 'Prazo vencido', 'alerta', $contagem['atrasadas'] ? 'vermelho' : 'cinza') ?>
    <?= cardKpi('Metas concluídas', (string) $contagem['concluidas'], 'No ciclo atual', 'check', 'verde') ?>
    <?= cardKpi('Média de satisfação', $mediaSatisfacao === null ? '—' : formatarNumero($mediaSatisfacao) . '/5', $mediaSatisfacao === null ? 'Respostas insuficientes' : 'Últimos 90 dias', 'sorriso', 'azul') ?>
    <?= cardKpi('Bem-estar da equipe (agregado)', $bemEstarAtual['media'] === null ? '—' : formatarNumero($bemEstarAtual['media']) . '/5', $legendaBemEstar, 'escudo', 'marinho') ?>
    <?= cardKpi('Alto risco de saída', (string) $contagem['alto_risco'], 'Classificação alto ou crítico', 'bandeira', $contagem['alto_risco'] ? 'vermelho' : 'verde') ?>
    <?= cardKpi('Sem atualização de PDI', (string) $contagem['sem_atualizacao'], "Há mais de {$limiteDias} dias", 'relogio', $contagem['sem_atualizacao'] ? 'amarelo' : 'cinza') ?>
</section>

<section class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-tabela-equipe">
    <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <h2 id="titulo-tabela-equipe" class="font-semibold text-marinho-900">Minha equipe</h2>
        <label class="relative block sm:w-72">
            <span class="sr-only">Filtrar equipe</span>
            <input type="search" data-filtro-tabela="#tabela-equipe" placeholder="Filtrar por nome, cargo, projeto ou status"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
        </label>
    </div>

    <?php if (!$linhas): ?>
        <div class="p-5"><?= estadoVazio('Nenhum colaborador vinculado', 'Quando o RH vincular colaboradores a você, eles aparecerão aqui.', 'equipe') ?></div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table id="tabela-equipe" class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Nome</th>
                        <th scope="col" class="px-5 py-3">Cargo</th>
                        <th scope="col" class="px-5 py-3">Projeto atual</th>
                        <th scope="col" class="min-w-[160px] px-5 py-3">Progresso médio</th>
                        <th scope="col" class="px-5 py-3">Próximo prazo</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3">Risco</th>
                        <?php if ($exibirSatisfacaoIndividual): ?><th scope="col" class="px-5 py-3">Satisfação</th><?php endif; ?>
                        <th scope="col" class="px-5 py-3">Última atualização</th>
                        <th scope="col" class="px-5 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($linhas as ['membro' => $membro, 'resumo' => $resumo, 'risco' => $risco]):
                        $projetos = $resumo['projetos']['atuais'];
                        $temValidacao = in_array('Aguardando validação', array_column($resumo['pdis'], 'status_efetivo'), true);
                        $satisfacao = $resumo['ultimo_checkin']['satisfacao_empresa'] ?? '';
                        $linkPerfil = url('perfil.php?email=' . rawurlencode($membro['email']));
                        ?>
                        <tr data-linha class="hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-xs font-semibold text-blue-800"><?= e(iniciais($membro['nome_completo'])) ?></span>
                                    <div>
                                        <p class="font-medium text-slate-900"><?= e($membro['nome_completo']) ?></p>
                                        <p class="text-xs text-slate-500"><?= e($membro['email']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600"><?= e($membro['cargo']) ?></td>
                            <td class="px-5 py-3 text-slate-600">
                                <?= $projetos ? e($projetos[0]['nome_projeto']) . (count($projetos) > 1 ? ' <span class="text-xs text-slate-400">+' . (count($projetos) - 1) . '</span>' : '') : '<span class="text-slate-400">—</span>' ?>
                            </td>
                            <td class="px-5 py-3"><?= $resumo['progresso_medio'] === null ? '<span class="text-slate-400">—</span>' : barraProgresso($resumo['progresso_medio']) ?></td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600"><?= e($resumo['proximo_prazo'] ? formatarData($resumo['proximo_prazo']['prazo']) : '—') ?></td>
                            <td class="px-5 py-3"><?= badgeStatus($resumo['status_geral']) ?></td>
                            <td class="px-5 py-3" title="<?= e(implode(' ', $risco['recomendacoes'])) ?>"><?= badgeRisco($risco['classificacao']) ?></td>
                            <?php if ($exibirSatisfacaoIndividual): ?>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600"><?= $satisfacao !== '' ? e($satisfacao . '/5') : '<span class="text-slate-400">—</span>' ?></td>
                            <?php endif; ?>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">
                                <?= e(formatarData($resumo['ultima_atualizacao'])) ?>
                                <?php if (($resumo['dias_sem_atualizacao'] ?? 0) > $limiteDias): ?>
                                    <span class="ml-1 text-xs font-medium text-amber-700">(<?= e(textoRelativoDias($resumo['dias_sem_atualizacao'])) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <?php if ($perfilDisponivel): ?>
                                    <div class="inline-flex gap-1">
                                        <a href="<?= e($linkPerfil) ?>" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-blue-700" title="Ver perfil" aria-label="Ver perfil de <?= e($membro['nome_completo']) ?>"><?= icone('olho', 'h-4 w-4') ?></a>
                                        <a href="<?= e($linkPerfil . '#comentarios') ?>" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-blue-700" title="Adicionar comentário" aria-label="Comentar sobre <?= e($membro['nome_completo']) ?>"><?= icone('chat', 'h-4 w-4') ?></a>
                                        <a href="<?= e($linkPerfil . '#pdis') ?>" class="rounded-lg p-2 <?= $temValidacao ? 'text-indigo-600 hover:bg-indigo-50' : 'text-slate-300 pointer-events-none' ?>" title="Validar meta" aria-label="Validar meta de <?= e($membro['nome_completo']) ?>" <?= $temValidacao ? '' : 'aria-disabled="true" tabindex="-1"' ?>><?= icone('check', 'h-4 w-4') ?></a>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400" title="Tela de perfil em desenvolvimento">Em breve</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2" aria-labelledby="titulo-alertas">
        <h2 id="titulo-alertas" class="flex items-center gap-2 font-semibold text-marinho-900"><?= icone('sino', 'h-5 w-5 text-blue-700') ?> Alertas da equipe</h2>
        <?php if (!$alertas): ?>
            <p class="mt-3 text-sm text-slate-500">Nenhum alerta no momento. Bom trabalho!</p>
        <?php else: ?>
            <ul class="mt-4 divide-y divide-slate-100">
                <?php foreach ($alertas as $alerta): ?>
                    <li class="flex gap-3 py-3">
                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg <?= $tonsAlerta[$alerta['tom']] ?>"><?= icone($alerta['icone'], 'h-4 w-4') ?></span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900"><?= e($alerta['titulo']) ?></p>
                            <p class="text-sm text-slate-600"><?= e($alerta['texto']) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <aside class="space-y-4">
        <?= avisoConfidencialidade('Indicadores de bem-estar são exibidos apenas de forma agregada e anônima. Respostas individuais de bem-estar são restritas ao RH e nunca aparecem nesta tela.') ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
            <p class="font-semibold text-marinho-900">Como ler o risco</p>
            <p class="mt-2">A classificação combina percepção do colaborador, satisfação e andamento do PDI. Use as recomendações (passe o mouse sobre a etiqueta) para orientar conversas individuais.</p>
        </div>
    </aside>
</div>
