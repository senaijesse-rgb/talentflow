<?php
/** @var string $classeCampo */
$pessoas = db()->todos('Usuarios');
usort($pessoas, static fn ($a, $b) => [boolValor($b['ativo']), $a['nome_completo']] <=> [boolValor($a['ativo']), $b['nome_completo']]);
$gestores = gestoresElegiveis();
?>
<form method="post" action="<?= e(url('actions/admin_salvar.php')) ?>" data-loading="Salvando...">
    <?= csrfCampo() ?>
    <input type="hidden" name="acao" value="salvar_equipes">
    <input type="hidden" name="secao" value="equipes">
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Pessoa</th>
                    <th class="px-5 py-3">Perfil</th>
                    <th class="px-5 py-3">Gestor responsável</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($pessoas as $pessoa):
                    $email = normalizarEmail($pessoa['email']);
                    ?>
                    <tr class="<?= boolValor($pessoa['ativo']) ? '' : 'opacity-60' ?>">
                        <td class="px-5 py-3">
                            <p class="font-medium text-slate-900"><?= e($pessoa['nome_completo']) ?></p>
                            <p class="text-xs text-slate-500"><?= e($pessoa['email']) ?><?= boolValor($pessoa['ativo']) ? '' : ' · inativo' ?></p>
                        </td>
                        <td class="px-5 py-3"><?= e(rotuloPerfil($pessoa['perfil'])) ?></td>
                        <td class="px-5 py-3">
                            <label class="sr-only" for="gestor-<?= e($email) ?>">Gestor de <?= e($pessoa['nome_completo']) ?></label>
                            <select id="gestor-<?= e($email) ?>" name="gestor[<?= e($email) ?>]" class="<?= $classeCampo ?> mt-0">
                                <option value="">Nenhum</option>
                                <?php foreach ($gestores as $gestor):
                                    if (normalizarEmail($gestor['email']) === $email) {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?= e($gestor['email']) ?>" <?= normalizarEmail($pessoa['gestor_email']) === normalizarEmail($gestor['email']) ? 'selected' : '' ?>><?= e($gestor['nome_completo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button type="submit" class="mt-4 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Salvar vínculos</button>
</form>
