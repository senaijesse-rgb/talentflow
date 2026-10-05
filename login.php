<?php

declare(strict_types=1);

require __DIR__ . '/includes/config.php';

if (usuarioAtual()) {
    redirect(rotaInicialPorPerfil(usuarioAtual()['perfil']));
}

$tituloPagina = 'Entrar';
$emailPreenchido = $_SESSION['login_email'] ?? ($_COOKIE['pdi_email'] ?? '');
$emailPreenchido = emailValido((string) $emailPreenchido) ? (string) $emailPreenchido : '';
unset($_SESSION['login_email']);

require __DIR__ . '/includes/header.php';
?>
<div class="grid min-h-screen lg:grid-cols-2">
    <section class="relative hidden overflow-hidden bg-gradient-to-br from-marinho-950 via-marinho-900 to-blue-900 p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div class="flex items-center gap-3">
            <?= logoPdiConnect('h-11 w-11') ?>
            <span class="text-lg font-semibold">PDI Connect</span>
        </div>
        <div class="max-w-md">
            <h2 class="text-3xl font-semibold leading-tight">Pessoas que se desenvolvem constroem projetos melhores.</h2>
            <p class="mt-4 text-blue-100">Acompanhe metas de desenvolvimento, projetos e a experiência das equipes com segurança e confidencialidade.</p>
        </div>
        <p class="flex items-center gap-2 text-xs text-blue-200/80"><?= icone('escudo', 'h-4 w-4') ?> Acesso monitorado e registrado para auditoria.</p>
    </section>

    <section class="flex flex-col justify-center bg-white px-6 py-12 sm:px-12">
        <div class="mx-auto w-full max-w-sm">
            <div class="flex flex-col items-center text-center lg:items-start lg:text-left">
                <?= logoPdiConnect('h-12 w-12') ?>
                <h1 class="mt-4 text-2xl font-semibold text-marinho-900">PDI Connect</h1>
                <p class="mt-1 text-sm text-slate-500">Gestão de Pessoas, Projetos e Desenvolvimento</p>
            </div>

            <div class="mt-8"><?= renderizarFlashes() ?></div>

            <form id="form-login" method="post" action="<?= e(url('actions/login_action.php')) ?>" class="space-y-5" novalidate data-loading="Entrando...">
                <?= csrfCampo() ?>
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">E-mail corporativo</label>
                    <input id="email" name="email" type="email" required autocomplete="username" inputmode="email" maxlength="254"
                           value="<?= e($emailPreenchido) ?>" <?= $emailPreenchido === '' ? 'autofocus' : '' ?>
                           aria-describedby="erro-email"
                           class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20 aria-[invalid=true]:border-red-500"
                           placeholder="nome@empresa.com.br">
                    <p id="erro-email" class="mt-1.5 hidden text-xs text-red-600">Informe um e-mail válido, por exemplo nome@empresa.com.br.</p>
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <label for="senha" class="block text-sm font-medium text-slate-700">Senha</label>
                        <button type="button" class="text-xs font-medium text-blue-700 hover:text-blue-800" data-toggle-senha="#senha" aria-pressed="false">Mostrar</button>
                    </div>
                    <input id="senha" name="senha" type="password" required autocomplete="current-password" maxlength="200" <?= $emailPreenchido !== '' ? 'autofocus' : '' ?>
                           class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20">
                </div>

                <div class="flex items-center justify-between gap-4">
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="lembrar" value="1" class="h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-600" <?= isset($_COOKIE['pdi_email']) ? 'checked' : '' ?>>
                        Lembrar acesso
                    </label>
                    <a href="#recuperar-senha" class="text-sm font-medium text-blue-700 hover:text-blue-800" data-toggle-painel="#recuperar-senha" aria-expanded="false" aria-controls="recuperar-senha">Esqueci minha senha</a>
                </div>

                <div id="recuperar-senha" tabindex="-1" class="hidden rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 focus:outline-none">
                    <p class="font-medium">Recuperação de senha</p>
                    <p class="mt-1">Por segurança, a redefinição é feita pelo Administrador RH. Envie uma solicitação pelo canal oficial de RH informando seu e-mail corporativo.</p>
                </div>

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 disabled:opacity-70">
                    Entrar
                </button>
            </form>

            <?php if (APP_ENV === 'local'): ?>
                <p class="mt-6 text-center text-xs text-slate-500">
                    Ambiente local: <a href="<?= e(url('index.php')) ?>" class="font-medium text-blue-700 hover:underline">ver credenciais de teste</a>
                </p>
            <?php endif; ?>

            <p class="mt-10 border-t border-slate-100 pt-6 text-center text-xs leading-relaxed text-slate-500">
                <?= icone('cadeado', 'mr-1 inline h-3.5 w-3.5 align-[-2px]') ?>
                Sistema de uso restrito. As informações aqui tratadas são confidenciais e protegidas pela LGPD. Acessos são registrados.
            </p>
        </div>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
