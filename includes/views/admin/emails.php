<?php
/** @var string $classeCampo */
$catalogo = N8NWebhookService::EVENTOS_EMAIL + ['alerta' => 'Modelo padrão de alerta'];
$destinos = [
    'confirmacao_atualizacao_pdi' => 'Colaborador',
    'lembrete_pdi_abaixo_50' => 'Colaborador',
    'aviso_prazo_proximo' => 'Colaborador',
    'alerta_pdi_atrasado' => 'Colaborador e gestor',
    'aviso_meta_concluida' => 'Colaborador e gestor',
    'solicitacao_validacao_gestor' => 'Gestor',
    'alerta_risco_alto_rh' => 'RH, sem nota de bem-estar',
    'resumo_semanal_gestor' => 'Gestor',
    'resumo_mensal_rh' => 'RH',
    'alerta' => 'Gestor',
];
?>
<form method="post" action="<?= e(url('actions/admin_salvar.php')) ?>" class="space-y-4" data-loading="Salvando...">
    <?= csrfCampo() ?>
    <input type="hidden" name="acao" value="salvar_emails">
    <input type="hidden" name="secao" value="emails">
    <p class="text-sm text-slate-500">Placeholders: {{nome_colaborador}}, {{nome_gestor}}, {{meta}}, {{status}}, {{prazo}}, {{percentual_novo}}. O n8n recebe o texto salvo quando dispara o e-mail.</p>
    <?php foreach ($catalogo as $codigo => $rotulo): ?>
        <label class="block rounded-2xl border border-slate-200 bg-white p-4 text-sm font-medium text-slate-700 shadow-sm">
            <span class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                <span><?= e($rotulo) ?></span>
                <span class="text-xs font-normal text-slate-400"><?= e($codigo) ?> · <?= e($destinos[$codigo] ?? '') ?></span>
            </span>
            <textarea name="modelo[<?= e($codigo) ?>]" required maxlength="1000" rows="3" class="<?= $classeCampo ?>"><?= e(textoModeloEmail($codigo)) ?></textarea>
        </label>
    <?php endforeach; ?>
    <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Salvar modelos</button>
</form>
