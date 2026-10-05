<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$usuario = exigirLogin();
$colaborador = buscarUsuario($usuario['email']);
$projetos = projetosParaFormulario($usuario['email']);
$ultimoCheckin = checkinsDoColaborador($usuario['email'])[0] ?? null;
$tituloPagina = 'Check-in de Experiência';

$escalas = [
    [
        'nome' => 'satisfacao_empresa',
        'rotulo' => 'Satisfação em relação à empresa',
        'escala' => ESCALA_SATISFACAO,
        'obrigatorio' => true,
        'ajuda' => 'De modo geral, como você avalia sua satisfação com a empresa hoje?',
    ],
    [
        'nome' => 'risco_saida_percebido',
        'rotulo' => 'Risco de saída percebido',
        'escala' => ESCALA_RISCO_SAIDA,
        'obrigatorio' => true,
        'ajuda' => 'Qual a probabilidade de você considerar deixar a empresa nos próximos meses?',
    ],
    [
        'nome' => 'bem_estar_trabalho',
        'rotulo' => 'Indicador voluntário de bem-estar no trabalho',
        'escala' => ESCALA_BEM_ESTAR,
        'obrigatorio' => false,
        'ajuda' => AVISO_BEM_ESTAR,
    ],
];

require __DIR__ . '/includes/header.php';
?>
<div class="mx-auto max-w-3xl">
    <div class="mb-6">
        <a href="<?= e(url('dashboard.php')) ?>" class="text-sm font-medium text-blue-700 hover:text-blue-800">← Voltar ao dashboard</a>
        <h2 class="mt-2 text-2xl font-semibold text-marinho-900">Check-in de Experiência do Colaborador</h2>
        <p class="text-sm text-slate-500">Conte como está sua experiência na empresa. Leva menos de 2 minutos.</p>
        <?php if ($ultimoCheckin): ?>
            <p class="mt-2 text-xs text-slate-500">Seu último check-in foi enviado em <?= e(formatarData($ultimoCheckin['data_checkin'])) ?>.</p>
        <?php endif; ?>
    </div>

    <div class="mb-6"><?= avisoConfidencialidade('Suas respostas são confidenciais. Seu gestor não visualiza sua resposta individual de bem-estar — apenas médias agregadas e anônimas da equipe. O acesso individual é restrito ao RH e registrado em log.') ?></div>

    <form method="post" action="<?= e(url('actions/salvar_checkin.php')) ?>" class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" data-loading="Enviando...">
        <?= csrfCampo() ?>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="email_exibicao" class="block text-sm font-medium text-slate-700">E-mail</label>
                <input id="email_exibicao" type="email" value="<?= e($usuario['email']) ?>" readonly aria-readonly="true"
                       class="mt-1.5 block w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-600">
            </div>
            <div>
                <label for="nome_exibicao" class="block text-sm font-medium text-slate-700">Nome</label>
                <input id="nome_exibicao" type="text" value="<?= e($colaborador['nome_completo'] ?? $usuario['nome']) ?>" readonly aria-readonly="true"
                       class="mt-1.5 block w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-600">
            </div>
        </div>

        <div>
            <label for="id_projeto" class="block text-sm font-medium text-slate-700">Projeto atual</label>
            <select id="id_projeto" name="id_projeto" class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
                <option value="">Sem projeto específico</option>
                <?php foreach ($projetos as $indice => $projeto): ?>
                    <option value="<?= e($projeto['id_projeto']) ?>" <?= $indice === 0 ? 'selected' : '' ?>><?= e($projeto['nome_projeto']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php foreach ($escalas as $campo): ?>
            <fieldset class="rounded-xl border border-slate-200 p-4 sm:p-5">
                <legend class="px-1 text-sm font-semibold text-slate-800">
                    <?= e($campo['rotulo']) ?>
                    <?= $campo['obrigatorio'] ? '<span class="text-red-600">*</span>' : '<span class="font-normal text-slate-400">(opcional)</span>' ?>
                </legend>
                <?php if ($campo['nome'] === 'bem_estar_trabalho'): ?>
                    <p class="mt-1 flex gap-2 rounded-lg bg-amber-50 p-3 text-xs text-amber-900"><?= icone('info', 'h-4 w-4 shrink-0') ?><?= e($campo['ajuda']) ?></p>
                <?php else: ?>
                    <p class="mt-1 text-xs text-slate-500"><?= e($campo['ajuda']) ?></p>
                <?php endif; ?>

                <div class="mt-4 grid grid-cols-5 gap-2">
                    <?php foreach ($campo['escala'] as $valor => $rotulo): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="<?= e($campo['nome']) ?>" value="<?= $valor ?>" class="peer sr-only" <?= $campo['obrigatorio'] ? 'required' : '' ?>>
                            <span class="flex h-full flex-col items-center gap-1 rounded-lg border border-slate-200 px-1 py-3 text-center transition hover:border-blue-300 hover:bg-blue-50/50 peer-checked:border-blue-700 peer-checked:bg-blue-700 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-blue-600 peer-focus-visible:ring-offset-2">
                                <span class="text-lg font-semibold"><?= $valor ?></span>
                                <span class="text-[11px] leading-tight sm:text-xs"><?= e($rotulo) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <?php if (!$campo['obrigatorio']): ?>
                    <label class="mt-3 inline-flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                        <input type="radio" name="<?= e($campo['nome']) ?>" value="" checked class="h-4 w-4 border-slate-300 text-blue-700 focus:ring-blue-600">
                        Prefiro não responder
                    </label>
                <?php endif; ?>
            </fieldset>
        <?php endforeach; ?>

        <div>
            <div class="flex items-center justify-between">
                <label for="comentario" class="block text-sm font-medium text-slate-700">Comentário sobre sua experiência no trabalho <span class="font-normal text-slate-400">(opcional)</span></label>
                <span id="contador-comentario" class="text-xs text-slate-400" aria-live="polite"></span>
            </div>
            <textarea id="comentario" name="comentario" rows="4" maxlength="1000" data-contador="#contador-comentario"
                      class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
                      placeholder="O que tem funcionado bem? O que poderia melhorar no seu dia a dia de trabalho?"></textarea>
        </div>

        <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
            <input type="checkbox" name="consentimento" value="1" required class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-600">
            <span>Entendo que não devo informar diagnósticos, dados médicos ou informações clínicas neste formulário. <span class="text-red-600">*</span></span>
        </label>

        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
            <a href="<?= e(url('dashboard.php')) ?>" class="inline-flex justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</a>
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 disabled:opacity-70">
                <?= icone('check', 'h-4 w-4') ?> Enviar check-in
            </button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
