<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$usuario = exigirLogin();
$skills = db()->buscar('Skills_Colaborador', 'email_colaborador', $usuario['email']);
usort($skills, static fn ($a, $b) => strcmp($b['data_registro'], $a['data_registro']));

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
$classeCampo = 'mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20';
$niveis = ['Básico', 'Intermediário', 'Avançado'];

require __DIR__ . '/includes/header.php';
?>
<div class="mb-6">
    <h2 class="text-2xl font-semibold text-marinho-900">Minhas skills</h2>
    <p class="text-sm text-slate-500">Registre competências que você já pratica. As metas do PDI continuam listadas abaixo.</p>
</div>

<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="titulo-nova-skill">
    <h2 id="titulo-nova-skill" class="font-semibold text-marinho-900">Adicionar skill</h2>
    <form method="post" action="<?= e(url('actions/salvar_skill.php')) ?>" class="mt-4 grid gap-4 sm:grid-cols-2" data-loading="Salvando...">
        <?= csrfCampo() ?>
        <label class="block text-sm font-medium text-slate-700">Nome
            <input name="nome" required minlength="2" maxlength="80" class="<?= $classeCampo ?>" placeholder="Ex.: SQL">
        </label>
        <label class="block text-sm font-medium text-slate-700">Nível
            <select name="nivel" required class="<?= $classeCampo ?>">
                <option value="">Selecione...</option>
                <?php foreach ($niveis as $nivel): ?>
                    <option value="<?= e($nivel) ?>"><?= e($nivel) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block text-sm font-medium text-slate-700 sm:col-span-2">Evidência <span class="font-normal text-slate-400">(opcional)</span>
            <textarea name="evidencia" maxlength="500" rows="2" class="<?= $classeCampo ?>" placeholder="Projeto, curso ou entrega em que você usou esta skill."></textarea>
        </label>
        <div class="sm:col-span-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800"><?= icone('check', 'h-4 w-4') ?> Adicionar skill</button>
        </div>
    </form>
</section>

<section class="mb-6 rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-skills-cadastradas">
    <h2 id="titulo-skills-cadastradas" class="border-b border-slate-100 px-5 py-4 font-semibold text-marinho-900">Skills cadastradas</h2>
    <?php if (!$skills): ?>
        <div class="p-5"><?= estadoVazio('Nenhuma skill cadastrada', 'Adicione uma skill com o nível em que você atua.', 'check') ?></div>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($skills as $skill): ?>
                <li class="p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-medium text-slate-900"><?= e($skill['nome']) ?></p>
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800"><?= e($skill['nivel']) ?></span>
                    </div>
                    <?php if ($skill['evidencia'] !== ''): ?>
                        <p class="mt-1 text-sm text-slate-600"><?= e($skill['evidencia']) ?></p>
                    <?php endif; ?>
                    <p class="mt-2 text-xs text-slate-500"><?= e(formatarData($skill['data_registro'], true)) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-skills-pdi">
    <h2 id="titulo-skills-pdi" class="border-b border-slate-100 px-5 py-4 font-semibold text-marinho-900">Competências das metas</h2>
    <?php if (!$porCompetencia): ?>
        <div class="p-5"><?= estadoVazio('Nenhuma competência no PDI', 'Elas aparecem aqui quando houver metas cadastradas.', 'prancheta') ?></div>
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