<?php
/** @var array $usuario */
$periodos = [30 => 'Últimos 30 dias', 90 => 'Últimos 90 dias', 180 => 'Últimos 180 dias', 365 => 'Últimos 12 meses', 0 => 'Todo o período'];
$filtros = [
    'periodo' => array_key_exists((int) ($_GET['periodo'] ?? 90), $periodos) ? (int) ($_GET['periodo'] ?? 90) : 90,
    'area' => textoLimpo($_GET['area'] ?? '', 100),
    'gestor' => normalizarEmail($_GET['gestor'] ?? ''),
    'projeto' => textoLimpo($_GET['projeto'] ?? '', 50),
    'status' => in_array($_GET['status'] ?? '', STATUS_PDI, true) ? $_GET['status'] : '',
];

$ativos = usuariosAtivos();
$projetosTodos = db()->todos('Projetos');
$projetosAtivos = array_values(array_filter($projetosTodos, static fn ($p) => mb_strtolower($p['status']) === 'ativo'));
$areas = array_values(array_unique(array_filter(array_column($ativos, 'area'))));
sort($areas);
$gestores = array_values(array_filter($ativos, static fn ($u) => $u['perfil'] === 'gestor'));

$pessoas = array_values(array_filter($ativos, static fn ($u) => $u['perfil'] !== 'administrador'
    && ($filtros['area'] === '' || $u['area'] === $filtros['area'])
    && ($filtros['gestor'] === '' || normalizarEmail($u['gestor_email']) === $filtros['gestor'])));

$resumos = [];
foreach ($pessoas as $pessoa) {
    $resumo = resumoColaborador($pessoa);
    if ($filtros['projeto'] !== '' && !in_array($filtros['projeto'], array_column($resumo['projetos']['atuais'], 'id_projeto'), true)) {
        continue;
    }
    $resumos[$pessoa['email']] = ['pessoa' => $pessoa, 'resumo' => $resumo];
}

$pdis = [];
foreach ($resumos as ['pessoa' => $pessoa, 'resumo' => $resumo]) {
    foreach ($resumo['pdis'] as $pdi) {
        if ($filtros['status'] === '' || $pdi['status_efetivo'] === $filtros['status']) {
            $pdis[] = $pdi + ['area' => $pessoa['area']];
        }
    }
}

$distribuicao = array_fill_keys(STATUS_PDI, 0);
foreach ($pdis as $pdi) {
    $distribuicao[$pdi['status_efetivo']]++;
}
$totalPdis = count($pdis);
$taxaConclusao = $totalPdis ? $distribuicao['Concluído'] / $totalPdis * 100 : null;

$emails = array_keys($resumos);
$fimJanela = $filtros['periodo'] ?: 36500;
$satisfacaoGeral = agregadoCheckin($emails, 'satisfacao_empresa', 0, $fimJanela, 1);
$bemEstarGeral = agregadoCheckin($emails, 'bem_estar_trabalho', 0, $fimJanela, 1);
$altoRisco = count(array_filter($resumos, static fn ($r) => in_array($r['resumo']['risco']['classificacao'], ['ALTO', 'CRITICO'], true)));

$riscoPorProjeto = [];
foreach ($projetosAtivos as $projeto) {
    if ($filtros['projeto'] !== '' && $projeto['id_projeto'] !== $filtros['projeto']) {
        continue;
    }
    $scores = [];
    foreach ($resumos as ['resumo' => $resumo]) {
        if (in_array($projeto['id_projeto'], array_column($resumo['projetos']['atuais'], 'id_projeto'), true)) {
            $scores[] = $resumo['risco']['score'];
        }
    }
    if ($scores) {
        $riscoPorProjeto[] = [
            'projeto' => $projeto,
            'membros' => count($scores),
            'media' => media($scores),
            'altos' => count(array_filter($scores, static fn ($s) => $s >= (int) regra('limite_risco_alto', 50))),
        ];
    }
}
usort($riscoPorProjeto, static fn ($a, $b) => [$b['altos'], $b['media']] <=> [$a['altos'], $a['media']]);
$riscoPorProjeto = array_slice($riscoPorProjeto, 0, 5);
$classificador = new RiskService(regras());

