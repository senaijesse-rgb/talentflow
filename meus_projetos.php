<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$usuario = exigirLogin();
$projetos = projetosDoColaborador($usuario['email']);
$tituloPagina = 'Meus projetos';

$bloco = static function (array $lista): void {
    if (!$lista) {
        echo '<p class="px-5 py-4 text-sm text-slate-500">Nenhum projeto neste grupo.</p>';
        return;
    }
    echo '<ul class="divide-y divide-slate-100">';
    foreach ($lista as $projeto) {
        echo '<li class="p-5">';
        echo '<div class="flex flex-wrap items-start justify-between gap-3">';
        echo '<div><p class="font-medium text-slate-900">' . e($projeto['nome_projeto']) . '</p>';
        echo '<p class="mt-1 text-sm text-slate-500">' . e($projeto['papel_no_projeto'] ?: 'Participante') . ' · ' . e($projeto['area_responsavel'] ?? '') . '</p></div>';
        echo badgeStatus(ucfirst((string) ($projeto['status_participacao'] ?? '')));
        echo '</div>';
        echo '<p class="mt-2 text-xs text-slate-500">De ' . e(formatarData($projeto['vinculo_inicio'])) . ($projeto['vinculo_fim'] !== '' ? ' até ' . e(formatarData($projeto['vinculo_fim'])) : ' · em andamento') . '</p>';
        echo '</li>';
    }
    echo '</ul>';
};

require __DIR__ . '/includes/header.php';
?>
<div class="mb-6">
    <h2 class="text-2xl font-semibold text-marinho-900">Meus projetos</h2>
    <p class="text-sm text-slate-500">Projetos em que você participa agora e os que já foram encerrados.</p>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-atuais">
        <h2 id="titulo-atuais" class="border-b border-slate-100 px-5 py-4 font-semibold text-marinho-900">Em andamento</h2>
        <?php $bloco($projetos['atuais']); ?>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="titulo-anteriores">
        <h2 id="titulo-anteriores" class="border-b border-slate-100 px-5 py-4 font-semibold text-marinho-900">Anteriores</h2>
        <?php $bloco($projetos['anteriores']); ?>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php';