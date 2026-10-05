<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$usuario = exigirLogin();
$emailAlvo = normalizarEmail((string) ($_GET['email'] ?? $usuario['email']));
exigirAcessoColaborador($emailAlvo);

$colaborador = buscarUsuario($emailAlvo);
if ($colaborador === null || !boolValor($colaborador['ativo'])) {
    flash('erro', 'Colaborador não encontrado ou inativo.');
    redirect('dashboard.php');
}

$ehProprio = $emailAlvo === $usuario['email'];
$pdisEditaveis = array_values(array_filter(
    pdisDoColaborador($emailAlvo),
    static fn ($p) => statusEfetivo($p) !== 'Concluído'
));

$idSolicitado = (string) ($_GET['id'] ?? '');
$selecionado = null;
foreach ($pdisEditaveis as $pdi) {
    if ($pdi['id_pdi'] === $idSolicitado) {
        $selecionado = $pdi;
    }
}
$selecionado ??= $pdisEditaveis[0] ?? null;

$projetos = projetosParaFormulario($emailAlvo);
$focoDificuldade = ($_GET['foco'] ?? '') === 'dificuldade';
$historicoMeta = $selecionado
    ? array_slice(array_values(array_filter(atualizacoesDoColaborador($emailAlvo), static fn ($a) => $a['id_pdi'] === $selecionado['id_pdi'])), 0, 5)
    : [];

$tituloPagina = $ehProprio ? 'Atualizar meu PDI' : 'Atualizar PDI de ' . primeiroNome($colaborador['nome_completo']);
$classeCampo = 'mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20';
$classeBloqueado = 'mt-1.5 block w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-600';

