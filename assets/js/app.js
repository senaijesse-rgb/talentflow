(function () {
    'use strict';

    const qs = (seletor, raiz = document) => raiz.querySelector(seletor);
    const qsa = (seletor, raiz = document) => Array.from(raiz.querySelectorAll(seletor));

    /* Menu lateral (mobile) */
    const sidebar = qs('#sidebar');
    const overlay = qs('#menu-overlay');
    const botaoAbrir = qs('[data-menu-open]');

    function alternarMenu(abrir) {
        if (!sidebar) return;
        sidebar.classList.toggle('-translate-x-full', !abrir);
        overlay?.classList.toggle('hidden', !abrir);
        botaoAbrir?.setAttribute('aria-expanded', String(abrir));
        document.body.classList.toggle('overflow-hidden', abrir);
    }

    botaoAbrir?.addEventListener('click', () => alternarMenu(true));
    qsa('[data-menu-close]').forEach((el) => el.addEventListener('click', () => alternarMenu(false)));
    document.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape') alternarMenu(false);
    });

    /* Mensagens flash */
    qsa('[data-flash-close]').forEach((botao) => {
        botao.addEventListener('click', () => botao.closest('[data-flash]')?.remove());
    });
    qsa('[data-flash][role="status"]').forEach((flash) => {
        setTimeout(() => flash.remove(), 8000);
    });

    /* Estado de carregamento ao enviar formulários */
    qsa('form[data-loading]').forEach((form) => {
        form.addEventListener('submit', () => {
            const botao = qs('button[type="submit"]', form);
            if (!botao || !form.checkValidity()) return;
            botao.disabled = true;
            botao.dataset.textoOriginal = botao.innerHTML;
            botao.innerHTML = '<span class="spinner"></span><span>' + (form.dataset.loading || 'Enviando...') + '</span>';
        });
    });

    /* Mostrar/ocultar senha */
    qsa('[data-toggle-senha]').forEach((botao) => {
        botao.addEventListener('click', () => {
            const campo = qs(botao.dataset.toggleSenha);
            if (!campo) return;
            const mostrar = campo.type === 'password';
            campo.type = mostrar ? 'text' : 'password';
            botao.setAttribute('aria-pressed', String(mostrar));
            botao.textContent = mostrar ? 'Ocultar' : 'Mostrar';
        });
    });

    /* Painéis alternáveis (ex.: "Esqueci minha senha") */
    qsa('[data-toggle-painel]').forEach((gatilho) => {
        gatilho.addEventListener('click', (ev) => {
            ev.preventDefault();
            const painel = qs(gatilho.dataset.togglePainel);
            if (!painel) return;
            const abrir = painel.classList.contains('hidden');
            painel.classList.toggle('hidden', !abrir);
            gatilho.setAttribute('aria-expanded', String(abrir));
            if (abrir) painel.focus();
        });
    });

    /* Validação de e-mail no login */
    const formLogin = qs('#form-login');
    if (formLogin) {
        formLogin.addEventListener('submit', (ev) => {
            const email = qs('#email', formLogin);
            const erro = qs('#erro-email', formLogin);
            const valido = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim());
            erro?.classList.toggle('hidden', valido);
            email.setAttribute('aria-invalid', String(!valido));
            if (!valido) {
                ev.preventDefault();
                email.focus();
            }
        });
    }

    /* Formulário de PDI: preencher dados da meta selecionada */
    const seletorPdi = qs('#id_pdi');
    if (seletorPdi) {
        const campos = {
            competencia: qs('#competencia'),
            meta: qs('#meta'),
            percentual: qs('#percentual'),
            faixa: qs('#percentual_faixa'),
            status: qs('#status'),
            prazo: qs('#prazo'),
        };

        const preencher = () => {
            const opcao = seletorPdi.selectedOptions[0];
            if (!opcao) return;
            campos.competencia.value = opcao.dataset.competencia || '';
            campos.meta.value = opcao.dataset.meta || '';
            campos.percentual.value = opcao.dataset.percentual || 0;
            campos.faixa.value = opcao.dataset.percentual || 0;
            campos.status.value = opcao.dataset.status || 'Em andamento';
            campos.prazo.value = opcao.dataset.prazo || '';
            const url = new URL(window.location.href);
            url.searchParams.set('id', seletorPdi.value);
            window.history.replaceState(null, '', url);
        };

        seletorPdi.addEventListener('change', preencher);
        campos.faixa?.addEventListener('input', () => (campos.percentual.value = campos.faixa.value));
        campos.percentual?.addEventListener('input', () => (campos.faixa.value = campos.percentual.value));
    }

    /* Contador de caracteres */
    qsa('[data-contador]').forEach((campo) => {
        const alvo = qs(campo.dataset.contador);
        const atualizar = () => alvo && (alvo.textContent = campo.value.length + '/' + campo.maxLength);
        campo.addEventListener('input', atualizar);
        atualizar();
    });

    /* Filtro textual de tabelas */
    qsa('[data-filtro-tabela]').forEach((campo) => {
        const tabela = qs(campo.dataset.filtroTabela);
        if (!tabela) return;
        campo.addEventListener('input', () => {
            const termo = campo.value.trim().toLowerCase();
            qsa('tbody tr[data-linha]', tabela).forEach((linha) => {
                linha.classList.toggle('hidden', termo !== '' && !linha.textContent.toLowerCase().includes(termo));
            });
        });
    });

    /* Foco inicial solicitado pela URL (ex.: ?foco=dificuldade) */
    const foco = qs('[data-foco-inicial]');
    if (foco) {
        foco.scrollIntoView({ block: 'center' });
        foco.focus({ preventScroll: true });
    }
})();
