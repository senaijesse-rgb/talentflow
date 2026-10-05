<?php
$pessoa = normalizarEmail((string) ($_GET['pessoa'] ?? ''));
$checkins = ordenarPorDataDesc(db()->todos('Checkins_Clima'), 'data_checkin');
$porPessoa = [];
foreach ($checkins as $checkin) {
    $email = normalizarEmail($checkin['email_colaborador']);
    $porPessoa[$email][] = $checkin;
}
$selecionados = $pessoa !== '' ? ($porPessoa[$pessoa] ?? []) : [];
?>
<?= avisoConfidencialidade('Área restrita ao Administrador RH. Esta consulta foi registrada na auditoria sem gravar a nota. O comentário livre do check-in não aparece aqui e não é enviado ao gestor.') ?>

<div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-slate-100 text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-5 py-3">Pessoa</th>
                <th class="px-5 py-3">Última resposta</th>
                <th class="px-5 py-3">Bem-estar</th>
                <th class="px-5 py-3 text-right">Histórico</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($porPessoa as $email => $lista):
                $ultimo = $lista[0];
                $nota = $ultimo['bem_estar_trabalho'];
                ?>
                <tr>
                    <td class="px-5 py-3">
                        <p class="font-medium text-slate-900"><?= e(nomeUsuario($email)) ?></p>
                        <p class="text-xs text-slate-500"><?= e($email) ?></p>
                    </td>
                    <td class="px-5 py-3 text-slate-600"><?= e(formatarData($ultimo['data_checkin'], true)) ?></td>
                    <td class="px-5 py-3"><?= $nota === '' ? 'Prefiro não responder' : e($nota . '/5 · ' . (ESCALA_BEM_ESTAR[(int) $nota] ?? '')) ?></td>
                    <td class="px-5 py-3 text-right"><a href="<?= e(url('admin.php?secao=bem-estar&pessoa=' . rawurlencode($email))) ?>" class="font-medium text-blue-700 hover:text-blue-800">Ver</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (!$porPessoa): ?>
        <div class="p-5"><?= estadoVazio('Sem respostas', 'Nenhum check-in de bem-estar foi registrado.', 'cadeado') ?></div>
    <?php endif; ?>
</div>

<?php if ($pessoa !== ''): ?>
<section class="mt-6 rounded-2xl border border-amber-200 bg-white shadow-sm" aria-labelledby="titulo-historico-bem-estar">
    <div class="border-b border-amber-100 px-5 py-4">
        <h3 id="titulo-historico-bem-estar" class="font-semibold text-marinho-900">Histórico de <?= e(nomeUsuario($pessoa)) ?></h3>
    </div>
    <?php if (!$selecionados): ?>
        <div class="p-5"><?= estadoVazio('Sem histórico', 'Não há check-in para esta pessoa.', 'cadeado') ?></div>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($selecionados as $checkin):
                $nota = $checkin['bem_estar_trabalho'];
                ?>
                <li class="flex items-center justify-between px-5 py-3 text-sm">
                    <span class="text-slate-600"><?= e(formatarData($checkin['data_checkin'], true)) ?></span>
                    <span><?= $nota === '' ? 'Prefiro não responder' : e($nota . '/5 · ' . (ESCALA_BEM_ESTAR[(int) $nota] ?? '')) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php endif; ?>
