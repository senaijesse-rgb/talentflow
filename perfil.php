<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$usuario = exigirLogin();
$emailAlvo = normalizarEmail((string) ($_GET['email'] ?? $usuario['email']));
exigirAcessoColaborador($emailAlvo);

$colaborador = buscarUsuario($emailAlvo);
if ($colaborador === null || (!boolValor($colaborador['ativo']) && !ehAdmin())) {
    flash('erro', 'Colaborador não encontrado ou inativo.');
    redirect('dashboard.php');
}

$ehProprio = $emailAlvo === $usuario['email'];
$podeGerenciar = podeGerenciarMetasDe($emailAlvo);
$veBemEstar = podeVerBemEstarIndividual();
$veSatisfacao = podeVerSatisfacaoIndividual($emailAlvo);
$resumo = resumoColaborador($colaborador);
$projetos = $resumo['projetos'];
$atividades = atividadesRecentes($emailAlvo, 30, $podeGerenciar);
$comentarios = comentariosDoColaborador($emailAlvo, !$podeGerenciar);
$checkins = array_map(
    static fn (array $c) => filtrarCheckinParaUsuario($c),
    checkinsDoColaborador($emailAlvo)
);
$nomeGestor = ($colaborador['gestor_email'] ?? '') !== '' ? nomeUsuario($colaborador['gestor_email']) : 'Não definido';
$visaoRisco = ehAdmin() ? $resumo['risco'] : (ehGestor() ? RiskService::visaoGestor($resumo['risco']) : null);

if ($veBemEstar) {
    registrarLog(
        'acesso_bem_estar_individual',
        'Checkins_Clima',
        $emailAlvo,
        'Consulta à área restrita de bem-estar individual.'
    );
}

$tituloPagina = $ehProprio ? 'Meu perfil' : 'Perfil de ' . $colaborador['nome_completo'];
$classeCampo = 'mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20';
$voltar = $ehProprio ? 'dashboard.php' : 'dashboard.php';

require __DIR__ . '/includes/header.php';
?>
<div class="mb-6">
    <a href="<?= e(url($voltar)) ?>" class="text-sm font-medium text-blue-700 hover:text-blue-800">← Voltar ao dashboard</a>
    <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex items-center gap-4">
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-marinho-800 text-lg font-semibold text-white"><?= e(iniciais($colaborador['nome_completo'])) ?></span>
            <div>
                <h2 class="text-2xl font-semibold text-marinho-900"><?= e($colaborador['nome_completo']) ?></h2>
                <p class="text-sm text-slate-500"><?= e($colaborador['cargo']) ?> · <?= e($colaborador['area']) ?></p>
                <p class="text-sm text-slate-500"><?= e($colaborador['email']) ?> · <?= e(rotuloPerfil($colaborador['perfil'])) ?></p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?= boolValor($colaborador['ativo']) ? '<span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">Ativo</span>' : '<span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/20">Inativo</span>' ?>
            <?php if ($visaoRisco): ?><?= badgeRisco($visaoRisco['classificacao']) ?><?php endif; ?>
            <?php if ($ehProprio && in_array($usuario['perfil'], ['colaborador', 'gestor'], true)): ?>
                <a href="<?= e(url('pdi.php')) ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-marinho-800 px-3 py-2 text-sm font-semibold text-white hover:bg-marinho-900"><?= icone('editar', 'h-4 w-4') ?> Atualizar PDI</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumo do perfil">
    <?= cardKpi('Cargo', (string) $colaborador['cargo'], 'Definido pelo RH', 'usuario', 'marinho') ?>
    <?= cardKpi('Gestor responsável', primeiroNome($nomeGestor), $colaborador['gestor_email'] !== '' ? $colaborador['gestor_email'] : 'Sem vínculo', 'equipe', 'azul') ?>
    <?= cardKpi('Progresso do PDI', $resumo['progresso_medio'] === null ? '—' : round($resumo['progresso_medio']) . '%', $resumo['concluidas'] . ' de ' . $resumo['total'] . ' metas concluídas', 'tendencia', 'verde') ?>
    <?php if (ehAdmin()): ?>
        <?= cardKpi('Score de risco', (string) $resumo['risco']['score'], $resumo['risco']['rotulo'], 'escudo', $resumo['risco']['classificacao'] === 'BAIXO' ? 'verde' : ($resumo['risco']['classificacao'] === 'MEDIO' ? 'amarelo' : 'vermelho')) ?>
    <?php else: ?>
        <?= cardKpi('Projetos atuais', (string) count($projetos['atuais']), $projetos['atuais'] ? implode(', ', array_column($projetos['atuais'], 'nome_projeto')) : 'Sem projeto ativo', 'pasta', 'cinza') ?>
    <?php endif; ?>
</section>