$atencaoPorArea = [];
foreach ($pdis as $pdi) {
    if (in_array($pdi['status_efetivo'], ['Atenção', 'Atrasado'], true)) {
        $atencaoPorArea[$pdi['area'] ?: 'Sem área'] = ($atencaoPorArea[$pdi['area'] ?: 'Sem área'] ?? 0) + 1;
    }
}
arsort($atencaoPorArea);

$coresStatus = [
    'Em andamento' => 'bg-blue-600',
    'Atenção' => 'bg-amber-500',
    'Atrasado' => 'bg-red-600',
    'Aguardando validação' => 'bg-indigo-500',
    'Concluído' => 'bg-emerald-500',
];

$modulos = [
    ['rotulo' => 'Gestão de usuários', 'descricao' => 'Criar, editar, ativar e inativar usuários e perfis.', 'icone' => 'usuario', 'link' => 'admin.php?secao=usuarios'],
    ['rotulo' => 'Gestores e equipes', 'descricao' => 'Vincular colaboradores aos respectivos gestores.', 'icone' => 'equipe', 'link' => 'admin.php?secao=equipes'],
    ['rotulo' => 'Projetos', 'descricao' => 'Cadastro de projetos e participações.', 'icone' => 'pasta', 'link' => 'admin.php?secao=projetos'],
    ['rotulo' => 'PDIs', 'descricao' => 'Metas, prazos e status de todos os PDIs.', 'icone' => 'prancheta', 'link' => 'admin.php?secao=pdis'],
    ['rotulo' => 'Regras e faixas', 'descricao' => 'Pesos de risco, prazos, alertas e classificações.', 'icone' => 'ajustes', 'link' => 'admin.php?secao=regras'],
    ['rotulo' => 'Logs de auditoria', 'descricao' => 'Acessos, alterações e tentativas negadas.', 'icone' => 'escudo', 'link' => 'admin.php?secao=logs'],
    ['rotulo' => 'Integrações n8n', 'descricao' => 'URL do webhook e status do envio.', 'icone' => 'tendencia', 'link' => 'admin.php?secao=integracoes'],
    ['rotulo' => 'Modelos de e-mail', 'descricao' => 'Templates dos e-mails enviados via n8n/Gmail.', 'icone' => 'chat', 'link' => 'admin.php?secao=emails'],
    ['rotulo' => 'Relatórios CSV', 'descricao' => 'Exportação de indicadores e PDIs.', 'icone' => 'download', 'link' => 'relatorios.php'],
    ['rotulo' => 'Bem-estar individual', 'descricao' => 'Área restrita com registro de acesso.', 'icone' => 'cadeado', 'link' => 'admin.php?secao=bem-estar'],
];

$webhookConfigurado = N8NWebhookService::padrao()->configurado();
$classeCampo = 'mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20';
?>
<section id="visao-rh" class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
    <div>
        <h2 class="text-2xl font-semibold text-marinho-900">Visão geral da organização</h2>
        <p class="text-sm text-slate-500">Indicadores globais de desenvolvimento, clima e risco.</p>
    </div>
    <div class="flex flex-wrap gap-2 text-xs">
        <span class="rounded-full px-2.5 py-1 font-medium <?= DATA_SOURCE === 'sheets' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
            Fonte: <?= DATA_SOURCE === 'sheets' ? 'Google Sheets' : 'Dados de teste (mock)' ?>
        </span>
        <span class="rounded-full px-2.5 py-1 font-medium <?= $webhookConfigurado ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' ?>">
            Webhook n8n: <?= $webhookConfigurado ? 'configurado' : 'não configurado' ?>
        </span>
    </div>
</section>

