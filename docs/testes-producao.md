# Testes em produção

Registro do teste manual feito em **5 de outubro de 2026** no site publicado.

| Item | Valor |
|---|---|
| Endereço | https://talentflow-weld.vercel.app |
| PDI Connect | `/login.php` |
| TalentFlow PDI | `/talentflow/` |
| Ambiente | `APP_ENV=production`, `DATA_SOURCE=sheets` |
| Resultado | Os fluxos abaixo passaram |

A tela de login do PDI Connect, em produção, não mostra a dica de usuário e senha. As contas usadas são as de demonstração já descritas neste repositório. Nenhuma conta nova foi criada. Nenhuma meta, indicação ou premiação foi gravada durante o teste. Os acessos ficaram na auditoria.

O TalentFlow fala com o n8n. O PDI Connect lê a planilha pela conta de serviço configurada na Vercel. Os dois não compartilham sessão.

---

## PDI Connect

Senha das contas de demonstração: `Senha@123`.

### Administrador RH

Conta: `rh@empresa.example`.

O login abriu **Dashboard do RH**. A visão geral, sem filtro de área, mostrou:

| Indicador | Valor observado |
|---|---|
| Colaboradores ativos | 6 |
| Gestores | 2 |
| Projetos ativos | 3 (4 cadastrados) |
| PDIs ativos | 10 (9 metas em aberto) |
| Taxa de conclusão | 10% |
| Satisfação geral | 3,5/5 (4 respondentes) |
| Bem-estar geral | 3,7/5 (3 respostas, 1 alerta) |
| Risco alto ou crítico | 1 colaborador |

O filtro **Área = Tecnologia** reduziu a visão para 4 colaboradores ativos e 8 PDIs, com taxa de conclusão de 13%.

Estas páginas abriram com dados da planilha, sem a mensagem “em breve” e sem erro de planilha:

| Página | Resultado |
|---|---|
| Gestão de usuários | 8 pessoas listadas: 7 ativas e 1 inativa (`colaborador5@empresa.example`) |
| Gestores e equipes | Abriu |
| Projetos | Abriu |
| PDIs | Abriu |
| Regras e faixas | Abriu, com as faixas de risco |
| Logs de auditoria | Abriu, com registros de login |
| Integrações n8n | Abriu. O segredo do webhook não é exibido |
| Modelos de e-mail | Abriu |
| Bem-estar individual | Nota e data. O aviso informa que o comentário livre não aparece |
| Relatórios CSV | Abriu |
| Check-in, perfil e atualizar PDI | Abriram para o próprio RH |
| Minha equipe (`equipe.php`) | Acesso negado (403). A tela é exclusiva do gestor |

Os quatro CSVs foram gerados (`text/csv`):

| Relatório | Linhas, com o cabeçalho | Cabeçalho |
|---|---|---|
| PDIs | 11 | `id;email;nome;competencia;meta;percentual;status;status_efetivo;prazo;gestor;ativo` |
| Indicadores | 7 | `email;nome;area;cargo;gestor;pdis;progresso_medio;status_geral;classificacao_risco;score_risco;ultima_atualizacao` |
| Usuários | 9 | `email;nome;perfil;cargo;area;gestor;ativo` |
| Projetos | 5 | `id;nome;area;gestor;status;inicio;fim;participacoes_ativas` |

Nenhum arquivo trouxe senha, comentário ou nota de bem-estar. A saída da sessão voltou para a tela de login.

### Gestor

Conta: `gestor@empresa.example`.

O login abriu **Dashboard da equipe**, com 3 pessoas vinculadas: Colaborador Exemplo, Colaborador Exemplo Dois e Colaborador Exemplo Três. O bem-estar apareceu só agregado (3,7/5, 3 respostas). A tela avisa que a resposta individual fica restrita ao RH.

Em **Minha equipe** havia 3 pessoas, 6 metas em aberto e 1 pessoa em risco alto ou crítico, sem nota de bem-estar. A busca por “Dois” deixou só Colaborador Exemplo Dois na lista de pessoas. A lista de metas em aberto continuou completa, porque é uma seção separada.

