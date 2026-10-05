<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$usuario = exigirLogin();
$pdis = pdisDoColaborador($usuario['email']);
usort($pdis, static fn ($a, $b) => strcmp((string) $a['prazo'], (string) $b['prazo']));
$tituloPagina = 'Minhas metas';

require __DIR__ . '/includes/header.php';
?>
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h2 class="text-2xl font-semibold text-marinho-900">Minhas metas</h2>
        <p class="text-sm text-slate-500">Metas do seu PDI, com prazo, progresso e status.</p>
    </div>
    <a href="<?= e(url('pdi.php')) ?>" class="inline-flex items-center gap-2 rounded-lg bg-marinho-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-marinho-900"><?= icone('editar', 'h-4 w-4') ?> Atualizar uma meta</a>
</div>

<section class="rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-minhas-metas">
    <h2 id="titulo-minhas-metas" class="sr-only">Lista de metas</h2>
    <?php if (!$pdis): ?>
        <div class="p-5"><?= estadoVazio('Nenhuma meta cadastrada', 'Quando o RH ou o seu gestor registrar metas no seu PDI, elas aparecem aqui.', 'prancheta') ?></div>
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