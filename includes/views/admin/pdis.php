<?php
/** @var string $classeCampo */
$idEdicao = textoLimpo($_GET['editar'] ?? '', 50);
$novo = ($_GET['novo'] ?? '') === '1';
$editando = $idEdicao !== '' ? buscarPdi($idEdicao) : null;
$filtroStatus = in_array($_GET['status'] ?? '', STATUS_PDI, true) ? (string) $_GET['status'] : '';
$pessoas = usuariosAtivos();
$pdis = db()->todos('PDIs');
$pdis = array_map(static fn (array $pdi): array => $pdi + ['status_efetivo' => statusEfetivo($pdi)], $pdis);
if ($filtroStatus !== '') {
    $pdis = array_values(array_filter($pdis, static fn (array $pdi): bool => $pdi['status_efetivo'] === $filtroStatus));
}
usort($pdis, static fn ($a, $b) => [$a['prazo']] <=> [$b['prazo']]);
?>
<?php if ($novo || $editando): ?>
<form method="post" action="<?= e(url('actions/admin_salvar.php')) ?>" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2" data-loading="Salvando...">
    <?= csrfCampo() ?>
    <input type="hidden" name="acao" value="salvar_pdi">
    <input type="hidden" name="secao" value="pdis">
    <input type="hidden" name="id_pdi" value="<?= e($editando['id_pdi'] ?? '') ?>">
    <label class="text-sm font-medium text-slate-700 sm:col-span-2">Colaborador
        <?php if ($editando): ?>
            <input type="hidden" name="email_colaborador" value="<?= e($editando['email_colaborador']) ?>">
            <input type="text" value="<?= e(nomeUsuario($editando['email_colaborador'])) ?>" class="<?= $classeCampo ?> bg-slate-50" readonly>
        <?php else: ?>
            <select name="email_colaborador" required class="<?= $classeCampo ?>">
                <option value="">Selecione</option>
                <?php foreach ($pessoas as $pessoa): ?>
                    <option value="<?= e($pessoa['email']) ?>"><?= e($pessoa['nome_completo']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </label>
    <label class="text-sm font-medium text-slate-700">Competência
        <input type="text" name="competencia" required maxlength="80" value="<?= e($editando['competencia'] ?? '') ?>" class="<?= $classeCampo ?>">
    </label>
    <label class="text-sm font-medium text-slate-700">Status
        <select name="status" class="<?= $classeCampo ?>">
            <?php foreach (STATUS_PDI as $status): ?>
                <option value="<?= e($status) ?>" <?= ($editando['status'] ?? 'Em andamento') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="text-sm font-medium text-slate-700 sm:col-span-2">Meta
        <textarea name="meta" required maxlength="300" rows="3" class="<?= $classeCampo ?>"><?= e($editando['meta'] ?? '') ?></textarea>
    </label>
    <label class="text-sm font-medium text-slate-700">Percentual
        <input type="number" name="percentual_conclusao" min="0" max="100" required value="<?= e((string) ($editando['percentual_conclusao'] ?? '0')) ?>" class="<?= $classeCampo ?>">
    </label>
    <label class="text-sm font-medium text-slate-700">Prazo
        <input type="date" name="prazo" required value="<?= e(substr((string) ($editando['prazo'] ?? ''), 0, 10)) ?>" class="<?= $classeCampo ?>">
    </label>
    <?php if (!$editando): ?>
        <label class="text-sm font-medium text-slate-700">Início
            <input type="date" name="data_inicio" required value="<?= e(date('Y-m-d')) ?>" class="<?= $classeCampo ?>">
        </label>
    <?php endif; ?>
    <label class="text-sm font-medium text-slate-700">Registro
        <select name="ativo" class="<?= $classeCampo ?>">
            <option value="sim" <?= boolValor($editando['ativo'] ?? 'sim') ? 'selected' : '' ?>>Ativo</option>
            <option value="nao" <?= $editando && !boolValor($editando['ativo']) ? 'selected' : '' ?>>Inativo</option>
        </select>
    </label>
    <div class="flex items-end gap-2 sm:col-span-2">
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800"><?= $editando ? 'Salvar PDI' : 'Criar PDI' ?></button>
        <a href="<?= e(url('admin.php?secao=pdis')) ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancelar</a>
    </div>
</form>
<?php else: ?>
<div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <a href="<?= e(url('admin.php?secao=pdis&novo=1')) ?>" class="inline-flex w-fit rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Novo PDI</a>
    <form method="get" class="flex items-center gap-2">
        <input type="hidden" name="secao" value="pdis">
        <label class="text-sm text-slate-600">Status
            <select name="status" class="<?= $classeCampo ?> mt-0" onchange="this.form.submit()">
                <option value="">Todos</option>
                <?php foreach (STATUS_PDI as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filtroStatus === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
</div>
<?php endif; ?>

<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table id="tabela-pdis" class="min-w-full divide-y divide-slate-100 text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-5 py-3">Pessoa</th>
                <th class="px-5 py-3">Meta</th>
                <th class="px-5 py-3">Prazo</th>
                <th class="px-5 py-3">Progresso</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3 text-right">Ação</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($pdis as $pdi): ?>
                <tr data-linha>
                    <td class="px-5 py-3">
                        <p class="font-medium text-slate-900"><?= e(nomeUsuario($pdi['email_colaborador'])) ?></p>
                        <p class="text-xs text-slate-500"><?= e($pdi['competencia']) ?></p>
                    </td>
                    <td class="max-w-xs px-5 py-3 text-slate-700"><?= e($pdi['meta']) ?></td>
                    <td class="whitespace-nowrap px-5 py-3 text-slate-600"><?= e(formatarData($pdi['prazo'])) ?></td>
                    <td class="px-5 py-3"><?= barraProgresso((float) $pdi['percentual_conclusao']) ?></td>
                    <td class="px-5 py-3"><?= badgeStatus($pdi['status_efetivo']) ?><?= boolValor($pdi['ativo']) ? '' : ' <span class="text-xs text-slate-400">inativo</span>' ?></td>
                    <td class="px-5 py-3 text-right"><a href="<?= e(url('admin.php?secao=pdis&editar=' . rawurlencode($pdi['id_pdi']))) ?>" class="font-medium text-blue-700 hover:text-blue-800">Editar</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (!$pdis): ?>
        <div class="p-5"><?= estadoVazio('Nenhum PDI', 'Não há metas para o filtro selecionado.', 'prancheta') ?></div>
    <?php endif; ?>
</div>