| Página | Resultado |
|---|---|
| Atualizar meu PDI | Abriu |
| Administração | Acesso negado (403) |
| Bem-estar individual | Acesso negado (403) |
| Relatórios CSV | Acesso negado (403) |

### Colaborador

Conta: `colaborador@empresa.example`.

O login abriu **Meu dashboard**, de Colaborador Exemplo, Analista de Sistemas Pleno, com gestor Gestor Exemplo. O progresso médio das metas ativas estava em 82%. A página não listou outras pessoas da equipe. O aviso de confidencialidade do bem-estar estava visível.

| Página | Resultado |
|---|---|
| Atualizar meu PDI | Abriu |
| Check-in de experiência | Abriu |
| Minha equipe | Acesso negado (403) |
| Administração | Acesso negado (403) |

### Conta inativa

Conta: `colaborador5@empresa.example`, com a mesma senha de demonstração.

O login permaneceu na tela de entrada e mostrou: “Seu acesso está inativo. Procure o Administrador RH.”

---

## TalentFlow PDI

O login é só pelo e-mail. Os atalhos da tela chamam o webhook do n8n e carregam o perfil da planilha.

### Colaborador — Ana Clara Souza

Atalho “Entrar como Colaborador”. O dashboard mostrou:

- Cargo: Desenvolvedora Front-end Pleno
- PDI: Especialização em front-end acessível, 2026/2027, ativo
- Metas: 1 ativa de 2 cadastradas, progresso médio 60%, próximo prazo 30/10/2026
- Skills: 3 validadas e 2 pendentes ou em validação
- Projeto atual: Projeto Beta — Portal do Cliente
- Bem-estar voluntário marcado como visível somente para a própria pessoa

Menu visto: Dashboard, Minhas metas, Reconhecimento, MentorIA, Bem-estar e clima, Minhas skills, Meus projetos.

Em **Reconhecimento** havia 2 indicações feitas por Ana (Camila Torres Ribeiro e Henrique Dias Fonseca) e o botão **Indicar um colega**. A busca por “Henrique” passou a lista para 1 indicação e escondeu a de Camila.

### Gestor — Marcos Vieira Andrade

Atalho “Entrar como Gestor”. O título da visão foi “Equipe de Marcos”.

Menu visto: Dashboard, Avaliar metas, Minha equipe, Reconhecimento, Skills da equipe, Projetos.

**Minha equipe** listou Ana Clara Souza, Bruno Henrique Lima, Camila Torres Ribeiro e Diego Alves, pelos e-mails `@empresa.com`. A página não mostrou comentário nem nota individual de bem-estar.

### Administrador RH — Helena Rocha Prado

Atalho “Entrar como RH”. O título da visão foi “Visão global de RH”.

Menu visto: Dashboard, Colaboradores, Reconhecimento, Gestão de skills, Projetos, Matriz skills × projetos, Relatórios, Logs e auditoria, Configurações.

**Colaboradores** listou 12 pessoas, com cargo, área e gestor, sem comentário de bem-estar.

**Reconhecimento** mostrou 3 indicações, o catálogo de premiações e o botão **Nova premiação**. O botão de indicar um colega não apareceu para o RH.

### E-mail inexistente

O e-mail `naoexiste@empresa.com` permaneceu na tela de acesso e mostrou: “E-mail não encontrado na base de colaboradores.”

---

## O que este teste não cobriu

- Criar, editar ou inativar usuários, projetos, metas, regras e modelos de e-mail
- Enviar o webhook de teste do n8n e conferir a caixa do Gmail
- Gerar um plano da MentorIA, criar uma indicação ou autorizar uma premiação
- O segundo gestor (`gestor2@empresa.example`)
- Várias instâncias da Vercel ao mesmo tempo. A sessão em arquivo segurou os fluxos deste teste; o README ainda recomenda um armazenamento de sessão externo antes de um uso com muitos acessos simultâneos
