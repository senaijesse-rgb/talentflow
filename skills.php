<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$usuario = exigirLogin();
$porCompetencia = [];
foreach (pdisDoColaborador($usuario['email']) as $pdi) {
    $nome = trim((string) ($pdi['competencia'] ?? ''));
    if ($nome === '') {
        $nome = 'Sem competência informada';
    }
    $porCompetencia[$nome][] = $pdi + ['status_efetivo' => statusEfetivo($pdi)];
}
ksort($porCompetencia);
$tituloPagina = 'Minhas skills';

require __DIR__ . '/includes/header.php';
?>
<div class="mb-6">
    <h2 class="text-2xl font-semibold text-marinho-900">Minhas skills</h2>
    <p class="text-sm text-slate-500">Competências ligadas às metas do seu PDI.</p>
</div>

<section class="rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-skills">
    <h2 id="titulo-skills" class="sr-only">Competências</h2>
    <?php if (!$porCompetencia): ?>
        <div class="p-5"><?= estadoVazio('Nenhuma skill no PDI', 'As competências aparecem aqui quando houver metas cadastradas para você.', 'check') ?></div>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($porCompetencia as $competencia => $metas): ?>
                <li class="p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-medium text-slate-900"><?= e($competencia) ?></p>
                        <span class="text-xs text-slate-500"><?= count($metas) ?> meta<?= count($metas) === 1 ? '' : 's' ?></span>
                    </div>
                    <ul class="mt-3 space-y-2">
                        <?php foreach ($metas as $meta): ?>
                            <li class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                <span class="text-slate-700"><?= e($meta['meta']) ?></span>
                                <?= badgeStatus($meta['status_efetivo']) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php';