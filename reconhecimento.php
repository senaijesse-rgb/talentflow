<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$usuario = exigirLogin();
$todos = db()->todos('Comentarios_Gestor');
$reconhecimentos = array_values(array_filter($todos, static function (array $item) use ($usuario): bool {
    if (($item['tipo_comentario'] ?? '') !== 'Reconhecimento') {
        return false;
    }
    if ($usuario['perfil'] === 'administrador') {
        return true;
    }

    $sobreMim = normalizarEmail($item['email_colaborador'] ?? '') === $usuario['email'] && boolValor($item['visivel_colaborador'] ?? '');
    $escritoPorMim = normalizarEmail($item['gestor_email'] ?? '') === $usuario['email'];

    return $sobreMim || $escritoPorMim;
}));
$reconhecimentos = ordenarPorDataDesc($reconhecimentos, 'data_comentario');

$colegas = [];
foreach (usuariosAtivos() as $pessoa) {
    if (normalizarEmail($pessoa['email']) === $usuario['email']) {
        continue;
    }
    $colegas[] = [
        'email' => $pessoa['email'],
        'nome' => $pessoa['nome_completo'],
        'cargo' => $pessoa['cargo'],
        'area' => $pessoa['area'],
    ];
}

$tituloPagina = 'Reconhecimento';
$classeCampo = 'mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20';

require __DIR__ . '/includes/header.php';
?>
<div class="mb-6">
    <h2 class="text-2xl font-semibold text-marinho-900">Reconhecimento</h2>
    <p class="text-sm text-slate-500">Reconheça um colega da companhia. A mensagem fica visível para a pessoa.</p>
</div>

<div class="grid gap-6 lg:grid-cols-5">
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-3" aria-labelledby="titulo-lista-reconhecimento">
        <h2 id="titulo-lista-reconhecimento" class="border-b border-slate-100 px-5 py-4 font-semibold text-marinho-900">Recebidos e enviados</h2>
        <?php if (!$reconhecimentos): ?>
            <div class="p-5"><?= estadoVazio('Nenhum reconhecimento ainda', 'Busque um colega e publique a primeira mensagem.', 'bandeira') ?></div>
        <?php else: ?>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($reconhecimentos as $item): ?>
                    <li class="p-5">
                        <p class="text-sm font-medium text-slate-900"><?= e(nomeUsuario($item['email_colaborador'])) ?></p>
                        <p class="mt-1 text-sm text-slate-700"><?= e($item['comentario']) ?></p>
                        <p class="mt-2 text-xs text-slate-500">Por <?= e(nomeUsuario($item['gestor_email'])) ?> · <?= e(formatarData($item['data_comentario'], true)) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2" aria-labelledby="titulo-novo-reconhecimento" data-busca-colegas>
        <h2 id="titulo-novo-reconhecimento" class="font-semibold text-marinho-900">Reconhecer um colega</h2>
        <script type="application/json" data-colegas><?= json_encode($colegas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
        <form method="post" action="<?= e(url('actions/salvar_comentario.php')) ?>" class="mt-4 space-y-3" data-loading="Salvando...">
            <?= csrfCampo() ?>
            <input type="hidden" name="destino" value="reconhecimento.php">
            <input type="hidden" name="tipo_comentario" value="Reconhecimento">
            <input type="hidden" name="visivel_colaborador" value="sim">
            <div>
                <label class="block text-sm font-medium text-slate-700" for="busca-colega">Buscar colega na companhia</label>
                <input id="busca-colega" type="search" autocomplete="off" placeholder="Nome, cargo ou área" class="<?= $classeCampo ?>" data-busca-colega>
                <ul class="mt-2 max-h-48 overflow-y-auto rounded-lg border border-slate-200 empty:hidden" data-resultados-colegas role="listbox" aria-label="Colegas encontrados"></ul>
            </div>
            <label class="block text-sm font-medium text-slate-700" for="email-colega">Colega escolhido
                <input id="email-colega" name="email" type="email" required readonly class="<?= $classeCampo ?> bg-slate-50" placeholder="Selecione alguém na busca" data-email-colega>
            </label>
            <label class="block text-sm font-medium text-slate-700" for="comentario">Mensagem
                <textarea id="comentario" name="comentario" required minlength="8" maxlength="1000" rows="4" class="<?= $classeCampo ?>" placeholder="Descreva a contribuição que você quer reconhecer."></textarea>
            </label>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Publicar</button>
        </form>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php';