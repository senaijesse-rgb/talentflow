<?php
/** @var array $usuario */
require_once __DIR__ . '/../mentoria.php';

$metasMentoria = metasParaMentoria($usuario['email']);
$historicoMentoria = historicoMentoria();
$classeCampoMentoria = 'mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20';
?>
<section id="mentoria" class="mt-6 scroll-mt-20 rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-mentoria">
    <div class="border-b border-slate-100 px-5 py-4">
        <h2 id="titulo-mentoria" class="flex items-center gap-2 font-semibold text-marinho-900"><?= icone('chat', 'h-5 w-5 text-blue-700') ?> MentorIA</h2>
        <p class="mt-1 text-sm text-slate-500">Orientação prática para destravar uma meta do seu PDI. Não é terapia, diagnóstico médico ou aconselhamento clínico.</p>
    </div>
    <div class="grid gap-6 p-5 xl:grid-cols-5">
        <div class="space-y-4 xl:col-span-3">
            <?= avisoConfidencialidade('A MentorIA ajuda com dificuldades ligadas às suas metas de desenvolvimento. Para questões pessoais, de saúde ou conflitos sérios, procure seu gestor, o RH ou um profissional especializado. O texto que você escreve não vai para o gestor nem para o e-mail.') ?>
            <?php if (!$metasMentoria): ?>
                <div><?= estadoVazio('Nenhuma meta em aberto', 'Quando houver uma meta sua em andamento, a MentorIA monta um plano de sete dias a partir dela.', 'prancheta') ?></div>
            <?php else: ?>
                <form method="post" action="<?= e(url('actions/mentoria.php')) ?>" class="space-y-4" data-loading="Gerando...">
                    <?= csrfCampo() ?>
                    <label class="block text-sm font-medium text-slate-700" for="mentoria-meta">Selecione a meta
                        <select id="mentoria-meta" name="id_pdi" required class="<?= $classeCampoMentoria ?>">
                            <option value="">Selecione...</option>
                            <?php foreach ($metasMentoria as $meta): ?>
                                <option value="<?= e($meta['id_pdi']) ?>"><?= e($meta['meta']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-700" for="mentoria-dificuldade">Descreva sua dificuldade
                        <textarea id="mentoria-dificuldade" name="dificuldade" required minlength="8" maxlength="1000" rows="3" class="<?= $classeCampoMentoria ?>" placeholder="Ex.: não estou conseguindo tempo para estudar por causa das entregas do projeto."></textarea>
                    </label>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-800">Gerar plano de ação</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="xl:col-span-2">
            <h3 class="text-sm font-semibold text-marinho-900">Histórico desta sessão</h3>
            <?php if (!$historicoMentoria): ?>
                <p class="mt-3 text-sm text-slate-500">Nenhuma orientação gerada ainda nesta visita.</p>
            <?php else: ?>
                <ul class="mt-3 space-y-3">
                    <?php foreach ($historicoMentoria as $item): ?>
                        <li class="rounded-xl border border-blue-100 bg-blue-50/40 p-4">
                            <p class="text-sm font-semibold text-slate-900"><?= e($item['meta']) ?></p>
                            <p class="text-xs text-slate-500"><?= e(formatarData($item['data'], true)) ?></p>
                            <p class="mt-3 text-sm text-slate-700"><?= e($item['mensagem']) ?></p>
                            <ol class="mt-3 space-y-2">
                                <?php foreach ($item['passos'] as $indice => $passo): ?>
                                    <li class="flex gap-3 rounded-lg border border-slate-100 bg-white p-3 text-sm">
                                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-blue-700 text-xs font-bold text-white"><?= $indice + 1 ?></span>
                                        <span><?= e($passo) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <p class="mt-3 rounded-lg border border-slate-100 bg-white p-3 text-sm"><b>Pergunta de reflexão:</b> <?= e($item['reflexao']) ?></p>
                            <?php if (!empty($item['aviso'])): ?>
                                <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950"><?= e($item['aviso']) ?></p>
                            <?php endif; ?>
                            <p class="mt-3 text-[11px] text-slate-500">A MentorIA oferece orientação de desenvolvimento profissional. Não realiza terapia, diagnóstico médico ou aconselhamento clínico.</p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</section>
