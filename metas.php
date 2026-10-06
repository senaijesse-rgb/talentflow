<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$usuario = exigirLogin();
$pdis = pdisDoColaborador($usuario['email']);
usort($pdis, static fn ($a, $b) => strcmp((string) $a['prazo'], (string) $b['prazo']));
$tituloPagina = 'Minhas metas';
$classeCampo = 'mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20';
$hoje = (new DateTimeImmutable('today'))->format('Y-m-d');

require __DIR__ . '/includes/header.php';
?>
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-2xl font-semibold text-marinho-900">Minhas metas</h2>
        <p class="text-sm text-slate-500">Crie metas do seu PDI e acompanhe prazo, progresso e status.</p>
    </div>
    <a href="<?= e(url('pdi.php')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"><?= icone('editar', 'h-4 w-4') ?> Atualizar uma meta</a>
</div>

<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="titulo-nova-meta">
    <h2 id="titulo-nova-meta" class="font-semibold text-marinho-900">Nova meta</h2>
    <form method="post" action="<?= e(url('actions/salvar_meta.php')) ?>" class="mt-4 grid gap-4 sm:grid-cols-2" data-loading="Salvando...">
        <?= csrfCampo() ?>
        <label class="block text-sm font-medium text-slate-700">Competência
            <input name="competencia" required minlength="2" maxlength="80" class="<?= $classeCampo ?>" placeholder="Ex.: Comunicação">
        </label>
        <label class="block text-sm font-medium text-slate-700">Prazo
            <input name="prazo" type="date" required min="<?= e($hoje) ?>" class="<?= $classeCampo ?>">
        </label>
        <label class="block text-sm font-medium text-slate-700 sm:col-span-2">Meta
            <textarea name="meta" required minlength="8" maxlength="400" rows="3" class="<?= $classeCampo ?>" placeholder="Descreva o que você quer desenvolver."></textarea>
        </label>
        <div class="sm:col-span-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800"><?= icone('prancheta', 'h-4 w-4') ?> Adicionar meta</button>
        </div>
    </form>
</section>

<section class="rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-minhas-metas">
    <h2 id="titulo-minhas-metas" class="sr-only">Lista de metas</h2>
    <?php if (!$pdis): ?>
        <div class="p-5"><?= estadoVazio('Nenhuma meta cadastrada', 'Use o formulário acima para incluir a primeira meta do seu PDI.', 'prancheta') ?></div>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($pdis as $pdi):
                $status = statusEfetivo($pdi);
                ?>
                <li class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700"><?= e($pdi['competencia']) ?></p>
                            <p class="mt-1 font-medium text-slate-900"><?= e($pdi['meta']) ?></p>
                        </div>
                        <?= badgeStatus($status) ?>
                    </div>
                    <div class="mt-3"><?= barraProgresso((float) $pdi['percentual_conclusao']) ?></div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                        <span>Prazo: <?= e(formatarData($pdi['prazo'])) ?></span>
                        <?php if ($status !== 'Concluído'): ?>
                            <a href="<?= e(url('pdi.php?id=' . rawurlencode($pdi['id_pdi']))) ?>" class="font-semibold text-blue-700 hover:text-blue-800">Atualizar meta →</a>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php';