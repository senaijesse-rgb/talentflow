<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/admin_apoio.php';

$usuario = exigirPerfil('administrador');
$secoes = secoesAdmin();
$secao = (string) ($_GET['secao'] ?? 'usuarios');
if (!isset($secoes[$secao])) {
    $secao = 'usuarios';
}

if ($secao === 'bem-estar') {
    $pessoaConsulta = normalizarEmail((string) ($_GET['pessoa'] ?? ''));
    registrarLog(
        'consulta_bem_estar',
        'Checkins_Clima',
        $pessoaConsulta,
        $pessoaConsulta !== ''
            ? 'Consulta individual de bem-estar. A nota não foi gravada no log.'
            : 'Listagem de bem-estar individual. As notas não foram gravadas no log.'
    );
}

$tituloPagina = $secoes[$secao]['rotulo'];
$classeCampo = classeCampoAdmin();

require __DIR__ . '/includes/header.php';
?>
<div class="mb-6">
    <a href="<?= e(url('dashboard.php')) ?>" class="text-sm font-medium text-blue-700 hover:text-blue-800">← Voltar ao dashboard</a>
    <h2 class="mt-2 text-2xl font-semibold text-marinho-900"><?= e($secoes[$secao]['rotulo']) ?></h2>
    <p class="text-sm text-slate-500"><?= e($secoes[$secao]['descricao']) ?></p>
</div>

<nav class="mb-6 flex gap-2 overflow-x-auto pb-1" aria-label="Seções da administração">
    <?php foreach ($secoes as $chave => $item): ?>
        <a href="<?= e(url('admin.php?secao=' . $chave)) ?>"
           class="shrink-0 rounded-full px-3 py-1.5 text-sm font-medium <?= $chave === $secao ? 'bg-marinho-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' ?>"
           <?= $chave === $secao ? 'aria-current="page"' : '' ?>><?= e($item['rotulo']) ?></a>
    <?php endforeach; ?>
</nav>

<?php
require __DIR__ . '/includes/views/admin/' . ($secao === 'bem-estar' ? 'bem_estar' : $secao) . '.php';
require __DIR__ . '/includes/footer.php';
