<?php
/** @var string $classeCampo */
$servico = N8NWebhookService::padrao();
$urlRegra = trim((string) regra('webhook_n8n_url', ''));
$urlAmbiente = trim((string) env('N8N_WEBHOOK_URL', ''));
$segredoConfigurado = trim((string) env('N8N_WEBHOOK_SECRET', '')) !== '';
$envios = array_slice(array_values(array_filter(
    ordenarPorDataDesc(db()->todos('Logs_Auditoria'), 'data_hora'),
    static fn (array $log): bool => $log['acao'] === 'webhook_n8n'
)), 0, 12);
?>
<section class="mb-6 grid gap-4 sm:grid-cols-3" aria-label="Status da integração">
    <?= cardKpi('Webhook', $servico->configurado() ? 'Pronto' : 'Inativo', $servico->configurado() ? 'URL aceita e envio ligado' : 'Confira a URL e a regra de ativação', 'tendencia', $servico->configurado() ? 'verde' : 'amarelo') ?>
    <?= cardKpi('Segredo', $segredoConfigurado ? 'Configurado' : 'Ausente', 'O valor fica só na variável de ambiente', 'cadeado', $segredoConfigurado ? 'verde' : 'vermelho') ?>
    <?= cardKpi('Últimos envios', (string) count($envios), 'Registros de webhook_n8n nesta página', 'escudo', 'azul') ?>
</section>

<form method="post" action="<?= e(url('actions/admin_salvar.php')) ?>" class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-loading="Salvando...">
    <?= csrfCampo() ?>
    <input type="hidden" name="acao" value="salvar_integracao">
    <input type="hidden" name="secao" value="integracoes">
    <label class="text-sm font-medium text-slate-700">Envio ao n8n
        <select name="webhook_n8n_ativo" class="<?= $classeCampo ?>">
            <option value="sim" <?= boolValor(regra('webhook_n8n_ativo', 'sim')) ? 'selected' : '' ?>>Ligado</option>
            <option value="nao" <?= !boolValor(regra('webhook_n8n_ativo', 'sim')) ? 'selected' : '' ?>>Desligado</option>
        </select>
    </label>
    <label class="text-sm font-medium text-slate-700">URL do webhook
        <input type="url" name="webhook_n8n_url" value="<?= e($urlRegra) ?>" placeholder="<?= e($urlAmbiente !== '' ? 'Usando a URL da variável de ambiente' : 'https://') ?>" class="<?= $classeCampo ?>">
    </label>
    <p class="text-xs text-slate-500"><?= $urlRegra === '' && $urlAmbiente !== '' ? 'Sem URL nesta regra, o envio usa o endereço definido na variável de ambiente.' : 'Uma URL preenchida aqui substitui a variável de ambiente.' ?> O segredo compartilhado não é exibido nem editado nesta tela.</p>
    <label class="text-sm font-medium text-slate-700">Bem-estar individual no payload
        <select name="webhook_incluir_bem_estar" class="<?= $classeCampo ?>">
            <option value="nao" <?= !boolValor(regra('webhook_incluir_bem_estar', 'nao')) ? 'selected' : '' ?>>Não incluir</option>
            <option value="sim" <?= boolValor(regra('webhook_incluir_bem_estar', 'nao')) ? 'selected' : '' ?>>Incluir só em fluxo restrito ao RH</option>
        </select>
    </label>
    <p class="text-xs text-amber-800">A nota individual de bem-estar não vai no e-mail do gestor e não deve alimentar nós de IA.</p>
    <button type="submit" class="w-fit rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Salvar integração</button>
</form>

<form method="post" action="<?= e(url('actions/admin_salvar.php')) ?>" class="mt-4" data-loading="Enviando...">
    <?= csrfCampo() ?>
    <input type="hidden" name="acao" value="testar_webhook">
    <input type="hidden" name="secao" value="integracoes">
    <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Enviar teste sem dados pessoais</button>
</form>

<section class="mt-6" aria-labelledby="titulo-envios">
    <h3 id="titulo-envios" class="font-semibold text-marinho-900">Status dos envios</h3>
    <?php if (!$envios): ?>
        <div class="mt-3"><?= estadoVazio('Nenhum envio', 'Ainda não há registro de chamada ao n8n.', 'tendencia') ?></div>
    <?php else: ?>
        <ul class="mt-3 divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white px-5 shadow-sm">
            <?php foreach ($envios as $envio): ?>
                <li class="py-3 text-sm">
                    <p class="font-medium text-slate-900"><?= e(formatarData($envio['data_hora'], true)) ?> · <?= e($envio['resultado']) ?></p>
                    <p class="text-slate-600"><?= e($envio['descricao']) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
