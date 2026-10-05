<?php
/** @var string $classeCampo */
$resultadoFiltro = in_array($_GET['resultado'] ?? '', ['sucesso', 'falha', 'negado'], true) ? (string) $_GET['resultado'] : '';
$busca = mb_strtolower(textoLimpo($_GET['q'] ?? '', 80));
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 40;

$logs = ordenarPorDataDesc(db()->todos('Logs_Auditoria'), 'data_hora');
if ($resultadoFiltro !== '') {
    $logs = array_values(array_filter($logs, static fn (array $log): bool => $log['resultado'] === $resultadoFiltro));
}
if ($busca !== '') {
    $logs = array_values(array_filter($logs, static function (array $log) use ($busca): bool {
        $texto = mb_strtolower(implode(' ', [$log['email_usuario'], $log['acao'], $log['entidade'], $log['descricao'], $log['id_entidade']]));

        return str_contains($texto, $busca);
    }));
}
$total = count($logs);
$paginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $paginas);
$visiveis = array_slice($logs, ($pagina - 1) * $porPagina, $porPagina);
$consulta = static fn (int $numero): string => 'admin.php?' . http_build_query(array_filter([
    'secao' => 'logs',
    'resultado' => $resultadoFiltro,
    'q' => $busca,
    'pagina' => $numero > 1 ? $numero : null,
]));
?>
<form method="get" class="mb-4 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-4">
    <input type="hidden" name="secao" value="logs">
    <label class="text-sm font-medium text-slate-700 sm:col-span-2">Busca
        <input type="search" name="q" value="<?= e($busca) ?>" maxlength="80" class="<?= $classeCampo ?>" placeholder="E-mail, ação ou descrição">
    </label>
    <label class="text-sm font-medium text-slate-700">Resultado
        <select name="resultado" class="<?= $classeCampo ?>">
            <option value="">Todos</option>
            <?php foreach (['sucesso' => 'Sucesso', 'falha' => 'Falha', 'negado' => 'Negado'] as $valor => $rotulo): ?>
                <option value="<?= e($valor) ?>" <?= $resultadoFiltro === $valor ? 'selected' : '' ?>><?= e($rotulo) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="flex items-end"><button type="submit" class="w-full rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Filtrar</button></div>
</form>

<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-slate-100 text-sm">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-5 py-3">Quando</th>
                <th class="px-5 py-3">Quem</th>
                <th class="px-5 py-3">Ação</th>
                <th class="px-5 py-3">Descrição</th>
                <th class="px-5 py-3">Resultado</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($visiveis as $log): ?>
                <tr>
                    <td class="whitespace-nowrap px-5 py-3 text-slate-600"><?= e(formatarData($log['data_hora'], true)) ?></td>
                    <td class="px-5 py-3">
                        <p class="font-medium text-slate-900"><?= e($log['email_usuario']) ?></p>
                        <p class="text-xs text-slate-500"><?= e(rotuloPerfil($log['perfil_usuario'])) ?></p>
                    </td>
                    <td class="px-5 py-3 text-slate-700"><?= e($log['acao']) ?><span class="block text-xs text-slate-400"><?= e($log['entidade']) ?></span></td>
                    <td class="max-w-md px-5 py-3 text-slate-600"><?= e($log['descricao']) ?></td>
                    <td class="px-5 py-3"><?= e($log['resultado']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (!$visiveis): ?>
        <div class="p-5"><?= estadoVazio('Nenhum registro', 'Não há logs para este filtro.', 'escudo') ?></div>
    <?php endif; ?>
</div>
<?php if ($paginas > 1): ?>
<nav class="mt-4 flex items-center justify-between text-sm" aria-label="Páginas dos logs">
    <a class="<?= $pagina > 1 ? 'text-blue-700' : 'pointer-events-none text-slate-300' ?>" href="<?= e(url($consulta($pagina - 1))) ?>">Anterior</a>
    <span class="text-slate-500"><?= $pagina ?> de <?= $paginas ?> · <?= $total ?> registro(s)</span>
    <a class="<?= $pagina < $paginas ? 'text-blue-700' : 'pointer-events-none text-slate-300' ?>" href="<?= e(url($consulta($pagina + 1))) ?>">Próxima</a>
</nav>
<?php endif; ?>
