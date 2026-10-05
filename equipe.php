<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$usuario = exigirPerfil('gestor');
$tituloPagina = 'Minha equipe';
$equipe = equipeDoGestor($usuario['email']);
$limiteDias = (int) regra('dias_sem_atualizacao', 14);
$exibirSatisfacao = boolValor(regra('politica_exibir_satisfacao_gestor', 'nao'));
$linhas = [];

foreach ($equipe as $membro) {
    $resumo = resumoColaborador($membro);
    $linhas[] = [
        'membro' => $membro,
        'resumo' => $resumo,
        'risco' => RiskService::visaoGestor($resumo['risco']),
    ];
}

require __DIR__ . '/includes/header.php';
?>
<div class="mb-6">
    <h2 class="text-2xl font-semibold text-marinho-900">Minha equipe</h2>
    <p class="text-sm text-slate-500">Colaboradores vinculados a você. Bem-estar individual não aparece nesta tela.</p>
</div>

<section class="mb-6 grid gap-4 sm:grid-cols-3" aria-label="Resumo da equipe">
    <?= cardKpi('Pessoas', (string) count($equipe), 'Vinculadas ao seu e-mail', 'equipe', 'marinho') ?>
    <?= cardKpi('Metas em aberto', (string) array_sum(array_map(static fn ($l) => count($l['resumo']['abertos']), $linhas)), 'Ainda não concluídas', 'prancheta', 'azul') ?>
    <?= cardKpi('Risco alto ou crítico', (string) count(array_filter($linhas, static fn ($l) => in_array($l['risco']['classificacao'], ['ALTO', 'CRITICO'], true))), 'Sem nota de bem-estar', 'bandeira', 'vermelho') ?>
</section>

<section class="rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-equipe">
    <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <h2 id="titulo-equipe" class="font-semibold text-marinho-900">Colaboradores</h2>
        <label class="relative block sm:w-72">
            <span class="sr-only">Filtrar equipe</span>
            <input type="search" data-filtro-tabela="#tabela-equipe" placeholder="Filtrar por nome, cargo ou projeto" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
        </label>
    </div>
    <?php if (!$linhas): ?>
        <div class="p-5"><?= estadoVazio('Nenhum colaborador vinculado', 'Quando o RH definir você como gestor, as pessoas aparecem aqui.', 'equipe') ?></div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table id="tabela-equipe" class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Nome</th>
                        <th class="px-5 py-3">Cargo</th>
                        <th class="px-5 py-3">Projeto atual</th>
                        <th class="px-5 py-3">Progresso</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Risco</th>
                        <?php if ($exibirSatisfacao): ?><th class="px-5 py-3">Satisfação</th><?php endif; ?>
                        <th class="px-5 py-3">Atualização</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($linhas as ['membro' => $membro, 'resumo' => $resumo, 'risco' => $risco]):
                        $projetos = $resumo['projetos']['atuais'];
                        ?>
                        <tr data-linha>
                            <td class="px-5 py-3">
                                <a href="<?= e(url('perfil.php?email=' . rawurlencode($membro['email']))) ?>" class="font-medium text-slate-900 hover:text-blue-700"><?= e($membro['nome_completo']) ?></a>
                                <p class="text-xs text-slate-500"><?= e($membro['email']) ?></p>
                            </td>
                            <td class="px-5 py-3 text-slate-600"><?= e($membro['cargo']) ?></td>
                            <td class="px-5 py-3 text-slate-600"><?= $projetos ? e($projetos[0]['nome_projeto']) : '—' ?></td>
                            <td class="px-5 py-3"><?= $resumo['progresso_medio'] === null ? '—' : barraProgresso($resumo['progresso_medio']) ?></td>
                            <td class="px-5 py-3"><?= badgeStatus($resumo['status_geral']) ?></td>
                            <td class="px-5 py-3"><?= badgeRisco($risco['classificacao']) ?></td>
                            <?php if ($exibirSatisfacao): ?>
                                <td class="px-5 py-3"><?= ($resumo['ultimo_checkin']['satisfacao_empresa'] ?? '') !== '' ? e($resumo['ultimo_checkin']['satisfacao_empresa'] . '/5') : '—' ?></td>
                            <?php endif; ?>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">
                                <?= e(formatarData($resumo['ultima_atualizacao'])) ?>
                                <?php if (($resumo['dias_sem_atualizacao'] ?? 0) > $limiteDias): ?>
                                    <span class="text-xs text-amber-700">(<?= e(textoRelativoDias($resumo['dias_sem_atualizacao'])) ?>)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="titulo-metas-equipe">
    <h2 id="titulo-metas-equipe" class="font-semibold text-marinho-900">Metas em aberto</h2>
    <?php
    $abertas = [];
    foreach ($linhas as ['membro' => $membro, 'resumo' => $resumo]) {
        foreach ($resumo['abertos'] as $pdi) {
            $abertas[] = $pdi + ['nome' => $membro['nome_completo'], 'email' => $membro['email']];
        }
    }
    ?>
    <?php if (!$abertas): ?>
        <p class="mt-3 text-sm text-slate-500">Nenhuma meta em aberto na equipe.</p>
    <?php else: ?>
        <ul class="mt-4 divide-y divide-slate-100">
            <?php foreach ($abertas as $pdi): ?>
                <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-900"><?= e($pdi['meta']) ?></p>
                        <p class="text-xs text-slate-500"><?= e($pdi['nome']) ?> · prazo <?= e(formatarData($pdi['prazo'])) ?></p>
                    </div>
                    <div class="flex items-center gap-3">
                        <?= badgeStatus($pdi['status_efetivo']) ?>
                        <a href="<?= e(url('pdi.php?email=' . rawurlencode($pdi['email']) . '&id=' . rawurlencode($pdi['id_pdi']))) ?>" class="text-sm font-medium text-blue-700 hover:text-blue-800">Abrir</a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<div class="mt-6">
    <?= avisoConfidencialidade('Indicadores de bem-estar da equipe, quando existem, ficam agregados no dashboard. Respostas individuais são exclusivas do RH.') ?>
</div>
<?php require __DIR__ . '/includes/footer.php';