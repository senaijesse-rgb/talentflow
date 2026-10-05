<?php

declare(strict_types=1);

require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/admin_apoio.php';

exigirPost();
$usuario = exigirPerfil('administrador');

$acao = textoLimpo($_POST['acao'] ?? '', 40);
$secao = textoLimpo($_POST['secao'] ?? 'usuarios', 40);
$voltarExtra = [];
if (in_array($acao, ['salvar_projeto', 'encerrar_vinculo', 'adicionar_vinculo'], true)) {
    $voltarExtra['editar'] = textoLimpo($_POST['id_projeto'] ?? '', 50);
}
exigirCsrf('admin.php?' . http_build_query(['secao' => $secao] + array_filter($voltarExtra)));

match ($acao) {
    'salvar_usuario' => salvarUsuarioAdmin($usuario),
    'salvar_equipes' => salvarEquipesAdmin($usuario),
    'salvar_projeto' => salvarProjetoAdmin($usuario),
    'adicionar_vinculo' => adicionarVinculoAdmin($usuario),
    'encerrar_vinculo' => encerrarVinculoAdmin($usuario),
    'salvar_pdi' => salvarPdiAdmin($usuario),
    'salvar_regras' => salvarRegrasAdmin($usuario),
    'salvar_integracao' => salvarIntegracaoAdmin($usuario),
    'testar_webhook' => testarWebhookAdmin(),
    'salvar_emails' => salvarEmailsAdmin($usuario),
    default => abortarAdmin($secao, 'Ação não reconhecida.'),
};

function abortarAdmin(string $secao, string $mensagem, array $extra = []): never
{
    flash('erro', $mensagem);
    voltarAdmin($secao, $extra);
}

