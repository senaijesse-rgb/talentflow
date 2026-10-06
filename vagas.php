<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$usuario = exigirLogin();
$vagas = db()->todos('Vagas_Internas');
usort($vagas, static fn ($a, $b) => strcmp($b['data_publicacao'], $a['data_publicacao']));
$abertas = array_values(array_filter($vagas, static fn ($v) => ($v['status'] ?? '') === 'Aberta'));

$minhas = [];
foreach (db()->buscar('Candidaturas_Vaga', 'email_colaborador', $usuario['email']) as $candidatura) {
    $minhas[$candidatura['id_vaga']] = $candidatura;
}

$interessados = [];
if ($usuario['perfil'] === 'administrador') {
    foreach (db()->todos('Candidaturas_Vaga') as $candidatura) {
        $interessados[$candidatura['id_vaga']][] = $candidatura;
    }
}

$tituloPagina = 'Vagas internas';
$classeCampo = 'mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20';

require __DIR__ . '/includes/header.php';
?>
<div class="mb-6">
    <h2 class="text-2xl font-semibold text-marinho-900">Vagas internas</h2>
    <p class="text-sm text-slate-500">Oportunidades abertas na companhia. Demonstre interesse e o RH recebe o seu nome.</p>
</div>

<?php if ($usuario['perfil'] === 'administrador'): ?>
<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="titulo-publicar-vaga">
    <h2 id="titulo-publicar-vaga" class="font-semibold text-marinho-900">Publicar vaga</h2>
    <form method="post" action="<?= e(url('actions/salvar_vaga.php')) ?>" class="mt-4 grid gap-4 sm:grid-cols-2" data-loading="Publicando...">
        <?= csrfCampo() ?>
        <input type="hidden" name="operacao" value="publicar">
        <label class="block text-sm font-medium text-slate-700">Título
            <input name="titulo" required minlength="5" maxlength="120" class="<?= $classeCampo ?>" placeholder="Ex.: Analista de dados">
        </label>
        <label class="block text-sm font-medium text-slate-700">Área
            <input name="area" required minlength="2" maxlength="80" class="<?= $classeCampo ?>" placeholder="Ex.: Tecnologia">
        </label>
        <label class="block text-sm font-medium text-slate-700 sm:col-span-2">Descrição
            <textarea name="descricao" required minlength="10" maxlength="1000" rows="3" class="<?= $classeCampo ?>"></textarea>
        </label>
        <label class="block text-sm font-medium text-slate-700 sm:col-span-2">Requisitos <span class="font-normal text-slate-400">(opcional)</span>
            <textarea name="requisitos" maxlength="500" rows="2" class="<?= $classeCampo ?>"></textarea>
        </label>
        <div class="sm:col-span-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Publicar vaga</button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-lista-vagas">
    <h2 id="titulo-lista-vagas" class="border-b border-slate-100 px-5 py-4 font-semibold text-marinho-900">Abertas</h2>
    <?php if (!$abertas): ?>
        <div class="p-5"><?= estadoVazio('Nenhuma vaga aberta', 'Quando o RH publicar uma oportunidade, ela aparece aqui.', 'maleta') ?></div>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($abertas as $vaga): ?>
                <li class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-slate-900"><?= e($vaga['titulo']) ?></p>
                            <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-blue-700"><?= e($vaga['area']) ?></p>
                        </div>
                        <span class="text-xs text-slate-500"><?= e(formatarData($vaga['data_publicacao'])) ?></span>
                    </div>
                    <p class="mt-3 text-sm text-slate-700"><?= e($vaga['descricao']) ?></p>
                    <?php if ($vaga['requisitos'] !== ''): ?>
                        <p class="mt-2 text-sm text-slate-600"><span class="font-medium">Requisitos:</span> <?= e($vaga['requisitos']) ?></p>
                    <?php endif; ?>

                    <?php if (isset($minhas[$vaga['id_vaga']])): ?>
                        <p class="mt-4 text-sm font-medium text-emerald-700">Interesse registrado em <?= e(formatarData($minhas[$vaga['id_vaga']]['data_candidatura'], true)) ?>.</p>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('actions/candidatar_vaga.php')) ?>" class="mt-4 space-y-3" data-loading="Enviando...">
                            <?= csrfCampo() ?>
                            <input type="hidden" name="id_vaga" value="<?= e($vaga['id_vaga']) ?>">
                            <label class="block text-sm font-medium text-slate-700">Mensagem <span class="font-normal text-slate-400">(opcional)</span>
                                <textarea name="mensagem" maxlength="500" rows="2" class="<?= $classeCampo ?>" placeholder="Conte por que esta vaga combina com você."></textarea>
                            </label>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Tenho interesse</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($usuario['perfil'] === 'administrador'): ?>
                        <?php $lista = $interessados[$vaga['id_vaga']] ?? []; ?>
                        <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                            <p class="font-medium"><?= count($lista) ?> interessado<?= count($lista) === 1 ? '' : 's' ?></p>
                            <?php if ($lista): ?>
                                <ul class="mt-2 space-y-1">
                                    <?php foreach ($lista as $candidatura): ?>
                                        <li><?= e(nomeUsuario($candidatura['email_colaborador'])) ?><?= $candidatura['mensagem'] !== '' ? ' — ' . e($candidatura['mensagem']) : '' ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        <form method="post" action="<?= e(url('actions/salvar_vaga.php')) ?>" class="mt-3">
                            <?= csrfCampo() ?>
                            <input type="hidden" name="operacao" value="encerrar">
                            <input type="hidden" name="id_vaga" value="<?= e($vaga['id_vaga']) ?>">
                            <button type="submit" class="text-sm font-medium text-slate-600 hover:text-slate-900">Encerrar vaga</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php';