require __DIR__ . '/includes/header.php';
?>
<div class="mx-auto max-w-5xl">
    <div class="mb-6">
        <a href="<?= e(url('dashboard.php')) ?>" class="text-sm font-medium text-blue-700 hover:text-blue-800">← Voltar ao dashboard</a>
        <h2 class="mt-2 text-2xl font-semibold text-marinho-900"><?= e($tituloPagina) ?></h2>
        <p class="text-sm text-slate-500">Registre seu progresso, dificuldades e o andamento das metas de desenvolvimento.</p>
    </div>

    <?php if (!$pdisEditaveis): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <?= estadoVazio('Nenhuma meta em aberto', 'Não há metas ativas para atualizar. Converse com o gestor para definir novas metas de desenvolvimento.', 'prancheta') ?>
        </div>
    <?php else: ?>
        <div class="grid gap-6 lg:grid-cols-3">
            <form method="post" action="<?= e(url('actions/salvar_pdi.php')) ?>" class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2" data-loading="Salvando...">
                <?= csrfCampo() ?>
                <input type="hidden" name="email" value="<?= e($emailAlvo) ?>">

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="email_exibicao" class="block text-sm font-medium text-slate-700">E-mail do colaborador</label>
                        <input id="email_exibicao" type="email" value="<?= e($colaborador['email']) ?>" class="<?= $classeBloqueado ?>" readonly aria-readonly="true">
                    </div>
                    <div>
                        <label for="nome_exibicao" class="block text-sm font-medium text-slate-700">Nome completo</label>
                        <input id="nome_exibicao" type="text" value="<?= e($colaborador['nome_completo']) ?>" class="<?= $classeBloqueado ?>" readonly aria-readonly="true">
                    </div>
                </div>

                <div>
                    <label for="id_pdi" class="block text-sm font-medium text-slate-700">Meta do PDI <span class="text-red-600">*</span></label>
                    <select id="id_pdi" name="id_pdi" required class="<?= $classeCampo ?>">
                        <?php foreach ($pdisEditaveis as $pdi): ?>
                            <option value="<?= e($pdi['id_pdi']) ?>"
                                    data-competencia="<?= e($pdi['competencia']) ?>"
                                    data-meta="<?= e($pdi['meta']) ?>"
                                    data-percentual="<?= (int) $pdi['percentual_conclusao'] ?>"
                                    data-status="<?= e($pdi['status']) ?>"
                                    data-prazo="<?= e($pdi['prazo']) ?>"
                                <?= $pdi['id_pdi'] === $selecionado['id_pdi'] ? 'selected' : '' ?>>
                                <?= e($pdi['competencia'] . ' — ' . resumirTexto($pdi['meta'], 70)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="projeto" class="block text-sm font-medium text-slate-700">Projeto atual ou relacionado</label>
                        <select id="projeto" name="id_projeto" class="<?= $classeCampo ?>">
                            <option value="">Não relacionado a projeto</option>
                            <?php foreach ($projetos as $projeto): ?>
                                <option value="<?= e($projeto['id_projeto']) ?>"><?= e($projeto['nome_projeto']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="competencia" class="block text-sm font-medium text-slate-700">Competência</label>
                        <input id="competencia" type="text" value="<?= e($selecionado['competencia']) ?>" class="<?= $classeBloqueado ?>" readonly aria-readonly="true">
                    </div>
                </div>

                <div>
                    <label for="meta" class="block text-sm font-medium text-slate-700">Meta do PDI</label>
                    <textarea id="meta" rows="2" class="<?= $classeBloqueado ?>" readonly aria-readonly="true"><?= e($selecionado['meta']) ?></textarea>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="percentual" class="block text-sm font-medium text-slate-700">Percentual de conclusão <span class="text-red-600">*</span></label>
                        <div class="mt-1.5 flex items-center gap-3">
                            <input id="percentual_faixa" type="range" min="0" max="100" step="5" value="<?= (int) $selecionado['percentual_conclusao'] ?>" class="flex-1" aria-label="Ajustar percentual">
                            <div class="relative w-24">
                                <input id="percentual" name="percentual" type="number" min="0" max="100" step="1" required value="<?= (int) $selecionado['percentual_conclusao'] ?>"
                                       class="block w-full rounded-lg border border-slate-300 py-2 pl-3 pr-7 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-slate-400">%</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-slate-700">Status <span class="text-red-600">*</span></label>
                        <select id="status" name="status" required class="<?= $classeCampo ?>">
                            <?php foreach (STATUS_PDI as $status):
                                $bloqueado = $status === 'Concluído' && !podeGerenciarMetasDe($emailAlvo);
                                ?>
                                <option value="<?= e($status) ?>" <?= $selecionado['status'] === $status ? 'selected' : '' ?> <?= $bloqueado ? 'disabled' : '' ?>>
                                    <?= e($status) ?><?= $bloqueado ? ' (após validação do gestor)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Ao atingir 100%, a meta segue para validação do gestor.</p>
                    </div>
                </div>

                <div>
                    <label for="prazo" class="block text-sm font-medium text-slate-700">Data prevista de conclusão <span class="text-red-600">*</span></label>
                    <input id="prazo" name="prazo" type="date" required value="<?= e($selecionado['prazo']) ?>" class="<?= $classeCampo ?> sm:max-w-xs">
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <label for="dificuldade" class="block text-sm font-medium text-slate-700">Dificuldade atual <span class="font-normal text-slate-400">(opcional)</span></label>
                        <span id="contador-dificuldade" class="text-xs text-slate-400" aria-live="polite"></span>
                    </div>
                    <textarea id="dificuldade" name="dificuldade" rows="4" maxlength="1000" data-contador="#contador-dificuldade" <?= $focoDificuldade ? 'data-foco-inicial' : '' ?>
                              class="<?= $classeCampo ?>" placeholder="Ex.: falta de tempo na agenda, necessidade de apoio técnico, dependência de outra equipe..."></textarea>
                    <p class="mt-1 text-xs text-slate-500">Descreva apenas aspectos profissionais. Não informe dados de saúde ou informações pessoais sensíveis.</p>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                    <a href="<?= e(url('dashboard.php')) ?>" class="inline-flex justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 disabled:opacity-70">
                        <?= icone('check', 'h-4 w-4') ?> Salvar atualização
                    </button>
                </div>
            </form>

            <aside class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-marinho-900">Histórico desta meta</h3>
                    <?php if (!$historicoMeta): ?>
                        <p class="mt-3 text-sm text-slate-500">Nenhuma atualização registrada para esta meta.</p>
                    <?php else: ?>
                        <ol class="mt-4 space-y-4">
                            <?php foreach ($historicoMeta as $item): ?>
                                <li class="border-l-2 border-blue-200 pl-3">
                                    <p class="text-sm font-medium text-slate-800"><?= (int) $item['percentual_anterior'] ?>% → <?= (int) $item['percentual_novo'] ?>%</p>
                                    <p class="text-xs text-slate-500"><?= e($item['status_novo']) ?> · <?= e(formatarData($item['data_atualizacao'], true)) ?></p>
                                    <?php if ($item['dificuldade'] !== ''): ?>
                                        <p class="mt-1 text-xs text-slate-600">Dificuldade: <?= e(resumirTexto($item['dificuldade'], 100)) ?></p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
                    <h3 class="font-semibold text-marinho-900">Dicas para uma boa atualização</h3>
                    <ul class="mt-3 list-disc space-y-1.5 pl-5">
                        <li>Registre avanços, mesmo que pequenos.</li>
                        <li>Use "Atenção" quando houver risco de não cumprir o prazo.</li>
                        <li>Informe dificuldades para que seu gestor possa apoiar.</li>
                    </ul>
                </section>
            </aside>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
