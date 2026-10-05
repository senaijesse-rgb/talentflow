<?php

declare(strict_types=1);

/*
 * Dados fictícios para desenvolvimento local (DATA_SOURCE=mock).
 * Nomes genéricos, domínio reservado ".example" e datas relativas ao dia atual.
 * Para recriar a base, apague storage/mock_db.json.
 */

$data = static fn (int $dias): string => (new DateTimeImmutable('today'))->modify(sprintf('%+d days', $dias))->format('Y-m-d');
$dataHora = static fn (int $dias, string $hora = '10:00'): string => (new DateTimeImmutable("today {$hora}"))->modify(sprintf('%+d days', $dias))->format(DATE_ATOM);
$hash = password_hash('Senha@123', PASSWORD_DEFAULT);

$rh = 'rh@empresa.example';
$gestor = 'gestor@empresa.example';
$gestor2 = 'gestor2@empresa.example';
$c1 = 'colaborador@empresa.example';
$c2 = 'colaborador2@empresa.example';
$c3 = 'colaborador3@empresa.example';
$c4 = 'colaborador4@empresa.example';
$c5 = 'colaborador5@empresa.example';

$usuario = static fn (string $id, string $email, string $nome, string $perfil, string $cargo, string $area, string $gestorEmail, string $ativo = 'sim') => [
    'id_usuario' => $id, 'email' => $email, 'nome_completo' => $nome, 'senha_hash' => $hash, 'perfil' => $perfil,
    'cargo' => $cargo, 'area' => $area, 'gestor_email' => $gestorEmail, 'ativo' => $ativo,
    'data_criacao' => $data(-400), 'ultimo_login' => '',
];

$regra = static fn (string $chave, string $valor, string $descricao) => [
    'chave' => $chave, 'valor' => $valor, 'descricao' => $descricao, 'ativo' => 'sim',
    'atualizado_em' => $dataHora(-30), 'atualizado_por' => $rh,
];

