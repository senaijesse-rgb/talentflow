<?php
/** @var array $usuario */
/** @var string $classeCampo */
$editandoEmail = normalizarEmail((string) ($_GET['editar'] ?? ''));
$novo = ($_GET['novo'] ?? '') === '1';
$editando = $editandoEmail !== '' ? buscarUsuario($editandoEmail) : null;
$mostrarFormulario = $novo || $editando !== null;
$pessoas = db()->todos('Usuarios');
usort($pessoas, static fn ($a, $b) => [$a['nome_completo']] <=> [$b['nome_completo']]);
$gestores = gestoresElegiveis();
?>
<?php if ($mostrarFormulario): ?>
<form method="post" action="<?= e(url('actions/admin_salvar.php')) ?>" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2" data-loading="Salvando...">
    <?= csrfCampo() ?>
    <input type="hidden" name="acao" value="salvar_usuario">
    <input type="hidden" name="secao" value="usuarios">
    <label class="text-sm font-medium text-slate-700 sm:col-span-2">E-mail
        <input type="email" name="email" required maxlength="254" value="<?= e($editando['email'] ?? '') ?>" <?= $editando ? 'readonly' : '' ?> class="<?= $classeCampo ?> <?= $editando ? 'bg-slate-50' : '' ?>">
    </label>
    <label class="text-sm font-medium text-slate-700">Nome completo
        <input type="text" name="nome_completo" required maxlength="120" value="<?= e($editando['nome_completo'] ?? '') ?>" class="<?= $classeCampo ?>">
    </label>
    <label class="text-sm font-medium text-slate-700">Cargo
        <input type="text" name="cargo" required maxlength="80" value="<?= e($editando['cargo'] ?? '') ?>" class="<?= $classeCampo ?>">
    </label>
    <label class="text-sm font-medium text-slate-700">Área
        <input type="text" name="area" maxlength="80" value="<?= e($editando['area'] ?? '') ?>" class="<?= $classeCampo ?>">
    </label>
    <label class="text-sm font-medium text-slate-700">Perfil
        <select name="perfil" required class="<?= $classeCampo ?>">
            <?php foreach (PERFIS as $valor => $rotulo): ?>
                <option value="<?= e($valor) ?>" <?= ($editando['perfil'] ?? 'colaborador') === $valor ? 'selected' : '' ?>><?= e($rotulo) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="text-sm font-medium text-slate-700">Gestor
        <select name="gestor_email" class="<?= $classeCampo ?>">
            <option value="">Nenhum</option>
            <?php foreach ($gestores as $gestor): ?>
                <option value="<?= e($gestor['email']) ?>" <?= normalizarEmail($editando['gestor_email'] ?? '') === normalizarEmail($gestor['email']) ? 'selected' : '' ?>><?= e($gestor['nome_completo']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="text-sm font-medium text-slate-700">Senha <?= $editando ? '(deixe em branco para manter)' : 'inicial' ?>
        <input type="password" name="senha" minlength="<?= $editando ? '0' : '8' ?>" maxlength="200" autocomplete="new-password" class="<?= $classeCampo ?>" <?= $editando ? '' : 'required' ?>>
    </label>
    <label class="text-sm font-medium text-slate-700">Situação
        <select name="ativo" class="<?= $classeCampo ?>">
            <option value="sim" <?= boolValor($editando['ativo'] ?? 'sim') ? 'selected' : '' ?>>Ativo</option>
            <option value="nao" <?= $editando && !boolValor($editando['ativo']) ? 'selected' : '' ?>>Inativo</option>
        </select>
    </label>
    <div class="flex items-end gap-2 sm:col-span-2">
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800"><?= $editando ? 'Salvar alterações' : 'Criar usuário' ?></button>
        <a href="<?= e(url('admin.php?secao=usuarios')) ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancelar</a>
    </div>
</form>
<?php else: ?>
<p class="mb-4"><a href="<?= e(url('admin.php?secao=usuarios&novo=1')) ?>" class="inline-flex rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Novo usuário</a></p>
<?php endif; ?>

<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-slate-100 text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-5 py-3">Nome</th>
                <th class="px-5 py-3">Perfil</th>
                <th class="px-5 py-3">Área</th>
                <th class="px-5 py-3">Gestor</th>
                <th class="px-5 py-3">Situação</th>
                <th class="px-5 py-3 text-right">Ação</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($pessoas as $pessoa): ?>
                <tr>
                    <td class="px-5 py-3">
                        <p class="font-medium text-slate-900"><?= e($pessoa['nome_completo']) ?></p>
                        <p class="text-xs text-slate-500"><?= e($pessoa['email']) ?> · <?= e($pessoa['cargo']) ?></p>
                    </td>
                    <td class="px-5 py-3"><?= e(rotuloPerfil($pessoa['perfil'])) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($pessoa['area'] ?: '—') ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= e($pessoa['gestor_email'] !== '' ? nomeUsuario($pessoa['gestor_email']) : '—') ?></td>
                    <td class="px-5 py-3"><?= boolValor($pessoa['ativo']) ? 'Ativo' : 'Inativo' ?></td>
                    <td class="px-5 py-3 text-right"><a href="<?= e(url('admin.php?secao=usuarios&editar=' . rawurlencode($pessoa['email']))) ?>" class="font-medium text-blue-700 hover:text-blue-800">Editar</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
