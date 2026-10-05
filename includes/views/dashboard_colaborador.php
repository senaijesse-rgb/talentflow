<?php
/** @var array $usuario */
$registro = buscarUsuario($usuario['email']) ?? [];
$resumo = resumoColaborador($registro);
$nomeGestor = ($registro['gestor_email'] ?? '') !== '' ? nomeUsuario($registro['gestor_email']) : 'Não definido';
$ultimoCheckin = $resumo['ultimo_checkin'];
$satisfacao = ($ultimoCheckin && $ultimoCheckin['satisfacao_empresa'] !== '') ? (int) $ultimoCheckin['satisfacao_empresa'] : null;
$proximo = $resumo['proximo_prazo'];
$projetosAtuais = $resumo['projetos']['atuais'];
$lembretes = lembretesColaborador($resumo);
$atividades = atividadesRecentes($usuario['email'], 8);
$tonsLembrete = [
    'vermelho' => 'border-red-200 bg-red-50 text-red-900',
    'amarelo' => 'border-amber-200 bg-amber-50 text-amber-900',
    'azul' => 'border-blue-200 bg-blue-50 text-blue-900',
];
?>
<section class="overflow-hidden rounded-2xl bg-gradient-to-r from-marinho-900 to-blue-800 p-6 text-white shadow-sm sm:p-8">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm text-blue-200"><?= e(dataPorExtenso()) ?></p>
            <h2 class="mt-1 text-2xl font-semibold sm:text-3xl">Olá, <?= e(primeiroNome($usuario['nome'])) ?>!</h2>
            <p class="mt-2 text-sm text-blue-100"><?= e($registro['cargo'] ?? '') ?> · <?= e($registro['area'] ?? '') ?></p>
            <p class="text-sm text-blue-100">Gestor responsável: <span class="font-medium text-white"><?= e($nomeGestor) ?></span></p>
            <p class="mt-4 max-w-2xl rounded-xl bg-white/10 px-4 py-3 text-sm text-blue-50"><?= e(mensagemIncentivo($resumo)) ?></p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="<?= e(url('pdi.php')) ?>" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-marinho-900 shadow-sm hover:bg-blue-50">
                <?= icone('editar', 'h-4 w-4') ?> Atualizar meu PDI
            </a>
            <a href="<?= e(url('pdi.php?foco=dificuldade')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-white/30 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/10">
                <?= icone('bandeira', 'h-4 w-4') ?> Registrar dificuldade
            </a>
        </div>
    </div>
</section>

<section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="Indicadores pessoais">
    <?= cardKpi('Meu progresso no PDI', $resumo['progresso_medio'] === null ? '—' : round($resumo['progresso_medio']) . '%', 'Média das metas ativas', 'tendencia', 'azul') ?>
    <?= cardKpi('Metas concluídas', $resumo['concluidas'] . ' de ' . $resumo['total'], $resumo['total'] ? 'Metas do seu PDI atual' : 'Nenhuma meta cadastrada', 'check', 'verde') ?>
    <?= cardKpi('Projetos atuais', (string) count($projetosAtuais), $projetosAtuais ? implode(', ', array_column($projetosAtuais, 'nome_projeto')) : 'Sem projeto ativo', 'pasta', 'marinho') ?>
    <?= cardKpi('Próximo prazo', $proximo ? formatarData($proximo['prazo']) : '—', $proximo ? resumirTexto($proximo['meta'], 50) . ' (' . textoRelativoDias(diasDesde($proximo['prazo'])) . ')' : 'Sem prazos em aberto', 'calendario', $proximo && (diasAte($proximo['prazo']) ?? 99) <= (int) regra('dias_prazo_proximo', 7) ? 'amarelo' : 'cinza') ?>
    <?= cardKpi('Meu nível de satisfação', $satisfacao ? "{$satisfacao}/5" : 'Sem check-in', $satisfacao ? ESCALA_SATISFACAO[$satisfacao] . ' · ' . formatarData($ultimoCheckin['data_checkin']) : 'Faça seu check-in de experiência', 'sorriso', $satisfacao === null ? 'cinza' : ($satisfacao <= 2 ? 'vermelho' : ($satisfacao === 3 ? 'amarelo' : 'verde'))) ?>
    <?= cardKpi('Última atualização', $resumo['ultima_atualizacao'] ? formatarData($resumo['ultima_atualizacao']) : '—', $resumo['ultima_atualizacao'] ? ucfirst(textoRelativoDias($resumo['dias_sem_atualizacao'])) : 'Nenhuma atualização registrada', 'relogio', ($resumo['dias_sem_atualizacao'] ?? 0) > (int) regra('dias_sem_atualizacao', 14) ? 'amarelo' : 'cinza') ?>
