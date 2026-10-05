<?php
/** @var string $classeCampo */
$idEdicao = textoLimpo($_GET['editar'] ?? '', 50);
$novo = ($_GET['novo'] ?? '') === '1';
$projeto = $idEdicao !== '' ? db()->buscarUm('Projetos', 'id_projeto', $idEdicao) : null;
$mostrarFormulario = $novo || $projeto !== null;
$projetos = db()->todos('Projetos');
usort($projetos, static fn ($a, $b) => [$a['nome_projeto']] <=> [$b['nome_projeto']]);
$gestores = gestoresElegiveis();
$ativos = usuariosAtivos();
$vinculos = $projeto ? db()->buscar('Colaborador_Projetos', 'id_projeto', $projeto['id_projeto']) : [];
$statuses = ['ativo' => 'Ativo', 'concluido' => 'Concluído', 'encerrado' => 'Encerrado'];
?>
<?php if ($mostrarFormulario): ?>
<form method="post" action="<?= e(url('actions/admin_salvar.php')) ?>" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2" data-loading="Salvando...">
    <?= csrfCampo() ?>
    <input type="hidden" name="acao" value="salvar_projeto">
    <input type="hidden" name="secao" value="projetos">
    <input type="hidden" name="id_projeto" value="<?= e($projeto['id_projeto'] ?? '') ?>">
    <label class="text-sm font-medium text-slate-700 sm:col-span-2">Nome
        <input type="text" name="nome_projeto" required maxlength="120" value="<?= e($projeto['nome_projeto'] ?? '') ?>" class="<?= $classeCampo ?>">
    </label>
    <label class="text-sm font-medium text-slate-700 sm:col-span-2">Descrição
        <textarea name="descricao" maxlength="500" rows="3" class="<?= $classeCampo ?>"><?= e($projeto['descricao'] ?? '') ?></textarea>
    </label>
    <label class="text-sm font-medium text-slate-700">Área responsável
        <input type="text" name="area_responsavel" maxlength="80" value="<?= e($projeto['area_responsavel'] ?? '') ?>" class="<?= $classeCampo ?>">
    </label>
    <label class="text-sm font-medium text-slate-700">Gestor responsável
        <select name="gestor_email" class="<?= $classeCampo ?>">
            <option value="">Nenhum</option>
            <?php foreach ($gestores as $gestor): ?>
                <option value="<?= e($gestor['email']) ?>" <?= normalizarEmail($projeto['gestor_email'] ?? '') === normalizarEmail($gestor['email']) ? 'selected' : '' ?>><?= e($gestor['nome_completo']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="text-sm font-medium text-slate-700">Status
        <select name="status" class="<?= $classeCampo ?>">
            <?php foreach ($statuses as $valor => $rotulo): ?>
                <option value="<?= e($valor) ?>" <?= ($projeto['status'] ?? 'ativo') === $valor ? 'selected' : '' ?>><?= e($rotulo) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="text-sm font-medium text-slate-700">Início
        <input type="date" name="data_inicio" required value="<?= e(substr((string) ($projeto['data_inicio'] ?? ''), 0, 10)) ?>" class="<?= $classeCampo ?>">
    </label>
    <label class="text-sm font-medium text-slate-700">Fim
        <input type="date" name="data_fim" value="<?= e(substr((string) ($projeto['data_fim'] ?? ''), 0, 10)) ?>" class="<?= $classeCampo ?>">
    </label>
    <div class="flex items-end gap-2 sm:col-span-2">
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800"><?= $projeto ? 'Salvar projeto' : 'Criar projeto' ?></button>
        <a href="<?= e(url('admin.php?secao=projetos')) ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancelar</a>
    </div>
</form>
<?php else: ?>
<p class="mb-4"><a href="<?= e(url('admin.php?secao=projetos&novo=1')) ?>" class="inline-flex rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Novo projeto</a></p>
<?php endif; ?>

<?php if ($projeto): ?>
<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="titulo-participacoes">
    <h3 id="titulo-participacoes" class="font-semibold text-marinho-900">Participações em <?= e($projeto['nome_projeto']) ?></h3>
    <form method="post" action="<?= e(url('actions/admin_salvar.php')) ?>" class="mt-4 grid gap-3 sm:grid-cols-4" data-loading="Salvando...">
        <?= csrfCampo() ?>
        <input type="hidden" name="acao" value="adicionar_vinculo">
        <input type="hidden" name="secao" value="projetos">
        <input type="hidden" name="id_projeto" value="<?= e($projeto['id_projeto']) ?>">
        <label class="text-sm font-medium text-slate-700 sm:col-span-2">Pessoa
            <select name="email_colaborador" required class="<?= $classeCampo ?>">
                <option value="">Selecione</option>
                <?php foreach ($ativos as $pessoa): ?>
                    <option value="<?= e($pessoa['email']) ?>"><?= e($pessoa['nome_completo']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="text-sm font-medium text-slate-700">Papel
            <input type="text" name="papel_no_projeto" required maxlength="80" class="<?= $classeCampo ?>">
        </label>
        <label class="text-sm font-medium text-slate-700">Início
            <input type="date" name="data_inicio" required value="<?= e(date('Y-m-d')) ?>" class="<?= $classeCampo ?>">
        </label>
        <div class="sm:col-span-4"><button type="submit" class="rounded-lg bg-marinho-800 px-4 py-2 text-sm font-semibold text-white hover:bg-marinho-900">Adicionar participação</button></div>
    </form>
    <?php if (!$vinculos): ?>
        <p class="mt-4 text-sm text-slate-500">Nenhuma participação registrada.</p>
    <?php else: ?>
        <ul class="mt-4 divide-y divide-slate-100 text-sm">
            <?php foreach ($vinculos as $vinculo): ?>
                <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-medium text-slate-900"><?= e(nomeUsuario($vinculo['email_colaborador'])) ?></p>
                        <p class="text-xs text-slate-500"><?= e($vinculo['papel_no_projeto']) ?> · <?= e($vinculo['status_participacao']) ?> · desde <?= e(formatarData($vinculo['data_inicio'])) ?></p>
                    </div>
                    <?php if (mb_strtolower($vinculo['status_participacao']) === 'ativo'): ?>
                        <form method="post" action="<?= e(url('actions/admin_salvar.php')) ?>">
                            <?= csrfCampo() ?>
                            <input type="hidden" name="acao" value="encerrar_vinculo">
                            <input type="hidden" name="secao" value="projetos">
                            <input type="hidden" name="id_projeto" value="<?= e($projeto['id_projeto']) ?>">
                            <input type="hidden" name="id_vinculo" value="<?= e($vinculo['id_vinculo']) ?>">
                            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Encerrar</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php endif; ?>

<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-slate-100 text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-5 py-3">Projeto</th>
                <th class="px-5 py-3">Área</th>
                <th class="px-5 py-3">Responsável</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3 text-right">Ação</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($projetos as $item): ?>
                <tr>
                    <td class="px-5 py-3 font-medium text-slate-900"><?= e($item['nome_projeto']) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($item['area_responsavel'] ?: '—') ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($item['gestor_email'] !== '' ? nomeUsuario($item['gestor_email']) : '—') ?></td>
                    <td class="px-5 py-3"><?= e($statuses[$item['status']] ?? $item['status']) ?></td>
                    <td class="px-5 py-3 text-right"><a href="<?= e(url('admin.php?secao=projetos&editar=' . rawurlencode($item['id_projeto']))) ?>" class="font-medium text-blue-700 hover:text-blue-800">Editar</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