<p class="mt-4 text-sm text-slate-500">Cargo, gestor e regras não são alterados por esta tela.</p>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section id="pdis" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2" aria-labelledby="titulo-pdis">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 id="titulo-pdis" class="font-semibold text-marinho-900">Metas do PDI</h2>
        </div>
        <?php if (!$resumo['pdis']): ?>
            <div class="p-5"><?= estadoVazio('Nenhuma meta cadastrada', 'As metas de desenvolvimento aparecem aqui quando o PDI for definido.', 'prancheta') ?></div>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($resumo['pdis'] as $pdi): ?>
                    <li class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700"><?= e($pdi['competencia']) ?></p>
                                <p class="mt-1 font-medium text-slate-900"><?= e($pdi['meta']) ?></p>
                            </div>
                            <?= badgeStatus($pdi['status_efetivo']) ?>
                        </div>
                        <div class="mt-3"><?= barraProgresso((float) $pdi['percentual_conclusao']) ?></div>
                        <p class="mt-2 text-xs text-slate-500">Prazo: <?= e(formatarData($pdi['prazo'])) ?> · Atualizado em <?= e(formatarData($pdi['data_ultima_atualizacao'] ?: $pdi['data_inicio'])) ?></p>
                        <?php if ($podeGerenciar && $pdi['status'] === 'Aguardando validação'): ?>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <form method="post" action="<?= e(url('actions/validar_meta.php')) ?>">
                                    <?= csrfCampo() ?>
                                    <input type="hidden" name="email" value="<?= e($emailAlvo) ?>">
                                    <input type="hidden" name="id_pdi" value="<?= e($pdi['id_pdi']) ?>">
                                    <input type="hidden" name="decisao" value="concluir">
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800"><?= icone('check', 'h-4 w-4') ?> Validar como concluída</button>
                                </form>
                                <form method="post" action="<?= e(url('actions/validar_meta.php')) ?>">
                                    <?= csrfCampo() ?>
                                    <input type="hidden" name="email" value="<?= e($emailAlvo) ?>">
                                    <input type="hidden" name="id_pdi" value="<?= e($pdi['id_pdi']) ?>">
                                    <input type="hidden" name="decisao" value="devolver">
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Devolver para ajuste</button>
                                </form>
                            </div>
                        <?php elseif ($ehProprio && $pdi['status_efetivo'] !== 'Concluído'): ?>
                            <a href="<?= e(url('pdi.php?id=' . rawurlencode($pdi['id_pdi']))) ?>" class="mt-3 inline-block text-sm font-semibold text-blue-700 hover:text-blue-800">Atualizar esta meta →</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="titulo-projetos">
        <h2 id="titulo-projetos" class="font-semibold text-marinho-900">Projetos</h2>
        <?php if (!$projetos['atuais'] && !$projetos['anteriores']): ?>
            <div class="mt-4"><?= estadoVazio('Sem projetos', 'Nenhum vínculo de projeto foi encontrado para este perfil.', 'pasta') ?></div>
        <?php else: ?>
            <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Atuais</h3>
            <ul class="mt-2 space-y-2">
                <?php foreach ($projetos['atuais'] as $projeto): ?>
                    <li class="rounded-lg bg-slate-50 px-3 py-2">
                        <p class="text-sm font-medium text-slate-900"><?= e($projeto['nome_projeto']) ?></p>
                        <p class="text-xs text-slate-500"><?= e($projeto['papel_no_projeto']) ?> · desde <?= e(formatarData($projeto['vinculo_inicio'])) ?></p>
                    </li>
                <?php endforeach; ?>
                <?php if (!$projetos['atuais']): ?><li class="text-sm text-slate-500">Nenhum projeto ativo.</li><?php endif; ?>
            </ul>
            <?php if ($projetos['anteriores']): ?>
                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Anteriores</h3>
                <ul class="mt-2 space-y-2">
                    <?php foreach ($projetos['anteriores'] as $projeto): ?>
                        <li class="text-sm text-slate-600"><?= e($projeto['nome_projeto']) ?> <span class="text-xs text-slate-400">(<?= e($projeto['status_participacao']) ?>)</span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section id="linha-do-tempo" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2" aria-labelledby="titulo-linha">
        <h2 id="titulo-linha" class="font-semibold text-marinho-900">Linha do tempo</h2>
        <?php if (!$atividades): ?>
            <div class="mt-4"><?= estadoVazio('Sem histórico', 'Atualizações de PDI, comentários e check-ins aparecem aqui.', 'relogio') ?></div>
        <?php else: ?>
            <ol class="relative mt-5 space-y-5 border-l border-slate-200 pl-5">
                <?php foreach ($atividades as $atividade): ?>
                    <li>
                        <span class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-blue-50 text-blue-700 ring-4 ring-white"><?= icone($atividade['icone'], 'h-3.5 w-3.5') ?></span>
                        <p class="text-sm font-medium text-slate-900"><?= e($atividade['titulo']) ?></p>
                        <p class="text-sm text-slate-600"><?= e($atividade['texto']) ?></p>
                        <?php if ($atividade['detalhe'] !== ''): ?><p class="text-xs text-slate-500"><?= e($atividade['detalhe']) ?></p><?php endif; ?>
                        <time class="text-xs text-slate-400" datetime="<?= e($atividade['data']) ?>"><?= e(formatarData($atividade['data'], true)) ?></time>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <section id="comentarios" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="titulo-comentarios">
        <h2 id="titulo-comentarios" class="font-semibold text-marinho-900">Comentários do gestor</h2>
        <?php if ($podeGerenciar): ?>
            <form method="post" action="<?= e(url('actions/salvar_comentario.php')) ?>" class="mt-4 space-y-3" data-loading="Salvando...">
                <?= csrfCampo() ?>
                <input type="hidden" name="email" value="<?= e($emailAlvo) ?>">
                <div>
                    <label for="tipo_comentario" class="block text-sm font-medium text-slate-700">Tipo</label>
                    <select id="tipo_comentario" name="tipo_comentario" class="<?= $classeCampo ?>">
                        <?php foreach (['Feedback', 'Acompanhamento', 'Reconhecimento'] as $tipo): ?>
                            <option value="<?= e($tipo) ?>"><?= e($tipo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="comentario" class="block text-sm font-medium text-slate-700">Comentário</label>
                    <textarea id="comentario" name="comentario" required maxlength="1000" rows="4" class="<?= $classeCampo ?>" placeholder="Registre um retorno sobre o desenvolvimento."></textarea>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="visivel_colaborador" value="sim" checked class="h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-600">
                    Visível para o colaborador
                </label>
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800"><?= icone('chat', 'h-4 w-4') ?> Publicar comentário</button>
            </form>
        <?php endif; ?>
        <?php if (!$comentarios): ?>
            <p class="mt-4 text-sm text-slate-500">Nenhum comentário registrado.</p>
        <?php else: ?>
            <ul class="mt-4 space-y-3">
                <?php foreach ($comentarios as $comentario): ?>
                    <li class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e($comentario['tipo_comentario']) ?> · <?= e(nomeUsuario($comentario['gestor_email'])) ?></p>
                        <p class="mt-1 text-sm text-slate-800"><?= e($comentario['comentario']) ?></p>
                        <p class="mt-1 text-xs text-slate-400"><?= e(formatarData($comentario['data_comentario'], true)) ?><?= boolValor($comentario['visivel_colaborador']) ? '' : ' · oculto do colaborador' ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?php if ($veBemEstar): ?>
<section id="bem-estar" class="mt-6 scroll-mt-24 rounded-2xl border border-amber-200 bg-white shadow-sm" aria-labelledby="titulo-bem-estar">
    <div class="border-b border-amber-100 px-5 py-4">
        <h2 id="titulo-bem-estar" class="flex items-center gap-2 font-semibold text-marinho-900"><?= icone('cadeado', 'h-5 w-5 text-amber-700') ?> Bem-estar individual</h2>
        <p class="mt-1 text-sm text-slate-500">Área restrita ao Administrador RH. Esta consulta foi registrada na auditoria, sem gravar a nota no log.</p>
    </div>
    <?php if (!$checkins): ?>
        <div class="p-5"><?= estadoVazio('Sem check-ins', 'Nenhuma resposta individual de bem-estar foi registrada.', 'cadeado') ?></div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-amber-50/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Data</th>
                        <th class="px-5 py-3">Bem-estar</th>
                        <th class="px-5 py-3">Satisfação</th>
                        <th class="px-5 py-3">Risco de saída</th>
                        <th class="px-5 py-3">Comentário</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($checkins as $checkin):
                        $bem = $checkin['bem_estar_trabalho'] ?? '';
                        ?>
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600"><?= e(formatarData($checkin['data_checkin'], true)) ?></td>
                            <td class="px-5 py-3"><?= $bem === '' ? 'Prefiro não responder' : e($bem . '/5 · ' . (ESCALA_BEM_ESTAR[(int) $bem] ?? '')) ?></td>
                            <td class="px-5 py-3"><?= ($checkin['satisfacao_empresa'] ?? '') === '' ? '—' : e($checkin['satisfacao_empresa'] . '/5') ?></td>
                            <td class="px-5 py-3"><?= ($checkin['risco_saida_percebido'] ?? '') === '' ? '—' : e($checkin['risco_saida_percebido'] . '/5') ?></td>
                            <td class="px-5 py-3 text-slate-600"><?= e($checkin['comentario'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php elseif ($veSatisfacao && $checkins): ?>
<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="titulo-checkins">
    <h2 id="titulo-checkins" class="font-semibold text-marinho-900">Check-ins de experiência</h2>
    <ul class="mt-4 divide-y divide-slate-100">
        <?php foreach (array_slice($checkins, 0, 6) as $checkin): ?>
            <li class="py-3 text-sm text-slate-600">
                <span class="font-medium text-slate-900"><?= e(formatarData($checkin['data_checkin'])) ?></span>
                <?php if (($checkin['satisfacao_empresa'] ?? '') !== ''): ?>
                    · Satisfação <?= e($checkin['satisfacao_empresa']) ?>/5
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<div class="mt-6">
    <?= avisoConfidencialidade('O indicador individual de bem-estar no trabalho fica restrito ao Administrador RH. Gestores veem apenas médias da equipe, quando há respostas suficientes para não identificar uma pessoa.') ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
