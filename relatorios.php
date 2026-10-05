<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

$usuario = exigirPerfil('administrador');
$tituloPagina = 'Relatórios CSV';
require __DIR__ . '/includes/admin_apoio.php';
$classeCampo = classeCampoAdmin();

$relatorios = [
    'pdis' => 'PDIs: metas, prazos, progresso e status',
    'indicadores' => 'Indicadores por pessoa: progresso, status e classificação de risco',
    'usuarios' => 'Usuários: perfil, área, gestor e situação',
    'projetos' => 'Projetos e quantidade de participações ativas',
];

require __DIR__ . '/includes/header.php';
?>
<div class="mb-6">
    <a href="<?= e(url('dashboard.php')) ?>" class="text-sm font-medium text-blue-700 hover:text-blue-800">← Voltar ao dashboard</a>
    <h2 class="mt-2 text-2xl font-semibold text-marinho-900">Relatórios CSV</h2>
    <p class="text-sm text-slate-500">Exportação de indicadores e PDIs. Notas e comentários de bem-estar não entram no arquivo.</p>
</div>

<form method="post" action="<?= e(url('actions/exportar_relatorio.php')) ?>" class="max-w-xl space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-loading="Gerando...">
    <?= csrfCampo() ?>
    <label class="block text-sm font-medium text-slate-700">Relatório
        <select name="tipo" required class="<?= $classeCampo ?>">
            <?php foreach ($relatorios as $valor => $rotulo): ?>
                <option value="<?= e($valor) ?>"><?= e($rotulo) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800"><?= icone('download', 'h-4 w-4') ?> Baixar CSV</button>
</form>
<?php require __DIR__ . '/includes/footer.php';