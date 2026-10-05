<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

if (usuarioAtual()) {
    redirect('dashboard.php');
}

$tituloPagina = 'Bem-vindo';
$credenciaisTeste = APP_ENV === 'local' && DATA_SOURCE === 'mock' ? [
    ['perfil' => 'Administrador RH', 'email' => 'rh@empresa.example'],
    ['perfil' => 'Gestor', 'email' => 'gestor@empresa.example'],
    ['perfil' => 'Gestor', 'email' => 'gestor2@empresa.example'],
    ['perfil' => 'Colaborador', 'email' => 'colaborador@empresa.example'],
    ['perfil' => 'Colaborador', 'email' => 'colaborador2@empresa.example'],
    ['perfil' => 'Colaborador (inativo)', 'email' => 'colaborador5@empresa.example'],
] : [];

require __DIR__ . '/includes/header.php';
?>
<div class="min-h-screen bg-gradient-to-br from-marinho-950 via-marinho-900 to-blue-900 text-white">
    <div class="mx-auto flex max-w-6xl flex-col px-6 py-10 lg:py-16">
        <header class="flex items-center gap-3">
            <?= logoPdiConnect('h-11 w-11') ?>
            <div>
                <p class="text-lg font-semibold">PDI Connect</p>
                <p class="text-sm text-blue-200">Gestão de Pessoas, Projetos e Desenvolvimento</p>
            </div>
        </header>

        <section class="mt-14 grid gap-12 lg:grid-cols-2 lg:items-center">
            <div>
                <h1 class="text-3xl font-semibold leading-tight sm:text-4xl">Desenvolvimento profissional acompanhado de perto, com privacidade.</h1>
                <p class="mt-5 max-w-xl text-blue-100">
                    Planos de Desenvolvimento Individual, projetos, check-ins de experiência e indicadores de risco em um só lugar —
                    com controle de acesso por perfil e dados sensíveis protegidos.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="<?= e(url('login.php')) ?>" class="inline-flex items-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-semibold text-marinho-900 shadow hover:bg-blue-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-marinho-900">
                        Acessar o sistema
                    </a>
                </div>
                <ul class="mt-10 grid gap-4 text-sm text-blue-100 sm:grid-cols-2">
                    <li class="flex gap-2"><?= icone('prancheta', 'h-5 w-5 shrink-0 text-blue-300') ?> PDIs com metas, prazos e histórico</li>
                    <li class="flex gap-2"><?= icone('equipe', 'h-5 w-5 shrink-0 text-blue-300') ?> Visão por equipe para gestores</li>
                    <li class="flex gap-2"><?= icone('grafico', 'h-5 w-5 shrink-0 text-blue-300') ?> Indicadores de clima e risco</li>
                    <li class="flex gap-2"><?= icone('escudo', 'h-5 w-5 shrink-0 text-blue-300') ?> Privacidade e trilha de auditoria</li>
                </ul>
            </div>

            <?php if ($credenciaisTeste): ?>
                <div class="rounded-2xl bg-white p-6 text-slate-800 shadow-xl">
                    <div class="flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                        <?= icone('alerta', 'h-5 w-5 shrink-0 text-amber-600') ?>
                        <p><strong>Somente ambiente local.</strong> Estas credenciais aparecem apenas com <code>APP_ENV=local</code> e <code>DATA_SOURCE=mock</code>. Nunca utilize em produção.</p>
                    </div>
                    <h2 class="mt-5 font-semibold text-marinho-900">Credenciais de teste</h2>
                    <p class="mt-1 text-sm text-slate-500">Senha para todos os usuários: <code class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-800">Senha@123</code></p>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-wide text-slate-500">
                                <tr><th class="py-2 pr-4">Perfil</th><th class="py-2">E-mail</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($credenciaisTeste as $credencial): ?>
                                    <tr>
                                        <td class="py-2 pr-4 text-slate-600"><?= e($credencial['perfil']) ?></td>
                                        <td class="py-2 font-mono text-xs text-slate-800"><?= e($credencial['email']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-4 text-xs text-slate-500">Para restaurar os dados de teste, apague o arquivo <code>storage/mock_db.json</code>.</p>
                </div>
            <?php endif; ?>
        </section>

        <footer class="mt-16 border-t border-white/10 pt-6 text-xs text-blue-200/80">
            <?= e(AVISO_CONFIDENCIALIDADE) ?>
        </footer>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
