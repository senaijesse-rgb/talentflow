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

$destinatarios = [];
if ($usuario['perfil'] === 'administrador') {
    $destinatarios = array_values(array_filter(usuariosAtivos(), static fn ($u) => normalizarEmail($u['email']) !== $usuario['email']));
} elseif ($usuario['perfil'] === 'gestor') {
    $destinatarios = equipeDoGestor($usuario['email']);
}

$tituloPagina = 'Reconhecimento';
$classeCampo = 'mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20';

require __DIR__ . '/includes/header.php';
?>
<div class="mb-6">
    <h2 class="text-2xl font-semibold text-marinho-900">Reconhecimento</h2>
    <p class="text-sm text-slate-500">Mensagens de reconhecimento visíveis para você. O gestor ou o RH registra o texto.</p>
</div>

<div class="grid gap-6 lg:grid-cols-5">
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-3" aria-labelledby="titulo-lista-reconhecimento">
        <h2 id="titulo-lista-reconhecimento" class="border-b border-slate-100 px-5 py-4 font-semibold text-marinho-900">Recebidos e publicados</h2>
        <?php if (!$reconhecimentos): ?>
            <div class="p-5"><?= estadoVazio('Nenhum reconhecimento ainda', 'Quando um gestor publicar um reconhecimento visível, ele aparece nesta lista.', 'bandeira') ?></div>
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

    <?php if ($destinatarios): ?>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2" aria-labelledby="titulo-novo-reconhecimento">
            <h2 id="titulo-novo-reconhecimento" class="font-semibold text-marinho-900">Registrar reconhecimento</h2>
            <form method="post" action="<?= e(url('actions/salvar_comentario.php')) ?>" class="mt-4 space-y-3" data-loading="Salvando...">
                <?= csrfCampo() ?>
                <input type="hidden" name="destino" value="reconhecimento.php">
                <input type="hidden" name="tipo_comentario" value="Reconhecimento">
                <input type="hidden" name="visivel_colaborador" value="sim">
                <label class="block text-sm font-medium text-slate-700" for="email">Pessoa
                    <select id="email" name="email" required class="<?= $classeCampo ?>">
                        <option value="">Selecione...</option>
                        <?php foreach ($destinatarios as $pessoa): ?>
                            <option value="<?= e($pessoa['email']) ?>"><?= e($pessoa['nome_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block text-sm font-medium text-slate-700" for="comentario">Mensagem
                    <textarea id="comentario" name="comentario" required maxlength="1000" rows="4" class="<?= $classeCampo ?>" placeholder="Descreva a contribuição que você quer reconhecer."></textarea>
                </label>
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Publicar</button>
            </form>
        </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php';