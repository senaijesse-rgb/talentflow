<?php
/** @var string $classeCampo */
$catalogo = catalogoRegrasAdmin();
?>
<form method="post" action="<?= e(url('actions/admin_salvar.php')) ?>" class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2" data-loading="Salvando...">
    <?= csrfCampo() ?>
    <input type="hidden" name="acao" value="salvar_regras">
    <input type="hidden" name="secao" value="regras">
    <?php foreach ($catalogo as $chave => $regra):
        $fallback = RiskService::PADROES[$chave] ?? [
            'dias_prazo_proximo' => 7,
            'dias_meta_concluida_recente' => 30,
            'limite_atencao_percentual' => 50,
            'minimo_respostas_agregado' => 3,
        ][$chave] ?? '';
        $atual = (string) regra($chave, (string) $fallback);
        ?>
        <label class="text-sm font-medium text-slate-700"><?= e($regra['rotulo']) ?>
            <?php if ($regra['tipo'] === 'simnao'): ?>
                <select name="valor[<?= e($chave) ?>]" class="<?= $classeCampo ?>">
                    <option value="nao" <?= !boolValor($atual === '' ? 'nao' : $atual) ? 'selected' : '' ?>>Não</option>
                    <option value="sim" <?= boolValor($atual) ? 'selected' : '' ?>>Sim</option>
                </select>
            <?php else: ?>
                <input type="number" name="valor[<?= e($chave) ?>]" required min="<?= (int) $regra['min'] ?>" max="<?= (int) $regra['max'] ?>" value="<?= e($atual !== '' ? $atual : (string) (RiskService::PADROES[$chave] ?? '')) ?>" class="<?= $classeCampo ?>">
            <?php endif; ?>
        </label>
    <?php endforeach; ?>
    <div class="sm:col-span-2">
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Salvar regras</button>
        <p class="mt-3 text-xs text-slate-500">A nota de bem-estar entra só no cálculo interno do RH. Gestores continuam vendo a classificação, sem o fator sensível.</p>
    </div>
</form>