</section>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2" aria-labelledby="titulo-metas">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 id="titulo-metas" class="font-semibold text-marinho-900">Metas ativas</h2>
            <span class="text-xs text-slate-500"><?= count($resumo['abertos']) ?> em aberto</span>
        </div>
        <?php if (!$resumo['abertos']): ?>
            <div class="p-5"><?= estadoVazio('Nenhuma meta em aberto', $resumo['total'] ? 'Todas as suas metas estão concluídas. Converse com seu gestor sobre os próximos desafios.' : 'Seu PDI ainda não possui metas. Converse com seu gestor para defini-las.', 'prancheta') ?></div>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($resumo['abertos'] as $pdi): ?>
                    <li class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700"><?= e($pdi['competencia']) ?></p>
                                <p class="mt-1 font-medium text-slate-900"><?= e($pdi['meta']) ?></p>
                            </div>
                            <?= badgeStatus($pdi['status_efetivo']) ?>
                        </div>
                        <div class="mt-3"><?= barraProgresso((float) $pdi['percentual_conclusao']) ?></div>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                            <span class="flex items-center gap-1"><?= icone('calendario', 'h-4 w-4') ?> Prazo: <?= e(formatarData($pdi['prazo'])) ?> (<?= e(textoRelativoDias(diasDesde($pdi['prazo']))) ?>)</span>
                            <span>Atualizado em <?= e(formatarData($pdi['data_ultima_atualizacao'] ?: $pdi['data_inicio'])) ?></span>
                            <a href="<?= e(url('pdi.php?id=' . rawurlencode($pdi['id_pdi']))) ?>" class="font-semibold text-blue-700 hover:text-blue-800">Atualizar meta →</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <div class="space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="titulo-lembretes">
            <h2 id="titulo-lembretes" class="flex items-center gap-2 font-semibold text-marinho-900"><?= icone('sino', 'h-5 w-5 text-blue-700') ?> Lembretes e planos de ação</h2>
            <?php if (!$lembretes): ?>
                <p class="mt-3 text-sm text-slate-500">Tudo em dia! Nenhum lembrete no momento.</p>
            <?php else: ?>
                <ul class="mt-4 space-y-2">
                    <?php foreach ($lembretes as $lembrete): ?>
                        <li class="rounded-lg border px-3 py-2 text-sm <?= $tonsLembrete[$lembrete['tom']] ?>"><?= e($lembrete['texto']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <a href="<?= e(url('checkin.php')) ?>" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-blue-700 hover:text-blue-800"><?= icone('sorriso', 'h-4 w-4') ?> Fazer check-in de experiência</a>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="titulo-atividades">
            <h2 id="titulo-atividades" class="font-semibold text-marinho-900">Atividades recentes</h2>
            <?php if (!$atividades): ?>
                <p class="mt-3 text-sm text-slate-500">Nenhuma atividade registrada ainda.</p>
            <?php else: ?>
                <ol class="relative mt-4 space-y-5 border-l border-slate-200 pl-5">
                    <?php foreach ($atividades as $atividade): ?>
                        <li>
                            <span class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-blue-50 text-blue-700 ring-4 ring-white"><?= icone($atividade['icone'], 'h-3.5 w-3.5') ?></span>
                            <p class="text-sm font-medium text-slate-900"><?= e($atividade['titulo']) ?></p>
                            <p class="text-sm text-slate-600"><?= e(resumirTexto($atividade['texto'], 120)) ?></p>
                            <?php if ($atividade['detalhe'] !== ''): ?><p class="text-xs text-slate-500"><?= e(resumirTexto($atividade['detalhe'], 70)) ?></p><?php endif; ?>
                            <time class="text-xs text-slate-400" datetime="<?= e($atividade['data']) ?>"><?= e(formatarData($atividade['data'], true)) ?></time>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>
</div>

<div class="mt-6">
    <?= avisoConfidencialidade('Suas respostas de bem-estar no trabalho são confidenciais: apenas o Administrador RH tem acesso individual. Seu gestor visualiza somente indicadores agregados da equipe, sem identificação.') ?>
</div>
