<?php
/** @var string|null $tituloPagina */
$usuarioLayout = usuarioAtual();
$arquivoAtual = basename($_SERVER['SCRIPT_NAME'] ?? '');
$tituloPagina ??= 'Painel';
?><!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
    <title><?= e($tituloPagina . ' · ' . APP_NAME) ?></title>
    <link rel="icon" href="<?= e(url('assets/img/logo.svg')) ?>" type="image/svg+xml">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        marinho: { 50: '#eef3fb', 100: '#d6e2f5', 700: '#1a3f73', 800: '#12305a', 900: '#0b1f3a', 950: '#071428' }
                    },
                    fontFamily: { sans: ['Inter', 'Segoe UI', 'system-ui', '-apple-system', 'Roboto', 'sans-serif'] }
                }
            }
        };
    </script>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="h-full font-sans text-slate-800 antialiased">
<a href="#conteudo" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">Pular para o conteúdo</a>
<?php if ($usuarioLayout): ?>
<div class="min-h-full">
    <div id="menu-overlay" class="fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden" data-menu-close></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-marinho-900 text-white transition-transform duration-200 lg:translate-x-0" aria-label="Menu principal">
        <div class="flex h-16 items-center justify-between gap-3 border-b border-white/10 px-5">
            <a href="<?= e(url('dashboard.php')) ?>" class="flex items-center gap-3">
                <?= logoPdiConnect('h-9 w-9') ?>
                <span>
                    <span class="block text-base font-semibold leading-tight">PDI Connect</span>
                    <span class="block text-[11px] text-blue-200/80">Pessoas · Projetos · Desenvolvimento</span>
                </span>
            </a>
            <button type="button" class="rounded-lg p-1.5 text-blue-100 hover:bg-white/10 lg:hidden" data-menu-close aria-label="Fechar menu">
                <?= icone('fechar') ?>
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5">
            <?php foreach (itensMenu($usuarioLayout['perfil']) as $item):
                $ativo = $arquivoAtual === $item['arquivo'];
                $disponivel = paginaDisponivel($item['arquivo']);
                $classes = $ativo ? 'bg-white/10 text-white' : 'text-blue-100/90 hover:bg-white/5 hover:text-white';
                ?>
                <?php if ($disponivel): ?>
                    <a href="<?= e(url($item['arquivo'])) ?>" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium <?= $classes ?>" <?= $ativo ? 'aria-current="page"' : '' ?>>
                        <?= icone($item['icone'], 'h-5 w-5 shrink-0') ?>
                        <?= e($item['rotulo']) ?>
                    </a>
                <?php else: ?>
                    <span class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-blue-100/40" title="Disponível em breve">
                        <?= icone($item['icone'], 'h-5 w-5 shrink-0') ?>
                        <?= e($item['rotulo']) ?>
                        <span class="ml-auto rounded bg-white/10 px-1.5 py-0.5 text-[10px] uppercase tracking-wide">em breve</span>
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="m-3 rounded-xl bg-white/5 p-4 text-xs text-blue-100/80">
            <div class="mb-1 flex items-center gap-2 font-semibold text-white"><?= icone('cadeado', 'h-4 w-4') ?> Ambiente confidencial</div>
            Acessos e alterações são registrados para fins de auditoria.
        </div>
    </aside>

    <div class="lg:pl-72">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8">
            <button type="button" class="-ml-1 rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" data-menu-open aria-controls="sidebar" aria-expanded="false" aria-label="Abrir menu">
                <?= icone('menu', 'h-6 w-6') ?>
            </button>
            <h1 class="truncate text-base font-semibold text-marinho-900 sm:text-lg"><?= e($tituloPagina) ?></h1>

            <div class="ml-auto flex items-center gap-3">
                <?php if (DATA_SOURCE === 'mock'): ?>
                    <span class="hidden rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 md:inline" title="Dados fictícios armazenados localmente">Dados de teste</span>
                <?php endif; ?>
                <div class="hidden text-right sm:block">
                    <p class="text-sm font-medium leading-tight text-slate-900"><?= e($usuarioLayout['nome']) ?></p>
                    <p class="text-xs text-slate-500"><?= e(rotuloPerfil($usuarioLayout['perfil'])) ?></p>
                </div>
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-marinho-800 text-sm font-semibold text-white" aria-hidden="true"><?= e(iniciais($usuarioLayout['nome'])) ?></span>
                <form method="post" action="<?= e(url('logout.php')) ?>">
                    <?= csrfCampo() ?>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600">
                        <?= icone('sair', 'h-4 w-4') ?><span class="hidden sm:inline">Sair</span>
                    </button>
                </form>
            </div>
        </header>

        <main id="conteudo" class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <?= renderizarFlashes() ?>
<?php else: ?>
<main id="conteudo">
<?php endif; ?>