function salvarUsuarioAdmin(array $ator): never
{
    $email = normalizarEmail((string) ($_POST['email'] ?? ''));
    $nome = textoLimpo($_POST['nome_completo'] ?? '', 120);
    $perfil = (string) ($_POST['perfil'] ?? '');
    $cargo = textoLimpo($_POST['cargo'] ?? '', 80);
    $area = textoLimpo($_POST['area'] ?? '', 80);
    $gestor = normalizarEmail((string) ($_POST['gestor_email'] ?? ''));
    $ativo = ($_POST['ativo'] ?? '') === 'sim' ? 'sim' : 'nao';
    $senha = (string) ($_POST['senha'] ?? '');
    $existente = buscarUsuario($email);

    $erros = validarUsuarioAdmin($email, $nome, $perfil, $cargo, $gestor, $senha, $existente, $ativo, $ator);
    if ($erros) {
        foreach ($erros as $erro) {
            flash('erro', $erro);
        }
        voltarAdmin('usuarios', $existente ? ['editar' => $email] : ['novo' => '1']);
    }

    $dados = [
        'nome_completo' => $nome,
        'perfil' => $perfil,
        'cargo' => $cargo,
        'area' => $area,
        'gestor_email' => $gestor,
        'ativo' => $ativo,
    ];
    if ($senha !== '') {
        $dados['senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
    }

    if ($existente) {
        db()->atualizar('Usuarios', 'email', $email, $dados);
        registrarLog('editar_usuario', 'Usuarios', $email, "Perfil {$perfil}; ativo {$ativo}.");
        flash('sucesso', 'Usuário atualizado.');
    } else {
        db()->inserir('Usuarios', $dados + [
            'id_usuario' => gerarId('USR'),
            'email' => $email,
            'data_criacao' => agoraIso(),
            'ultimo_login' => '',
        ]);
        registrarLog('criar_usuario', 'Usuarios', $email, "Perfil {$perfil}.");
        flash('sucesso', 'Usuário criado.');
    }

    voltarAdmin('usuarios');
}

function validarUsuarioAdmin(string $email, string $nome, string $perfil, string $cargo, string $gestor, string $senha, ?array $existente, string $ativo, array $ator): array
{
    $erros = [];
    if (!emailValido($email)) {
        $erros[] = 'Informe um e-mail válido.';
    }
    if (mb_strlen($nome) < 3) {
        $erros[] = 'Informe o nome completo.';
    }
    if (!isset(PERFIS[$perfil])) {
        $erros[] = 'Selecione um perfil válido.';
    }
    if ($cargo === '') {
        $erros[] = 'Informe o cargo.';
    }
    if ($gestor === $email && $email !== '') {
        $erros[] = 'A pessoa não pode ser gestora de si mesma.';
    }
    if ($gestor !== '') {
        $gestorUsuario = buscarUsuario($gestor);
        if ($gestorUsuario === null || !boolValor($gestorUsuario['ativo']) || !in_array($gestorUsuario['perfil'], ['gestor', 'administrador'], true)) {
            $erros[] = 'O gestor precisa ser um usuário ativo com perfil de gestor ou RH.';
        }
    }
    if ($existente === null && mb_strlen($senha) < 8) {
        $erros[] = 'A senha inicial precisa ter pelo menos 8 caracteres.';
    }
    if ($senha !== '' && mb_strlen($senha) < 8) {
        $erros[] = 'A nova senha precisa ter pelo menos 8 caracteres.';
    }
    if ($existente && $email === $ator['email'] && ($ativo !== 'sim' || $perfil !== 'administrador')) {
        $erros[] = 'Você não pode inativar a própria conta nem sair do perfil de RH por aqui.';
    }
    if ($existente && $existente['perfil'] === 'administrador' && boolValor($existente['ativo']) && ($ativo !== 'sim' || $perfil !== 'administrador') && quantidadeAdminsAtivos() <= 1) {
        $erros[] = 'Mantenha pelo menos um administrador RH ativo.';
    }

    return $erros;
}

function salvarEquipesAdmin(array $ator): never
{
    $enviados = is_array($_POST['gestor'] ?? null) ? $_POST['gestor'] : [];
    $elegiveis = [];
    foreach (gestoresElegiveis() as $gestor) {
        $elegiveis[normalizarEmail($gestor['email'])] = true;
    }

    $alterados = 0;
    foreach (db()->todos('Usuarios') as $pessoa) {
        $email = normalizarEmail($pessoa['email']);
        if (!array_key_exists($email, $enviados)) {
            continue;
        }
        $novo = normalizarEmail((string) $enviados[$email]);
        $atual = normalizarEmail($pessoa['gestor_email']);
        if ($novo === $atual) {
            continue;
        }
        if ($novo === $email) {
            flash('erro', 'Não é possível vincular ' . $pessoa['nome_completo'] . ' a si mesmo.');
            continue;
        }
        if ($novo !== '' && !isset($elegiveis[$novo])) {
            flash('erro', 'Gestor inválido para ' . $pessoa['nome_completo'] . '.');
            continue;
        }
        db()->atualizar('Usuarios', 'email', $email, ['gestor_email' => $novo]);
        registrarLog('vincular_gestor', 'Usuarios', $email, $novo === '' ? 'Gestor removido.' : 'Gestor definido.');
        $alterados++;
    }

    flash($alterados ? 'sucesso' : 'aviso', $alterados ? "{$alterados} vínculo(s) atualizado(s)." : 'Nenhum vínculo foi alterado.');
    unset($ator);
    voltarAdmin('equipes');
}

function salvarProjetoAdmin(array $ator): never
{
    $id = textoLimpo($_POST['id_projeto'] ?? '', 50);
    $nome = textoLimpo($_POST['nome_projeto'] ?? '', 120);
    $descricao = textoLimpo($_POST['descricao'] ?? '', 500);
    $area = textoLimpo($_POST['area_responsavel'] ?? '', 80);
    $gestor = normalizarEmail((string) ($_POST['gestor_email'] ?? ''));
    $status = (string) ($_POST['status'] ?? '');
    $inicio = (string) ($_POST['data_inicio'] ?? '');
    $fim = (string) ($_POST['data_fim'] ?? '');
    $statuses = ['ativo', 'concluido', 'encerrado'];

    $erros = [];
    if (mb_strlen($nome) < 3) {
        $erros[] = 'Informe o nome do projeto.';
    }
    if (!in_array($status, $statuses, true)) {
        $erros[] = 'Selecione o status do projeto.';
    }
    if (!dataValida($inicio)) {
        $erros[] = 'Informe a data de início.';
    }
    if ($fim !== '' && !dataValida($fim)) {
        $erros[] = 'A data de fim é inválida.';
    }
    if ($gestor !== '') {
        $gestorUsuario = buscarUsuario($gestor);
        if ($gestorUsuario === null || !in_array($gestorUsuario['perfil'], ['gestor', 'administrador'], true)) {
            $erros[] = 'O responsável precisa ter perfil de gestor ou RH.';
        }
    }
    if ($erros) {
        foreach ($erros as $erro) {
            flash('erro', $erro);
        }
        voltarAdmin('projetos', $id !== '' ? ['editar' => $id] : ['novo' => '1']);
    }

    $dados = [
        'nome_projeto' => $nome,
        'descricao' => $descricao,
        'area_responsavel' => $area,
        'gestor_email' => $gestor,
        'status' => $status,
        'data_inicio' => $inicio,
        'data_fim' => $fim,
    ];
    $existente = $id !== '' ? db()->buscarUm('Projetos', 'id_projeto', $id) : null;
    if ($existente) {
        db()->atualizar('Projetos', 'id_projeto', $id, $dados);
        registrarLog('editar_projeto', 'Projetos', $id, "Status {$status}.");
        flash('sucesso', 'Projeto atualizado.');
    } else {
        $id = gerarId('PRJ');
        db()->inserir('Projetos', $dados + ['id_projeto' => $id]);
        registrarLog('criar_projeto', 'Projetos', $id, $nome);
        flash('sucesso', 'Projeto criado.');
    }
    unset($ator);
    voltarAdmin('projetos', ['editar' => $id]);
}

function adicionarVinculoAdmin(array $ator): never
{
    $idProjeto = textoLimpo($_POST['id_projeto'] ?? '', 50);
    $email = normalizarEmail((string) ($_POST['email_colaborador'] ?? ''));
    $papel = textoLimpo($_POST['papel_no_projeto'] ?? '', 80);
    $inicio = (string) ($_POST['data_inicio'] ?? '');
    $projeto = db()->buscarUm('Projetos', 'id_projeto', $idProjeto);
    $pessoa = buscarUsuario($email);

    if ($projeto === null || $pessoa === null || !boolValor($pessoa['ativo']) || $papel === '' || !dataValida($inicio)) {
        abortarAdmin('projetos', 'Informe um projeto, uma pessoa ativa, o papel e a data de início.', ['editar' => $idProjeto]);
    }

    foreach (db()->buscar('Colaborador_Projetos', 'id_projeto', $idProjeto) as $vinculo) {
        if (normalizarEmail($vinculo['email_colaborador']) === $email && mb_strtolower($vinculo['status_participacao']) === 'ativo') {
            abortarAdmin('projetos', 'Esta pessoa já participa deste projeto.', ['editar' => $idProjeto]);
        }
    }

    $id = gerarId('VIN');
    db()->inserir('Colaborador_Projetos', [
        'id_vinculo' => $id,
        'email_colaborador' => $email,
        'id_projeto' => $idProjeto,
        'papel_no_projeto' => $papel,
        'data_inicio' => $inicio,
        'data_fim' => '',
        'status_participacao' => 'ativo',
    ]);
    registrarLog('vincular_projeto', 'Colaborador_Projetos', $id, "{$email} em {$idProjeto}.");
    unset($ator);
    flash('sucesso', 'Participação registrada.');
    voltarAdmin('projetos', ['editar' => $idProjeto]);
}

function encerrarVinculoAdmin(array $ator): never
{
    $id = textoLimpo($_POST['id_vinculo'] ?? '', 50);
    $idProjeto = textoLimpo($_POST['id_projeto'] ?? '', 50);
    $vinculo = db()->buscarUm('Colaborador_Projetos', 'id_vinculo', $id);
    if ($vinculo === null || $vinculo['id_projeto'] !== $idProjeto) {
        abortarAdmin('projetos', 'Participação não encontrada.', ['editar' => $idProjeto]);
    }

    db()->atualizar('Colaborador_Projetos', 'id_vinculo', $id, [
        'status_participacao' => 'encerrado',
        'data_fim' => date('Y-m-d'),
    ]);
    registrarLog('encerrar_vinculo', 'Colaborador_Projetos', $id, $vinculo['email_colaborador']);
    unset($ator);
    flash('sucesso', 'Participação encerrada.');
    voltarAdmin('projetos', ['editar' => $idProjeto]);
}

function salvarPdiAdmin(array $ator): never
{
    $id = textoLimpo($_POST['id_pdi'] ?? '', 50);
    $email = normalizarEmail((string) ($_POST['email_colaborador'] ?? ''));
    $competencia = textoLimpo($_POST['competencia'] ?? '', 80);
    $meta = textoLimpo($_POST['meta'] ?? '', 300);
    $percentual = inteiroEntre($_POST['percentual_conclusao'] ?? null, 0, 100);
    $status = (string) ($_POST['status'] ?? '');
    $prazo = (string) ($_POST['prazo'] ?? '');
    $inicio = (string) ($_POST['data_inicio'] ?? '');
    $ativo = ($_POST['ativo'] ?? 'sim') === 'sim' ? 'sim' : 'nao';
    $pessoa = buscarUsuario($email);
    $existente = $id !== '' ? buscarPdi($id) : null;

    $erros = [];
    if ($pessoa === null) {
        $erros[] = 'Selecione um colaborador.';
    }
    if (mb_strlen($competencia) < 2 || mb_strlen($meta) < 5) {
        $erros[] = 'Informe a competência e a meta.';
    }
    if ($percentual === null || !in_array($status, STATUS_PDI, true) || !dataValida($prazo)) {
        $erros[] = 'Percentual, status e prazo precisam ser válidos.';
    }
    if ($existente === null && !dataValida($inicio)) {
        $erros[] = 'Informe a data de início.';
    }
    if ($existente && normalizarEmail($existente['email_colaborador']) !== $email) {
        $erros[] = 'O colaborador da meta não pode ser trocado.';
    }
    if ($erros) {
        foreach ($erros as $erro) {
            flash('erro', $erro);
        }
        voltarAdmin('pdis', $existente ? ['editar' => $id] : ['novo' => '1']);
    }

    $gestor = normalizarEmail($pessoa['gestor_email'] ?? '');
    $dados = [
        'competencia' => $competencia,
        'meta' => $meta,
        'percentual_conclusao' => $percentual,
        'status' => $status,
        'prazo' => $prazo,
        'gestor_email' => $gestor,
        'ativo' => $ativo,
        'data_conclusao' => $status === 'Concluído' ? ($existente['data_conclusao'] ?: date('Y-m-d')) : '',
    ];

    if ($existente) {
        db()->atualizar('PDIs', 'id_pdi', $id, $dados);
        registrarLog('editar_pdi', 'PDIs', $id, "Status {$status}; {$percentual}%.");
        flash('sucesso', 'PDI atualizado.');
    } else {
        $id = gerarId('PDI');
        db()->inserir('PDIs', $dados + [
            'id_pdi' => $id,
            'email_colaborador' => $email,
            'data_inicio' => $inicio,
            'data_ultima_atualizacao' => agoraIso(),
        ]);
        registrarLog('criar_pdi', 'PDIs', $id, $email);
        flash('sucesso', 'PDI criado.');
    }
    unset($ator);
    voltarAdmin('pdis');
}

function salvarRegrasAdmin(array $ator): never
{
    $enviados = is_array($_POST['valor'] ?? null) ? $_POST['valor'] : [];
    $catalogo = catalogoRegrasAdmin();
    $erros = [];
    $gravar = [];

    foreach ($catalogo as $chave => $regra) {
        $bruto = trim((string) ($enviados[$chave] ?? ''));
        if ($regra['tipo'] === 'simnao') {
            if (!in_array($bruto, ['sim', 'nao'], true)) {
                $erros[] = $regra['rotulo'] . ': escolha sim ou não.';
                continue;
            }
            $gravar[$chave] = $bruto;
            continue;
        }
        $numero = filter_var($bruto, FILTER_VALIDATE_INT);
        if ($numero === false || $numero < $regra['min'] || $numero > $regra['max']) {
            $erros[] = $regra['rotulo'] . ': informe um inteiro entre ' . $regra['min'] . ' e ' . $regra['max'] . '.';
            continue;
        }
        $gravar[$chave] = (string) $numero;
    }

    $medio = (int) ($gravar['limite_risco_medio'] ?? 0);
    $alto = (int) ($gravar['limite_risco_alto'] ?? 0);
    $critico = (int) ($gravar['limite_risco_critico'] ?? 0);
    if (isset($gravar['limite_risco_medio'], $gravar['limite_risco_alto'], $gravar['limite_risco_critico']) && !($medio < $alto && $alto < $critico)) {
        $erros[] = 'As faixas de risco precisam crescer: médio, depois alto, depois crítico.';
    }

    if ($erros) {
        foreach ($erros as $erro) {
            flash('erro', $erro);
        }
        voltarAdmin('regras');
    }

    foreach ($gravar as $chave => $valor) {
        gravarRegra($chave, $valor, $catalogo[$chave]['rotulo']);
    }
    registrarLog('editar_regras', 'Regras', '', count($gravar) . ' regras atualizadas.');
    unset($ator);
    flash('sucesso', 'Regras e faixas salvas.');
    voltarAdmin('regras');
}

function salvarIntegracaoAdmin(array $ator): never
{
    $ativo = ($_POST['webhook_n8n_ativo'] ?? '') === 'sim' ? 'sim' : 'nao';
    $url = trim((string) ($_POST['webhook_n8n_url'] ?? ''));
    $bemEstar = ($_POST['webhook_incluir_bem_estar'] ?? '') === 'sim' ? 'sim' : 'nao';

    if ($url !== '' && (filter_var($url, FILTER_VALIDATE_URL) === false || !in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http'], true))) {
        abortarAdmin('integracoes', 'Informe uma URL http ou https, ou deixe em branco para usar a variável de ambiente.');
    }
    if ($url !== '' && parse_url($url, PHP_URL_SCHEME) === 'http' && APP_ENV !== 'local') {
        abortarAdmin('integracoes', 'Em produção a URL do webhook precisa ser https.');
    }

    gravarRegra('webhook_n8n_ativo', $ativo, 'Liga ou desliga o envio ao n8n.');
    gravarRegra('webhook_n8n_url', $url, 'URL do webhook n8n. Vazia usa a variável de ambiente.');
    gravarRegra('webhook_incluir_bem_estar', $bemEstar, 'Inclui bem-estar individual no payload somente para fluxo restrito ao RH, sem IA e sem e-mail ao gestor.');
    registrarLog('editar_integracao', 'Regras', 'webhook_n8n', "Ativo {$ativo}.");
    unset($ator);
    flash('sucesso', 'Integração atualizada. O segredo do webhook continua só na variável de ambiente.');
    voltarAdmin('integracoes');
}

function testarWebhookAdmin(): never
{
    $resultado = enviarWebhookN8N([
        'evento' => 'teste_conexao',
        'origem' => 'pdi_connect',
        'notificacoes' => [],
    ]);
    flash($resultado['enviado'] ? 'sucesso' : 'erro', $resultado['enviado']
        ? 'Teste enviado ao n8n (HTTP ' . $resultado['status'] . ').'
        : 'O teste não foi enviado: ' . ($resultado['erro'] ?? 'falha desconhecida'));
    voltarAdmin('integracoes');
}

function salvarEmailsAdmin(array $ator): never
{
    $enviados = is_array($_POST['modelo'] ?? null) ? $_POST['modelo'] : [];
    $codigos = array_merge(array_keys(N8NWebhookService::EVENTOS_EMAIL), ['alerta']);
    $salvos = 0;

    foreach ($codigos as $codigo) {
        if (!array_key_exists($codigo, $enviados)) {
            continue;
        }
        $texto = textoLimpo((string) $enviados[$codigo], 1000);
        if ($texto === '') {
            flash('erro', 'O modelo "' . $codigo . '" não pode ficar vazio.');
            continue;
        }
        $rotulo = N8NWebhookService::EVENTOS_EMAIL[$codigo] ?? 'Modelo padrão de e-mail de alerta.';
        gravarRegra(chaveModeloEmail($codigo), $texto, $rotulo);
        $salvos++;
    }

    if ($salvos) {
        registrarLog('editar_modelos_email', 'Regras', '', $salvos . ' modelos atualizados.');
        flash('sucesso', 'Modelos de e-mail salvos. O n8n recebe o texto no campo modelos_email.');
    }
    unset($ator);
    voltarAdmin('emails');
}