<form method="get" class="mt-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-6" aria-label="Filtros">
    <label class="text-sm font-medium text-slate-700">Período
        <select name="periodo" class="<?= $classeCampo ?>">
            <?php foreach ($periodos as $valor => $rotulo): ?>
                <option value="<?= $valor ?>" <?= $filtros['periodo'] === $valor ? 'selected' : '' ?>><?= e($rotulo) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="text-sm font-medium text-slate-700">Área
        <select name="area" class="<?= $classeCampo ?>">
            <option value="">Todas</option>
            <?php foreach ($areas as $area): ?>
                <option value="<?= e($area) ?>" <?= $filtros['area'] === $area ? 'selected' : '' ?>><?= e($area) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="text-sm font-medium text-slate-700">Gestor
        <select name="gestor" class="<?= $classeCampo ?>">
            <option value="">Todos</option>
            <?php foreach ($gestores as $gestor): ?>
                <option value="<?= e($gestor['email']) ?>" <?= $filtros['gestor'] === normalizarEmail($gestor['email']) ? 'selected' : '' ?>><?= e($gestor['nome_completo']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="text-sm font-medium text-slate-700">Projeto
        <select name="projeto" class="<?= $classeCampo ?>">
            <option value="">Todos</option>
            <?php foreach ($projetosAtivos as $projeto): ?>
                <option value="<?= e($projeto['id_projeto']) ?>" <?= $filtros['projeto'] === $projeto['id_projeto'] ? 'selected' : '' ?>><?= e($projeto['nome_projeto']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="text-sm font-medium text-slate-700">Status da meta
        <select name="status" class="<?= $classeCampo ?>">
            <option value="">Todos</option>
            <?php foreach (STATUS_PDI as $status): ?>
                <option value="<?= e($status) ?>" <?= $filtros['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="flex items-end gap-2">
        <button type="submit" class="flex-1 rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Filtrar</button>
        <a href="<?= e(url('dashboard.php')) ?>" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Limpar</a>
    </div>
</form>

<section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores globais">
    <?= cardKpi('Colaboradores ativos', (string) count($resumos), 'Conforme filtros aplicados', 'equipe', 'marinho') ?>
    <?= cardKpi('Gestores', (string) count($gestores), 'Com acesso ativo', 'usuario', 'azul') ?>
    <?= cardKpi('Projetos ativos', (string) count($projetosAtivos), count($projetosTodos) . ' cadastrados no total', 'pasta', 'azul') ?>
    <?= cardKpi('PDIs ativos', (string) $totalPdis, ($totalPdis - $distribuicao['Concluído']) . ' metas em aberto', 'prancheta', 'marinho') ?>
    <?= cardKpi('Taxa de conclusão', $taxaConclusao === null ? '—' : formatarNumero($taxaConclusao, 0) . '%', 'Metas concluídas / total', 'check', 'verde') ?>
    <?= cardKpi('Satisfação geral', $satisfacaoGeral['media'] === null ? '—' : formatarNumero($satisfacaoGeral['media']) . '/5', $satisfacaoGeral['respondentes'] . ' respondentes no período', 'sorriso', 'azul') ?>
    <?= cardKpi('Bem-estar geral', $bemEstarGeral['media'] === null ? '—' : formatarNumero($bemEstarGeral['media']) . '/5', $bemEstarGeral['respondentes'] . ' respostas voluntárias · ' . ($bemEstarGeral['alertas'] ?? 0) . ' alerta(s)', 'escudo', 'marinho') ?>
    <?= cardKpi('Risco alto ou crítico', (string) $altoRisco, 'Colaboradores', 'bandeira', $altoRisco ? 'vermelho' : 'verde') ?>
</section>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="titulo-distribuicao">
        <h2 id="titulo-distribuicao" class="font-semibold text-marinho-900">Distribuição de status das metas</h2>
        <?php if (!$totalPdis): ?>
            <div class="mt-4"><?= estadoVazio('Sem metas', 'Nenhuma meta encontrada para os filtros selecionados.', 'prancheta') ?></div>
        <?php else: ?>
            <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                <?php foreach ($distribuicao as $status => $quantidade): if (!$quantidade) continue; ?>
                    <div class="<?= $coresStatus[$status] ?>" style="width: <?= round($quantidade / $totalPdis * 100, 2) ?>%"></div>
                <?php endforeach; ?>
            </div>
            <ul class="mt-4 space-y-2 text-sm">
                <?php foreach ($distribuicao as $status => $quantidade): ?>
                    <li class="flex items-center justify-between">
                        <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full <?= $coresStatus[$status] ?>"></span><?= e($status) ?></span>
                        <span class="tabular-nums text-slate-600"><?= $quantidade ?> <span class="text-xs text-slate-400">(<?= formatarNumero($quantidade / $totalPdis * 100, 0) ?>%)</span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><strong><?= $distribuicao['Atrasado'] ?></strong> meta(s) atrasada(s)</p>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="titulo-projetos-risco">
        <h2 id="titulo-projetos-risco" class="font-semibold text-marinho-900">Projetos com maior risco</h2>
        <?php if (!$riscoPorProjeto): ?>
            <div class="mt-4"><?= estadoVazio('Sem dados', 'Nenhum projeto ativo com participantes nos filtros atuais.', 'pasta') ?></div>
        <?php else: ?>
            <ul class="mt-4 divide-y divide-slate-100">
                <?php foreach ($riscoPorProjeto as $item): ?>
                    <li class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-900"><?= e($item['projeto']['nome_projeto']) ?></p>
                            <p class="text-xs text-slate-500"><?= $item['membros'] ?> participante(s) · <?= $item['altos'] ?> em risco alto · score médio <?= formatarNumero($item['media'], 0) ?></p>
                        </div>
                        <?= badgeRisco($classificador->classificar((int) round($item['media']))) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="titulo-areas">
        <h2 id="titulo-areas" class="font-semibold text-marinho-900">Áreas com mais PDIs em atenção</h2>
        <?php if (!$atencaoPorArea): ?>
            <div class="mt-4"><?= estadoVazio('Nenhuma área em atenção', 'Não há metas em atenção ou atrasadas nos filtros atuais.', 'check') ?></div>
        <?php else: $maximo = max($atencaoPorArea); ?>
            <ul class="mt-4 space-y-3">
                <?php foreach ($atencaoPorArea as $area => $quantidade): ?>
                    <li>
                        <div class="flex justify-between text-sm"><span class="text-slate-700"><?= e($area) ?></span><span class="tabular-nums text-slate-600"><?= $quantidade ?></span></div>
                        <div class="mt-1 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-amber-500" style="width: <?= round($quantidade / $maximo * 100) ?>%"></div></div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="mt-4 text-xs text-slate-500">Inclui metas com status Atenção e Atrasado.</p>
        <?php endif; ?>
    </section>
</div>

<section class="mt-6" aria-labelledby="titulo-modulos">
    <h2 id="titulo-modulos" class="font-semibold text-marinho-900">Administração</h2>
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <?php foreach ($modulos as $modulo):
            $disponivel = paginaDisponivel($modulo['link']);
            $tag = $disponivel ? 'a' : 'div';
            ?>
            <<?= $tag ?> <?= $disponivel ? 'href="' . e(url($modulo['link'])) . '"' : 'aria-disabled="true"' ?>
                class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm <?= $disponivel ? 'hover:border-blue-300 hover:shadow' : 'opacity-60' ?>">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-marinho-50 text-marinho-800"><?= icone($modulo['icone']) ?></span>
                <p class="mt-3 text-sm font-semibold text-slate-900"><?= e($modulo['rotulo']) ?><?= $disponivel ? '' : ' <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase text-slate-500">em breve</span>' ?></p>
                <p class="mt-1 text-xs text-slate-500"><?= e($modulo['descricao']) ?></p>
            </<?= $tag ?>>
        <?php endforeach; ?>
    </div>
</section>

<div class="mt-6">
    <?= avisoConfidencialidade('Área de indicadores confidenciais. Dados individuais de bem-estar são de acesso exclusivo do RH, devem ser usados apenas para ações de cuidado e nunca compartilhados com gestores ou automações de IA.') ?>
</div>
