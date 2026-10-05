# PDI Connect

Aplicação web responsiva para **gestão de pessoas, projetos e desenvolvimento profissional**: PDIs (Planos de Desenvolvimento Individual), projetos, check-ins de experiência, indicadores de clima e risco de perda de colaboradores, com controle de acesso por perfil e privacidade por padrão.

- **Front-end:** HTML5, Tailwind CSS (CDN) e JavaScript puro
- **Back-end:** PHP 8.2+ sem framework
- **Banco de dados:** Google Sheets (Google Sheets API) — com modo *mock* local para desenvolvimento
- **Automação:** Webhooks para n8n (e-mails via Gmail são enviados pelos fluxos do n8n)

O repositório tem duas aplicações na mesma planilha. Elas não leem os dados uma da outra. O PHP ignora abas fora do esquema das nove abas abaixo.

| Aplicação | Endereço | Quem grava |
|---|---|---|
| **PDI Connect** | `/` | PHP, nas nove abas deste documento |
| **TalentFlow PDI** | `/talentflow/` | n8n, nas abas `TalentFlow_*` e `TF_*` |

---

## Sumário

1. [Perfis e regras de privacidade](#perfis-e-regras-de-privacidade)
2. [Estrutura de pastas](#estrutura-de-pastas)
3. [Instalação local](#instalação-local)
4. [Configuração do Google Sheets](#configuração-do-google-sheets)
5. [Integração com n8n e Gmail](#integração-com-n8n-e-gmail)
6. [Lógica de risco](#lógica-de-risco)
7. [Segurança](#segurança)
8. [Deploy](#deploy)
9. [Status da implementação e próximos passos](#status-da-implementação-e-próximos-passos)
10. [TalentFlow PDI](#talentflow-pdi)
11. [Testes em produção](docs/testes-producao.md)

---

## Perfis e regras de privacidade

| Perfil | Pode ver | Não pode |
|---|---|---|
| **Colaborador** (`colaborador`) | Apenas os próprios dados, PDIs, projetos, histórico e lembretes | Ver outros colaboradores; alterar cargo, gestor ou regras; concluir meta sem validação |
| **Gestor** (`gestor`) | Somente colaboradores vinculados a ele (`gestor_email`); classificação de risco e recomendações; satisfação individual **se** `politica_exibir_satisfacao_gestor = sim` | Ver bem-estar individual (somente agregado, com mínimo de respostas); ver score numérico de risco; acessar outras equipes |
| **Administrador RH** (`administrador`) | Tudo, inclusive bem-estar individual (área restrita) e logs | — |

Regras aplicadas no código:

- O **e-mail corporativo** é a chave de busca e vínculo entre abas (sempre normalizado em minúsculas).
- O campo **“Indicador de bem-estar no trabalho”** (1 a 5) é voluntário (“Prefiro não responder”) e exibe o aviso de privacidade obrigatório.
- Médias agregadas para o gestor só aparecem com pelo menos `minimo_respostas_agregado` respondentes (padrão: 3), evitando identificação indireta.
- O bem-estar individual **não é enviado ao n8n** por padrão (`bem_estar_trabalho: null`). Só é incluído se a regra `webhook_incluir_bem_estar = sim` — use apenas em fluxos restritos ao RH e **nunca** com nós de IA.
- Logs de auditoria nunca registram valores de bem-estar, senhas ou tokens.
- Nenhum campo solicita diagnósticos, laudos, medicamentos ou informações clínicas; o check-in exige consentimento explícito.

---

## Estrutura de pastas

```
/
├── index.php                 Página inicial (credenciais de teste só com APP_ENV=local + mock)
├── login.php / logout.php    Autenticação (logout via POST + CSRF)
├── dashboard.php             Dashboard por perfil (colaborador, gestor, RH)
├── pdi.php                   Formulário de atualização de PDI
├── checkin.php               Check-in de Experiência do Colaborador
├── perfil.php                Perfil, linha do tempo e bem-estar individual (somente RH)
├── equipe.php, admin.php, relatorios.php   (ainda não criados; o menu mostra “em breve”)
├── router.php                Roteador para `php -S` (bloqueia pastas internas)
├── talentflow/index.html     TalentFlow PDI (página estática; dados via n8n)
├── .env.example  .gitignore  .htaccess  composer.json  vercel.json
├── assets/{css,js,img}
├── includes/
│   ├── config.php            Bootstrap: .env, sessão segura, headers, autoload
│   ├── auth.php              Login, sessão, expiração, limite de tentativas
│   ├── permissions.php       Perfis, regras de acesso, negação com log
│   ├── csrf.php              Tokens CSRF
│   ├── functions.php         Utilidades, consultas de domínio e componentes visuais
│   ├── header.php / footer.php
│   └── views/                Dashboards de cada perfil
├── services/
│   ├── GoogleSheetsService.php   Acesso às abas (modo sheets ou mock)
│   ├── N8NWebhookService.php     Envio de eventos (cURL) + enviarWebhookN8N()
│   ├── RiskService.php           calcularScoreRisco()
│   └── AuditService.php          Logs_Auditoria
├── actions/                  login_action, salvar_pdi, salvar_checkin (+ próximos)
├── api/                      Endpoints JSON (próxima etapa)
├── data/mock_seed.php        Dados fictícios (nomes genéricos, domínio .example)
└── storage/                  Base mock e controle de tentativas de login (não versionado)
```

---

## Instalação local

Pré-requisitos: **PHP 8.2+** com extensões `curl`, `json`, `mbstring`, `openssl` e `session`. Composer é necessário apenas para o modo Google Sheets.

```bash
# 1. Variáveis de ambiente
cp .env.example .env          # Windows: copy .env.example .env

# 2. Dependências (necessário para DATA_SOURCE=sheets)
composer install

# 3. Servidor de desenvolvimento
php -S localhost:8000 router.php
```

Acesse `http://localhost:8000`. Com `APP_ENV=local` e `DATA_SOURCE=mock`, a página inicial lista as credenciais de teste:

| Perfil | E-mail | Senha |
|---|---|---|
| Administrador RH | `rh@empresa.example` | `Senha@123` |
| Gestor | `gestor@empresa.example` | `Senha@123` |
| Gestor | `gestor2@empresa.example` | `Senha@123` |
| Colaborador | `colaborador@empresa.example` | `Senha@123` |
| Colaborador (inativo) | `colaborador5@empresa.example` | `Senha@123` |

> ⚠️ Essas contas de demonstração também estão na planilha usada pelo site publicado. Em produção a tela de login não mostra essa dica. Nunca use `APP_ENV=local` em produção.

A base mock é criada em `storage/mock_db.json` no primeiro acesso, com datas relativas ao dia atual. Para restaurá-la, apague esse arquivo.

---

## Configuração do Google Sheets

### 1. Conta de serviço

1. No [Google Cloud Console](https://console.cloud.google.com/), crie (ou selecione) um projeto.
2. Ative a **Google Sheets API** em *APIs e serviços → Biblioteca*.
3. Em *IAM e administrador → Contas de serviço*, crie uma conta de serviço e gere uma **chave JSON**.
4. Salve o JSON **fora da pasta pública** (ex.: `/etc/pdi-connect/service-account.json`) ou em `credentials/` (ignorada pelo Git e bloqueada no servidor web).

### 2. Planilha

1. Crie uma planilha no Google Sheets e copie o ID da URL: `https://docs.google.com/spreadsheets/d/<ID>/edit`.
2. **Compartilhe** a planilha com o e-mail da conta de serviço (`...@...iam.gserviceaccount.com`) como **Editor**.
3. Crie as abas abaixo com **exatamente** estes nomes e, na **linha 1**, os cabeçalhos na ordem indicada:

| Aba | Cabeçalhos (linha 1) |
|---|---|
| `Usuarios` | id_usuario, email, nome_completo, senha_hash, perfil, cargo, area, gestor_email, ativo, data_criacao, ultimo_login |
| `Projetos` | id_projeto, nome_projeto, descricao, area_responsavel, gestor_email, status, data_inicio, data_fim |
| `Colaborador_Projetos` | id_vinculo, email_colaborador, id_projeto, papel_no_projeto, data_inicio, data_fim, status_participacao |
| `PDIs` | id_pdi, email_colaborador, competencia, meta, percentual_conclusao, status, prazo, data_inicio, data_ultima_atualizacao, data_conclusao, gestor_email, ativo |
| `Atualizacoes_PDI` | id_atualizacao, id_pdi, email_colaborador, percentual_anterior, percentual_novo, status_anterior, status_novo, dificuldade, data_atualizacao, origem, registrado_por |
| `Checkins_Clima` | id_checkin, email_colaborador, id_projeto, satisfacao_empresa, risco_saida_percebido, bem_estar_trabalho, comentario, consentimento_confirmado, data_checkin, score_risco, classificacao_risco |
| `Regras` | chave, valor, descricao, ativo, atualizado_em, atualizado_por |
| `Logs_Auditoria` | id_log, data_hora, email_usuario, perfil_usuario, acao, entidade, id_entidade, descricao, ip, resultado |
| `Comentarios_Gestor` | id_comentario, email_colaborador, gestor_email, comentario, tipo_comentario, data_comentario, visivel_colaborador |

Convenções de valores:

- `perfil`: `colaborador`, `gestor` ou `administrador`
- `ativo`, `visivel_colaborador`, `consentimento_confirmado`: `sim` / `nao`
- `status` (PDIs): `Em andamento`, `Atenção`, `Atrasado`, `Aguardando validação`, `Concluído`
- `status` (Projetos) e `status_participacao`: `ativo` / `concluido` / `encerrado`
- Datas: `AAAA-MM-DD`; data/hora: ISO 8601 (gravadas automaticamente pelo sistema)
- Recomenda-se formatar as colunas de data e percentual como **Texto simples** para evitar conversões automáticas.

### 3. Primeiro administrador

Gere o hash da senha localmente (nunca coloque a senha em texto puro na planilha):

```bash
php -r "echo password_hash('SuaSenhaForte!', PASSWORD_DEFAULT), PHP_EOL;"
```

Adicione uma linha na aba `Usuarios` com `perfil = administrador`, `ativo = sim` e o hash na coluna `senha_hash`.

### 4. Regras iniciais (aba `Regras`)

| chave | valor sugerido | descrição |
|---|---|---|
| dias_sem_atualizacao | 14 | Dias sem atualização de PDI para gerar alerta |
| dias_prazo_proximo | 7 | Antecedência do aviso de prazo |
| dias_meta_concluida_recente | 30 | Janela de “meta concluída recentemente” |
| limite_atencao_percentual | 50 | Percentual abaixo do qual a meta exige atenção |
| limite_risco_medio / limite_risco_alto / limite_risco_critico | 25 / 50 / 75 | Faixas de classificação do score |
| risco_pontos_saida_alta | 35 | Pesos do score (ver [Lógica de risco](#lógica-de-risco)) |
| risco_pontos_satisfacao_baixa | 25 | |
| risco_pontos_pdi_atrasado | 20 | |
| risco_pontos_sem_atualizacao | 15 | |
| risco_pontos_bem_estar_baixo | 10 | |
| risco_pontos_meta_concluida | -10 | |
| risco_pontos_satisfacao_alta | -10 | |
| risco_pontos_progresso_alto | -5 | |
| limite_progresso_alto | 80 | |
| minimo_respostas_agregado | 3 | Mínimo de respostas para exibir médias ao gestor |
| politica_exibir_satisfacao_gestor | nao | `sim` permite ao gestor ver satisfação individual |
| webhook_n8n_ativo | sim | Liga/desliga o envio ao n8n |
| webhook_n8n_url | *(vazio)* | Sobrescreve `N8N_WEBHOOK_URL` |
| webhook_incluir_bem_estar | nao | Inclui bem-estar individual no payload (somente RH, sem IA) |
| modelo_email_alerta | Olá, {{nome_gestor}}... | Modelo padrão de alerta |

Todas as linhas com `ativo = sim`. Regras ausentes usam os valores padrão do código.

### 5. Ativar o modo Sheets

```dotenv
DATA_SOURCE=sheets
GOOGLE_SHEETS_ID=<id-da-planilha>
GOOGLE_SERVICE_ACCOUNT_JSON=/caminho/seguro/service-account.json
# ou o conteúdo JSON completo em uma linha (útil em plataformas serverless)
```

Todas as escritas usam `valueInputOption=RAW`: textos iniciados por `=`, `+`, `-` ou `@` são gravados literalmente, sem serem interpretados como fórmulas.

> Limites: a Sheets API tem cota de requisições por minuto. O serviço faz cache por requisição (uma leitura por aba). Para volumes maiores, a evolução natural é migrar para um banco relacional mantendo a interface do `GoogleSheetsService`.

---

## Integração com n8n e Gmail

### Configuração

```dotenv
N8N_WEBHOOK_URL=https://seu-n8n.exemplo/webhook/pdi-connect
N8N_WEBHOOK_SECRET=<segredo-longo-e-aleatorio>
GMAIL_FROM_NAME=PDI Connect
GMAIL_FROM_EMAIL=nao-responda@suaempresa.com.br
```

Cada envio é um `POST` JSON com os headers:

- `X-App-Secret`: segredo compartilhado (valide no primeiro nó do fluxo)
- `X-App-Signature`: `sha256=<HMAC-SHA256 do corpo com o segredo>` (validação opcional, mais robusta)
- `X-PDI-Event`: nome do evento

Em produção, só URLs `https://` são aceitas. Falhas de envio **não** bloqueiam o usuário (o dado já está salvo) e ficam registradas em `Logs_Auditoria` (`acao = webhook_n8n`).

### Payload — `atualizacao_pdi`

```json
{
  "evento": "atualizacao_pdi",
  "origem": "pdi_connect",
  "id_pdi": "PDI-001",
  "email_colaborador": "colaborador@empresa.example",
  "nome_colaborador": "Colaborador Exemplo",
  "gestor_email": "gestor@empresa.example",
  "competencia": "Comunicação",
  "meta": "Apresentar duas demonstrações do produto",
  "percentual_anterior": 40,
  "percentual_novo": 60,
  "status": "Em andamento",
  "prazo": "2026-12-31",
  "dificuldade": "Texto opcional",
  "data_atualizacao": "2026-10-04T10:00:00-03:00",
  "id_atualizacao": "ATU-261004-A1B2C3",
  "id_projeto": "PRJ-001",
  "registrado_por": "colaborador@empresa.example",
  "notificacoes": ["confirmacao_atualizacao_pdi", "aviso_prazo_proximo"]
}
```

### Payload — `checkin_clima`

```json
{
  "evento": "checkin_clima",
  "origem": "pdi_connect",
  "id_checkin": "CHK-261004-D4E5F6",
  "email_colaborador": "colaborador@empresa.example",
  "nome_colaborador": "Colaborador Exemplo",
  "id_projeto": "PRJ-001",
  "satisfacao_empresa": 4,
  "risco_saida_percebido": 2,
  "bem_estar_trabalho": null,
  "score_risco": 0,
  "classificacao_risco": "BAIXO",
  "data_checkin": "2026-10-04T10:00:00-03:00",
  "notificacoes": [],
  "privacidade": { "bem_estar_omitido": true, "proibido_uso_em_ia": true, "destinatario_bem_estar": "somente_rh" }
}
```

### Eventos de e-mail (campo `notificacoes`)

O PHP indica **quais** e-mails o fluxo deve disparar; o n8n monta e envia pelo nó Gmail.

| Código | Quando é sinalizado | Destinatário sugerido |
|---|---|---|
| `confirmacao_atualizacao_pdi` | Toda atualização de PDI | Colaborador |
| `lembrete_pdi_abaixo_50` | Percentual abaixo de `limite_atencao_percentual` | Colaborador |
| `aviso_prazo_proximo` | Prazo em até `dias_prazo_proximo` dias | Colaborador |
| `alerta_pdi_atrasado` | Status Atrasado ou prazo vencido | Colaborador e gestor |
| `aviso_meta_concluida` | Meta validada como Concluída | Colaborador e gestor |
| `solicitacao_validacao_gestor` | Meta em Aguardando validação | Gestor |
| `alerta_risco_alto_rh` | Check-in com risco ALTO ou CRITICO | RH |
| `resumo_semanal_gestor` | Agendado no n8n (Cron) | Gestor |
| `resumo_mensal_rh` | Agendado no n8n (Cron) | RH |

Fluxo sugerido no n8n: **Webhook → IF (valida `X-App-Secret`) → Switch (`evento`) → Split (`notificacoes`) → Gmail**. Os resumos semanal/mensal serão alimentados pelos endpoints de `api/` (próxima etapa).

> 🔒 **Nunca** envie a nota individual de bem-estar por e-mail ao gestor, e não conecte dados de bem-estar a nós de IA.

---

## Lógica de risco

`calcularScoreRisco()` (em `services/RiskService.php`) gera um score de 0 a 100 com pesos configuráveis na aba `Regras`:

| Condição | Pontos |
|---|---|
| Risco de saída percebido 4 ou 5 | +35 |
| Satisfação 1 ou 2 | +25 |
| PDI com meta atrasada | +20 |
| Sem atualização de PDI há mais de 14 dias | +15 |
| Bem-estar 1 ou 2 *(fator visível somente ao RH)* | +10 |
| Meta concluída recentemente | −10 |
| Satisfação 4 ou 5 | −10 |
| Progresso médio do PDI acima de 80% | −5 |

Classificação: **0–24 Baixo · 25–49 Médio · 50–74 Alto · 75–100 Crítico**.

O gestor recebe apenas `RiskService::visaoGestor()`: classificação e recomendações operacionais, sem o score numérico nem fatores sensíveis.

---

## Segurança

- Senhas com `password_hash` / `password_verify` (rehash automático quando o algoritmo evolui).
- Sessão com `use_strict_mode`, cookies `HttpOnly` + `SameSite=Lax` (+ `Secure` em HTTPS), regeneração de ID no login, expiração por inatividade e revalidação periódica do usuário (inativação ou troca de perfil surtem efeito em até 5 minutos).
- “Lembrar acesso” estende a sessão por `SESSION_REMEMBER_DAYS` dias.
- Limite de 5 tentativas de login por IP + e-mail a cada 15 minutos.
- Token CSRF em todos os formulários POST (incluindo logout).
- Validação server-side de todos os campos; saída sempre escapada com `htmlspecialchars`.
- Verificação de permissão em cada página/ação; tentativas negadas são registradas em `Logs_Auditoria`.
- Headers `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` e `Permissions-Policy`.
- Pastas internas (`includes`, `services`, `data`, `storage`, `vendor`, `credentials`) e arquivos `.env`/`.json`/`.md` bloqueados via `.htaccess`, `router.php` e `vercel.json`.
- Nenhuma chave, ID de planilha ou token é exposto ao front-end.

**Login futuro via Google Workspace:** implemente o fluxo OAuth/OpenID (ex.: com `google/apiclient`), valide o domínio corporativo, busque o usuário com `buscarUsuario($email)` e chame `iniciarSessaoUsuario($registro, false, 'google')`. O restante do sistema (permissões, auditoria) permanece igual.

---

## Deploy

### Servidor PHP tradicional (recomendado)

Apache (com `mod_rewrite`/`AllowOverride All`) ou Nginx + PHP-FPM 8.2+:

1. Envie os arquivos (sem `.env` local, `storage/*.json` e `credentials/`).
2. Execute `composer install --no-dev --optimize-autoloader`.
3. Crie o `.env` de produção com `APP_ENV=production`, `DATA_SOURCE=sheets` e HTTPS habilitado.
4. Garanta que `storage/` seja gravável pelo usuário do PHP.
5. No Nginx, replique os bloqueios do `.htaccess`:

```nginx
location ~ /\.(?!well-known) { deny all; }
location ~ ^/(includes|services|storage|data|vendor|credentials)/ { deny all; }
location ~ \.(json|lock|md|example|log)$ { deny all; }
```

### Vercel (referência)

O `vercel.json` incluído usa o runtime comunitário [`vercel-php`](https://github.com/vercel-community/php), pois a Vercel **não executa PHP nativamente**. Pontos de atenção:

- A versão do runtime define a versão do PHP; confira a compatibilidade com 8.2+.
- O sistema de arquivos é somente leitura (exceto `/tmp`), e cada função é isolada: **sessões em arquivo não são compartilhadas entre instâncias** e a base mock é volátil. Em produção na Vercel, use `DATA_SOURCE=sheets` e configure um *session handler* externo (ex.: Redis) antes de liberar o acesso.
- Defina as variáveis de ambiente no painel da Vercel; `GOOGLE_SERVICE_ACCOUNT_JSON` pode receber o conteúdo JSON completo.

Para esta primeira versão, uma hospedagem PHP tradicional (VPS, hospedagem compartilhada com PHP 8.2 ou Cloud Run com imagem PHP-Apache) é a opção mais simples e estável.

O `vercel.json` também publica o TalentFlow: `/talentflow` e `/talentflow/` servem `talentflow/index.html`. O restante das rotas continua no PHP.

Na Vercel, defina estas variáveis antes de usar a planilha. Sem elas o PHP não conecta no Google Sheets:

| Variável | Valor em produção |
|---|---|
| `APP_ENV` | `production` |
| `APP_URL` | URL pública do projeto |
| `DATA_SOURCE` | `sheets` |
| `GOOGLE_SHEETS_ID` | ID da planilha |
| `GOOGLE_SERVICE_ACCOUNT_JSON` | JSON da conta de serviço em uma linha |
| `N8N_WEBHOOK_URL` | URL `https://` do webhook do PDI Connect |
| `N8N_WEBHOOK_SECRET` | Segredo do header `X-App-Secret` |
| `GMAIL_FROM_NAME` / `GMAIL_FROM_EMAIL` | Remetente usado pelos fluxos do n8n |

O zip de publicação não inclui `.env` nem `credentials/service-account.json`. Essas credenciais ficam só nas variáveis da Vercel.

O teste manual do site publicado está em [docs/testes-producao.md](docs/testes-producao.md).

---

## Status da implementação e próximos passos

**Implementado**

- Estrutura de pastas, `composer.json`, `.env.example`, `.gitignore`, `.htaccess`, `router.php`, `vercel.json`
- `includes/` completo (config, auth, permissions, csrf, functions, header, footer)
- Serviços: `GoogleSheetsService` (Sheets + mock), `N8NWebhookService`, `RiskService`, `AuditService`
- Telas: página inicial, login, dashboards de Colaborador, Gestor e Administrador RH (com filtros), Minhas metas, Reconhecimento, MentorIA, Bem-estar e clima, Minhas skills, Meus projetos, perfil, `equipe.php`, `admin.php` e `relatorios.php`
- Administração do RH: usuários, vínculo de gestores, projetos e participações, PDIs, regras de risco, logs, webhook n8n, modelos de e-mail e bem-estar individual com registro de acesso
- Ações: login, salvar PDI, salvar check-in, validar meta, comentário do gestor, administração e exportação CSV (com validação, auditoria e webhook)
- Fluxo n8n **PDI Connect — avisos** em `/webhook/pdi-connect`, com validação de `X-App-Secret` e envio pelo Gmail
- Planilha com as nove abas deste documento, conta de serviço como editor e `DATA_SOURCE=sheets` no ambiente local

**Ainda não feito**

- `api/colaborador.php`, `api/projetos.php`, `api/pdis.php`, `api/indicadores.php`
- Crons no n8n para `resumo_semanal_gestor` e `resumo_mensal_rh`

Os itens de menu ainda sem arquivo aparecem como “em breve” e são habilitados automaticamente quando os arquivos forem criados.

---

## TalentFlow PDI

Página estática em `talentflow/index.html`. O navegador não chama a API do Google. Toda leitura e gravação passa pelo webhook do n8n, que lê e grava a planilha e envia os e-mails pelo Gmail.

Local: `http://localhost:8000/talentflow/`. Na Vercel: `/talentflow/`.

`APP_CONFIG.mode` está em `production`. A base é `https://senaipdi.app.n8n.cloud` e o único endereço usado é `POST /webhook/talentflow`. O modo `demo` continua no arquivo e responde no navegador, sem planilha.

### Perfis e telas

O login é pelo e-mail corporativo, sem senha. O n8n devolve um token de sessão (validade de 12 horas), guardado na planilha. As chamadas seguintes enviam esse token no corpo e no header `Authorization`. Sem token válido a resposta é 401. O e-mail sozinho não autoriza a operação.

Os usuários de demonstração são fictícios, no domínio `@empresa.com`. Os atalhos da tela de login são Ana Souza (colaborador), Marcos Vieira (gestor) e Helena Rocha (RH).

| Perfil | Telas |
|---|---|
| Colaborador | Dashboard, Minhas metas, MentorIA, Bem-estar e clima, Minhas skills, Meus projetos |
| Gestor | Dashboard, Avaliar metas, Minha equipe, Skills da equipe, Projetos |
| Administrador RH | Dashboard, Colaboradores, Gestão de skills, Projetos, Matriz skills × projetos, Relatórios, Logs e auditoria, Configurações |

Regras aplicadas no motor (`talentflow/motor.js`, executado pelo n8n):

- O colaborador vê e altera só os próprios dados. Só ele cria e atualiza as próprias metas.
- O gestor vê a equipe vinculada pelo e-mail do gestor. Não vê satisfação nem bem-estar individual. O clima da equipe aparece agregado.
- O RH vê o quadro geral. A projeção legível da planilha não inclui comentário nem sentimento de bem-estar.
- O pedido de apoio ao RH dispara o modelo `apoio_rh` sem conteúdo individual.
- A MentorIA orienta metas de desenvolvimento. Ela não faz diagnóstico clínico nem psicológico.
- Logs não guardam o HTML do e-mail nem o token da sessão.

### Contrato do webhook

`POST https://senaipdi.app.n8n.cloud/webhook/talentflow`

```json
{
  "recurso": "metas",
  "metodo": "POST",
  "token": "<token emitido no login>",
  "solicitante": "ana.souza@empresa.com"
}
```

`recurso` é o nome da operação: `login`, `dashboard`, `colaboradores`, `pdi`, `metas`, `skills`, `projetos`, `aprovarMeta`, `avaliarColaborador`, `validarSkill`, `mentorIA`, `relatorios`, `configuracoes`. O corpo inclui os campos do formulário daquela tela.

A resposta de sucesso traz os dados em `data` (ou no próprio JSON). `success: false` é erro de regra de negócio. Falha de leitura ou gravação da planilha responde 502 e não confirma a operação.

O preflight `OPTIONS` responde 204 e libera `Content-Type` e `Authorization`.

### Abas da planilha

Cada pessoa, meta, skill, projeto, premiação e reconhecimento ocupa **uma linha** na aba `TF_Registros` (colunas `colecao`, `id`, `json`). A leitura pede até 20 mil linhas, o que comporta mais de mil usuários com metas, skills e indicações. O n8n só regrava as linhas que mudaram. Sessões ficam em `TF_Sessoes`. As abas abaixo são a mesma informação em colunas, para leitura humana.

| Aba | Cabeçalhos (linha 1) |
|---|---|
| `TF_Registros` | colecao, id, json |
| `TF_Sessoes` | token, email, exp |
| `TF_Usuarios` | email, nome, cargo, departamento, perfil, status, gestor_email, disponibilidade, projeto_atual, risco |
| `TF_Metas` | id, email, titulo, competencia, status, progresso, inicio, prazo, projeto |
| `TF_Projetos` | codigo, nome, area, gestor_email, status, inicio, fim, criticidade, participantes |
| `TF_Skills` | id, nome, categoria, tipo, estrategica, status |
| `TF_Logs` | id, data, usuario, acao, entidade, detalhe |
| `TF_Premiacoes` | id, nome, descricao, financeira, valor, ativa |
| `TF_Reconhecimentos` | id, de, para, premiacao_id, mensagem, financeira, valor_sugerido, valor_aprovado, status, criado_em, gestor_email |
| `TF_Motor` | código do motor, partido na coluna A |

A hierarquia é o campo `gestor_email`: o gestor vê a própria equipe; o colaborador vê só o que é dele; o RH vê o quadro, com filtros e páginas de 40 registros. `TF_Usuarios` não tem colunas de bem-estar. `scripts/semear_talentflow.php` recria essas abas a partir do seed e **apaga** sessões e alterações já gravadas. Use só para restaurar a base fictícia.

### Reconhecimento

O colaborador ou o gestor indica um colega (nunca a si mesmo) e escolhe uma premiação. Se a premiação permitir dinheiro, a pessoa marca ou não a recompensa — o valor é o do catálogo, não um número livre.

1. A indicação fica **Aguardando gestor** (o gestor de quem foi indicado).
2. Sem recompensa financeira, a confirmação do gestor publica como **Reconhecido**.
3. Com recompensa, segue **Aguardando RH**. O RH autoriza (**Premiado**) ou recusa.
4. Se quem indica já é o gestor da pessoa, o dinheiro vai direto ao RH. Sem dinheiro, o reconhecimento já fica publicado.

O colega indicado não vê o valor enquanto o RH não autoriza. O RH cadastra as premiações.

### Fluxo no n8n e Gmail

Fluxo publicado: **TalentFlow PDI — API**.

Webhook → lê `TF_Motor` → lê as abas em lote → **IA não generativa** → **IA generativa** → motor → grava só as linhas alteradas → responde ao navegador. Em paralelo, os e-mails pendentes seguem para o Gmail.

A IA não generativa só entra quando o colaborador salva satisfação e bem-estar. Ela lê as notas, a carga e o sentimento (lista fechada) e devolve só a classe: Baixo, Médio ou Alto. O comentário livre não entra nesse nó.

A IA generativa só entra na MentorIA. Ela escreve a mensagem, os três passos, a pergunta de reflexão e o aviso, usando o título da meta e a dificuldade. Não recebe bem-estar, comentário de clima nem dados de outras pessoas. Se a dificuldade fala de saúde, o aviso pede ajuda do gestor, do RH ou de um serviço especializado, sem diagnóstico.

Destinatários `@empresa.com` e `*.example` são entregues em `senaijesse@gmail.com`. Os demais endereços seguem o valor original.

| Modelo | Quando | Destinatário |
|---|---|---|
| `meta_enviada` | Colaborador envia meta para avaliação ou para validar a conclusão | Gestor |
| `meta_decisao` | Gestor aprova, pede ajuste, rejeita ou valida a conclusão | Colaborador |
| `conversa` | Gestor convida para conversa de acompanhamento | Colaborador |
| `skill_status` | RH valida ou rejeita uma skill | Colaborador |
| `alocacao` | Gestor pede análise de alocação, ou o RH abre plano de capacitação | RH, ou o gestor do projeto |
| `apoio_rh` | Colaborador sinaliza que precisa de apoio | RH, sem conteúdo individual |
| `reconhecimento_colega` | Alguém indicou a pessoa | Colega indicado, sem o valor |
| `reconhecimento_gestor` | Indicação aguardando confirmação | Gestor da pessoa indicada |
| `reconhecimento_rh` | Há recompensa financeira para autorizar | RH |
| `reconhecimento_resultado` | A indicação foi publicada, premiada ou recusada | Indicado e quem indicou |

Falha de Gmail ou da gravação não derruba o fluxo inteiro: o nó segue e, se a planilha não gravou, a resposta ao navegador é 502.

### O que o TalentFlow ainda não cobre

- Senha, CSRF e limite de tentativas de login (isso existe no PDI Connect, não aqui).
- As nove abas e o webhook `/webhook/pdi-connect` do PDI Connect.
- Resumos semanal e mensal por cron.
- O site publicado em https://talentflow-weld.vercel.app foi testado em 5 de outubro de 2026. O roteiro e o resultado estão em [docs/testes-producao.md](docs/testes-producao.md). O TalentFlow fala direto com o n8n; o PHP da mesma hospedagem usa a conta de serviço pelas variáveis da Vercel.