return [
    'Usuarios' => [
        $usuario('U001', $rh, 'Administrador RH Exemplo', 'administrador', 'Analista de Recursos Humanos', 'Recursos Humanos', ''),
        $usuario('U002', $gestor, 'Gestor Exemplo', 'gestor', 'Coordenador de Tecnologia', 'Tecnologia', ''),
        $usuario('U003', $gestor2, 'Gestor Exemplo Dois', 'gestor', 'Coordenador de Produto', 'Produto', ''),
        $usuario('U004', $c1, 'Colaborador Exemplo', 'colaborador', 'Analista de Sistemas Pleno', 'Tecnologia', $gestor),
        $usuario('U005', $c2, 'Colaborador Exemplo Dois', 'colaborador', 'Desenvolvedor Júnior', 'Tecnologia', $gestor),
        $usuario('U006', $c3, 'Colaborador Exemplo Três', 'colaborador', 'Analista de Dados', 'Tecnologia', $gestor),
        $usuario('U007', $c4, 'Colaborador Exemplo Quatro', 'colaborador', 'Analista de Produto', 'Produto', $gestor2),
        $usuario('U008', $c5, 'Colaborador Exemplo Cinco', 'colaborador', 'Designer de Produto', 'Produto', $gestor2, 'nao'),
    ],

    'Projetos' => [
        ['id_projeto' => 'PRJ-001', 'nome_projeto' => 'Portal de Autoatendimento', 'descricao' => 'Portal para solicitações internas.', 'area_responsavel' => 'Tecnologia', 'gestor_email' => $gestor, 'status' => 'ativo', 'data_inicio' => $data(-200), 'data_fim' => ''],
        ['id_projeto' => 'PRJ-002', 'nome_projeto' => 'Migração para Nuvem', 'descricao' => 'Migração da infraestrutura on-premise.', 'area_responsavel' => 'Tecnologia', 'gestor_email' => $gestor, 'status' => 'ativo', 'data_inicio' => $data(-120), 'data_fim' => ''],
        ['id_projeto' => 'PRJ-003', 'nome_projeto' => 'Aplicativo Mobile', 'descricao' => 'Aplicativo para clientes.', 'area_responsavel' => 'Produto', 'gestor_email' => $gestor2, 'status' => 'ativo', 'data_inicio' => $data(-90), 'data_fim' => ''],
        ['id_projeto' => 'PRJ-004', 'nome_projeto' => 'Modernização do ERP', 'descricao' => 'Atualização do sistema de gestão.', 'area_responsavel' => 'Tecnologia', 'gestor_email' => $gestor, 'status' => 'concluido', 'data_inicio' => $data(-500), 'data_fim' => $data(-210)],
    ],

    'Colaborador_Projetos' => [
        ['id_vinculo' => 'VIN-001', 'email_colaborador' => $c1, 'id_projeto' => 'PRJ-001', 'papel_no_projeto' => 'Desenvolvedor', 'data_inicio' => $data(-200), 'data_fim' => '', 'status_participacao' => 'ativo'],
        ['id_vinculo' => 'VIN-002', 'email_colaborador' => $c1, 'id_projeto' => 'PRJ-004', 'papel_no_projeto' => 'Analista', 'data_inicio' => $data(-500), 'data_fim' => $data(-210), 'status_participacao' => 'encerrado'],
        ['id_vinculo' => 'VIN-003', 'email_colaborador' => $c2, 'id_projeto' => 'PRJ-002', 'papel_no_projeto' => 'Desenvolvedor', 'data_inicio' => $data(-120), 'data_fim' => '', 'status_participacao' => 'ativo'],
        ['id_vinculo' => 'VIN-004', 'email_colaborador' => $c3, 'id_projeto' => 'PRJ-001', 'papel_no_projeto' => 'Analista de dados', 'data_inicio' => $data(-150), 'data_fim' => '', 'status_participacao' => 'ativo'],
        ['id_vinculo' => 'VIN-005', 'email_colaborador' => $c3, 'id_projeto' => 'PRJ-002', 'papel_no_projeto' => 'Analista de dados', 'data_inicio' => $data(-100), 'data_fim' => '', 'status_participacao' => 'ativo'],
        ['id_vinculo' => 'VIN-006', 'email_colaborador' => $c4, 'id_projeto' => 'PRJ-003', 'papel_no_projeto' => 'Product Owner', 'data_inicio' => $data(-90), 'data_fim' => '', 'status_participacao' => 'ativo'],
        ['id_vinculo' => 'VIN-007', 'email_colaborador' => $c5, 'id_projeto' => 'PRJ-003', 'papel_no_projeto' => 'Designer', 'data_inicio' => $data(-90), 'data_fim' => $data(-20), 'status_participacao' => 'encerrado'],
    ],

    'PDIs' => [
        ['id_pdi' => 'PDI-001', 'email_colaborador' => $c1, 'competencia' => 'Comunicação', 'meta' => 'Apresentar duas demonstrações do produto para stakeholders', 'percentual_conclusao' => 60, 'status' => 'Em andamento', 'prazo' => $data(25), 'data_inicio' => $data(-60), 'data_ultima_atualizacao' => $dataHora(-5), 'data_conclusao' => '', 'gestor_email' => $gestor, 'ativo' => 'sim'],
        ['id_pdi' => 'PDI-002', 'email_colaborador' => $c1, 'competencia' => 'Arquitetura de Software', 'meta' => 'Concluir curso de arquitetura limpa e aplicar em um módulo', 'percentual_conclusao' => 85, 'status' => 'Em andamento', 'prazo' => $data(6), 'data_inicio' => $data(-90), 'data_ultima_atualizacao' => $dataHora(-3), 'data_conclusao' => '', 'gestor_email' => $gestor, 'ativo' => 'sim'],
        ['id_pdi' => 'PDI-003', 'email_colaborador' => $c1, 'competencia' => 'Liderança', 'meta' => 'Mentorar um colaborador júnior por três meses', 'percentual_conclusao' => 100, 'status' => 'Concluído', 'prazo' => $data(-10), 'data_inicio' => $data(-120), 'data_ultima_atualizacao' => $dataHora(-12), 'data_conclusao' => $data(-12), 'gestor_email' => $gestor, 'ativo' => 'sim'],
        ['id_pdi' => 'PDI-004', 'email_colaborador' => $c2, 'competencia' => 'Testes automatizados', 'meta' => 'Atingir 70% de cobertura de testes nos serviços do projeto', 'percentual_conclusao' => 30, 'status' => 'Em andamento', 'prazo' => $data(-5), 'data_inicio' => $data(-80), 'data_ultima_atualizacao' => $dataHora(-20), 'data_conclusao' => '', 'gestor_email' => $gestor, 'ativo' => 'sim'],
        ['id_pdi' => 'PDI-005', 'email_colaborador' => $c2, 'competencia' => 'Versionamento de código', 'meta' => 'Dominar fluxo de branches e participar de revisões de código', 'percentual_conclusao' => 40, 'status' => 'Atenção', 'prazo' => $data(8), 'data_inicio' => $data(-70), 'data_ultima_atualizacao' => $dataHora(-18), 'data_conclusao' => '', 'gestor_email' => $gestor, 'ativo' => 'sim'],
        ['id_pdi' => 'PDI-006', 'email_colaborador' => $c3, 'competencia' => 'Visualização de dados', 'meta' => 'Publicar três painéis de indicadores para a área', 'percentual_conclusao' => 100, 'status' => 'Aguardando validação', 'prazo' => $data(5), 'data_inicio' => $data(-75), 'data_ultima_atualizacao' => $dataHora(-2), 'data_conclusao' => '', 'gestor_email' => $gestor, 'ativo' => 'sim'],
        ['id_pdi' => 'PDI-007', 'email_colaborador' => $c3, 'competencia' => 'SQL avançado', 'meta' => 'Otimizar cinco consultas críticas do data warehouse', 'percentual_conclusao' => 55, 'status' => 'Em andamento', 'prazo' => $data(40), 'data_inicio' => $data(-45), 'data_ultima_atualizacao' => $dataHora(-9), 'data_conclusao' => '', 'gestor_email' => $gestor, 'ativo' => 'sim'],
        ['id_pdi' => 'PDI-008', 'email_colaborador' => $c4, 'competencia' => 'Discovery de produto', 'meta' => 'Conduzir dez entrevistas com usuários e consolidar aprendizados', 'percentual_conclusao' => 20, 'status' => 'Em andamento', 'prazo' => $data(60), 'data_inicio' => $data(-40), 'data_ultima_atualizacao' => $dataHora(-16), 'data_conclusao' => '', 'gestor_email' => $gestor2, 'ativo' => 'sim'],
        ['id_pdi' => 'PDI-009', 'email_colaborador' => $c4, 'competencia' => 'Métricas de produto', 'meta' => 'Definir e acompanhar métricas de engajamento do aplicativo', 'percentual_conclusao' => 70, 'status' => 'Em andamento', 'prazo' => $data(30), 'data_inicio' => $data(-50), 'data_ultima_atualizacao' => $dataHora(-4), 'data_conclusao' => '', 'gestor_email' => $gestor2, 'ativo' => 'sim'],
        ['id_pdi' => 'PDI-010', 'email_colaborador' => $gestor, 'competencia' => 'Gestão de pessoas', 'meta' => 'Realizar conversas individuais quinzenais com toda a equipe', 'percentual_conclusao' => 65, 'status' => 'Em andamento', 'prazo' => $data(45), 'data_inicio' => $data(-60), 'data_ultima_atualizacao' => $dataHora(-6), 'data_conclusao' => '', 'gestor_email' => $rh, 'ativo' => 'sim'],
    ],

    'Atualizacoes_PDI' => [
        ['id_atualizacao' => 'ATU-001', 'id_pdi' => 'PDI-001', 'email_colaborador' => $c1, 'percentual_anterior' => 40, 'percentual_novo' => 60, 'status_anterior' => 'Em andamento', 'status_novo' => 'Em andamento', 'dificuldade' => '', 'data_atualizacao' => $dataHora(-5), 'origem' => 'formulario_pdi', 'registrado_por' => $c1],
        ['id_atualizacao' => 'ATU-002', 'id_pdi' => 'PDI-002', 'email_colaborador' => $c1, 'percentual_anterior' => 70, 'percentual_novo' => 85, 'status_anterior' => 'Em andamento', 'status_novo' => 'Em andamento', 'dificuldade' => '', 'data_atualizacao' => $dataHora(-3), 'origem' => 'formulario_pdi', 'registrado_por' => $c1],
        ['id_atualizacao' => 'ATU-003', 'id_pdi' => 'PDI-003', 'email_colaborador' => $c1, 'percentual_anterior' => 90, 'percentual_novo' => 100, 'status_anterior' => 'Em andamento', 'status_novo' => 'Aguardando validação', 'dificuldade' => '', 'data_atualizacao' => $dataHora(-14), 'origem' => 'formulario_pdi', 'registrado_por' => $c1],
        ['id_atualizacao' => 'ATU-004', 'id_pdi' => 'PDI-004', 'email_colaborador' => $c2, 'percentual_anterior' => 20, 'percentual_novo' => 30, 'status_anterior' => 'Em andamento', 'status_novo' => 'Em andamento', 'dificuldade' => 'Pouco tempo disponível por demandas do projeto.', 'data_atualizacao' => $dataHora(-20), 'origem' => 'formulario_pdi', 'registrado_por' => $c2],
        ['id_atualizacao' => 'ATU-005', 'id_pdi' => 'PDI-006', 'email_colaborador' => $c3, 'percentual_anterior' => 80, 'percentual_novo' => 100, 'status_anterior' => 'Em andamento', 'status_novo' => 'Aguardando validação', 'dificuldade' => '', 'data_atualizacao' => $dataHora(-2), 'origem' => 'formulario_pdi', 'registrado_por' => $c3],
        ['id_atualizacao' => 'ATU-006', 'id_pdi' => 'PDI-009', 'email_colaborador' => $c4, 'percentual_anterior' => 50, 'percentual_novo' => 70, 'status_anterior' => 'Em andamento', 'status_novo' => 'Em andamento', 'dificuldade' => '', 'data_atualizacao' => $dataHora(-4), 'origem' => 'formulario_pdi', 'registrado_por' => $c4],
    ],

    'Checkins_Clima' => [
        ['id_checkin' => 'CHK-001', 'email_colaborador' => $c1, 'id_projeto' => 'PRJ-001', 'satisfacao_empresa' => 4, 'risco_saida_percebido' => 2, 'bem_estar_trabalho' => 4, 'comentario' => '', 'consentimento_confirmado' => 'sim', 'data_checkin' => $dataHora(-40), 'score_risco' => 0, 'classificacao_risco' => 'BAIXO'],
        ['id_checkin' => 'CHK-002', 'email_colaborador' => $c2, 'id_projeto' => 'PRJ-002', 'satisfacao_empresa' => 3, 'risco_saida_percebido' => 3, 'bem_estar_trabalho' => 3, 'comentario' => '', 'consentimento_confirmado' => 'sim', 'data_checkin' => $dataHora(-35), 'score_risco' => 0, 'classificacao_risco' => 'BAIXO'],
        ['id_checkin' => 'CHK-003', 'email_colaborador' => $c3, 'id_projeto' => 'PRJ-001', 'satisfacao_empresa' => 4, 'risco_saida_percebido' => 2, 'bem_estar_trabalho' => 3, 'comentario' => '', 'consentimento_confirmado' => 'sim', 'data_checkin' => $dataHora(-45), 'score_risco' => 0, 'classificacao_risco' => 'BAIXO'],
        ['id_checkin' => 'CHK-004', 'email_colaborador' => $c1, 'id_projeto' => 'PRJ-001', 'satisfacao_empresa' => 4, 'risco_saida_percebido' => 2, 'bem_estar_trabalho' => 4, 'comentario' => 'Gosto do time e dos desafios atuais.', 'consentimento_confirmado' => 'sim', 'data_checkin' => $dataHora(-10), 'score_risco' => 0, 'classificacao_risco' => 'BAIXO'],
        ['id_checkin' => 'CHK-005', 'email_colaborador' => $c2, 'id_projeto' => 'PRJ-002', 'satisfacao_empresa' => 2, 'risco_saida_percebido' => 4, 'bem_estar_trabalho' => 2, 'comentario' => 'Sinto falta de mais clareza sobre próximos passos de carreira.', 'consentimento_confirmado' => 'sim', 'data_checkin' => $dataHora(-8), 'score_risco' => 100, 'classificacao_risco' => 'CRITICO'],
        ['id_checkin' => 'CHK-006', 'email_colaborador' => $c3, 'id_projeto' => 'PRJ-002', 'satisfacao_empresa' => 5, 'risco_saida_percebido' => 1, 'bem_estar_trabalho' => 5, 'comentario' => '', 'consentimento_confirmado' => 'sim', 'data_checkin' => $dataHora(-12), 'score_risco' => 0, 'classificacao_risco' => 'BAIXO'],
        ['id_checkin' => 'CHK-007', 'email_colaborador' => $c4, 'id_projeto' => 'PRJ-003', 'satisfacao_empresa' => 3, 'risco_saida_percebido' => 3, 'bem_estar_trabalho' => '', 'comentario' => '', 'consentimento_confirmado' => 'sim', 'data_checkin' => $dataHora(-20), 'score_risco' => 15, 'classificacao_risco' => 'BAIXO'],
    ],

    'Regras' => [
        $regra('dias_sem_atualizacao', '14', 'Dias sem atualização de PDI para gerar alerta.'),
        $regra('dias_prazo_proximo', '7', 'Antecedência (dias) para aviso de prazo próximo.'),
        $regra('dias_meta_concluida_recente', '30', 'Janela (dias) para considerar meta concluída recentemente.'),
        $regra('limite_atencao_percentual', '50', 'Percentual abaixo do qual a meta exige atenção.'),
        $regra('limite_risco_medio', '25', 'Score mínimo para Médio risco.'),
        $regra('limite_risco_alto', '50', 'Score mínimo para Alto risco.'),
        $regra('limite_risco_critico', '75', 'Score mínimo para risco Crítico.'),
        $regra('risco_pontos_saida_alta', '35', 'Pontos quando risco de saída percebido é 4 ou 5.'),
        $regra('risco_pontos_satisfacao_baixa', '25', 'Pontos quando satisfação é 1 ou 2.'),
        $regra('risco_pontos_pdi_atrasado', '20', 'Pontos quando há meta de PDI atrasada.'),
        $regra('risco_pontos_sem_atualizacao', '15', 'Pontos quando o PDI está sem atualização além do limite.'),
        $regra('risco_pontos_bem_estar_baixo', '10', 'Pontos quando bem-estar é 1 ou 2 (fator visível apenas ao RH).'),
        $regra('risco_pontos_meta_concluida', '-10', 'Ajuste quando há meta concluída recentemente.'),
        $regra('risco_pontos_satisfacao_alta', '-10', 'Ajuste quando satisfação é 4 ou 5.'),
        $regra('risco_pontos_progresso_alto', '-5', 'Ajuste quando o progresso médio do PDI supera o limite.'),
        $regra('limite_progresso_alto', '80', 'Percentual de progresso considerado alto.'),
        $regra('minimo_respostas_agregado', '3', 'Mínimo de respondentes para exibir médias agregadas ao gestor.'),
        $regra('politica_exibir_satisfacao_gestor', 'sim', 'Permite que o gestor veja a satisfação individual da equipe (sim/nao).'),
        $regra('webhook_n8n_ativo', 'sim', 'Habilita o envio de eventos ao n8n (sim/nao).'),
        $regra('webhook_n8n_url', '', 'URL do webhook n8n (sobrescreve N8N_WEBHOOK_URL quando preenchida).'),
        $regra('webhook_incluir_bem_estar', 'nao', 'Inclui o bem-estar individual no payload do n8n (somente fluxos restritos ao RH, sem IA).'),
        $regra('modelo_email_alerta', 'Olá, {{nome_gestor}}. O PDI de {{nome_colaborador}} requer atenção: {{meta}} ({{status}}).', 'Modelo padrão de e-mail de alerta.'),
    ],

    'Logs_Auditoria' => [],

    'Comentarios_Gestor' => [
        ['id_comentario' => 'COM-001', 'email_colaborador' => $c1, 'gestor_email' => $gestor, 'comentario' => 'Ótima evolução nas apresentações. Vamos planejar a próxima demo para a diretoria.', 'tipo_comentario' => 'Feedback', 'data_comentario' => $dataHora(-4), 'visivel_colaborador' => 'sim'],
        ['id_comentario' => 'COM-002', 'email_colaborador' => $c2, 'gestor_email' => $gestor, 'comentario' => 'Vamos revisar juntos o plano de testes e redefinir o prazo da meta.', 'tipo_comentario' => 'Acompanhamento', 'data_comentario' => $dataHora(-6), 'visivel_colaborador' => 'sim'],
    ],
];
