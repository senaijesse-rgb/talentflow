import { readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const html = readFileSync(join(root, "talentflow", "index.html"), "utf8");
const open = html.lastIndexOf("<script>");
const script = html.slice(open + "<script>".length, html.indexOf("</script>", open));

const from = script.indexOf("const clone = ");
const dbAt = script.indexOf("const DB = (() => {");
const riscoAt = script.indexOf("function calcRisco");
const forEachAt = script.indexOf("DB.users.forEach(u => { u.risco = calcRisco(u.satisfacao); });");
const mockBackendAt = script.indexOf("function mockBackend");
if ([from, dbAt, riscoAt, forEachAt, mockBackendAt].some(n => n < 0)) {
  throw new Error("Marcadores do motor não encontrados no index.html.");
}

const beforeDb = script.slice(from, dbAt);
const afterSeed = script.slice(riscoAt, mockBackendAt);
const seedBody = script.slice(from, forEachAt + "DB.users.forEach(u => { u.risco = calcRisco(u.satisfacao); });".length);
const seed = new Function(`${seedBody}\nreturn DB;`)();
delete seed.sessions;

const queueOld = `function queueEmail(templateId, para, vars = {}) {
  const tpl = DB.configuracoes.templates.find(x => x.id === templateId);
  const fill = s => String(s || "").replace(/\\{\\{(\\w+)\\}\\}/g, (_, k) => vars[k] ?? "");
  DB.emails.unshift({ id: uid("MAIL"), data: nowISO(), para, assunto: tpl ? fill(tpl.assunto) : templateId, template: templateId, status: "Enviado via n8n/Gmail (simulado)" });
}`;
const queueNew = `function queueEmail(templateId, para, vars = {}) {
  const tpl = DB.configuracoes.templates.find(x => x.id === templateId);
  const fill = s => String(s || "").replace(/\\{\\{(\\w+)\\}\\}/g, (_, k) => vars[k] ?? "");
  const assunto = tpl ? fill(tpl.assunto) : templateId;
  const corpo = tpl ? fill(tpl.corpo) : "";
  const seguro = String(corpo).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  const html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#0f172a"><p>' + seguro.replace(/\\n/g, "<br>") + '</p><p style="color:#64748b;font-size:12px">TalentFlow PDI · mensagem automática</p></div>';
  DB.emails.unshift({ id: uid("MAIL"), data: nowISO(), para, assunto, template: templateId, status: "Na fila", pendente: true, html });
}`;
if (!afterSeed.includes(queueOld)) throw new Error("queueEmail não encontrado.");
const rules = beforeDb + afterSeed.replace(queueOld, queueNew);

const motor = `function executarTalentFlow(db, recurso, body, token) {
  const DB = db;
  if (!DB.sessions) DB.sessions = [];
  if (!DB.premiacoes) DB.premiacoes = [];
  if (!DB.reconhecimentos) DB.reconhecimentos = [];
${rules}
  const agora = Date.now();
  DB.sessions = DB.sessions.filter(s => s.exp > agora);
  DB.users.forEach(u => { u.risco = calcRisco(u.satisfacao); });
  let statusCode = 200;
  let result;
  try {
    if (!MOCK[recurso]) throw new ApiError("Endpoint não encontrado.", 404);
    if (recurso === "login") {
      result = MOCK.login(body || {}, null, "POST");
      const novo = uid("TK") + Math.random().toString(36).slice(2, 12);
      DB.sessions.push({ token: novo, email: result.user.email, exp: agora + 12 * 60 * 60 * 1000 });
      result = { ...result, token: novo };
    } else {
      const session = DB.sessions.find(s => s.token && s.token === token);
      if (!session) throw new ApiError("Sessão inválida ou expirada. Faça login novamente.", 401);
      const actor = dbUser(session.email);
      if (!actor || actor.status !== "Ativo") throw new ApiError("Sessão inválida ou expirada. Faça login novamente.", 401);
      result = MOCK[recurso](body || {}, actor, "POST");
    }
  } catch (e) {
    statusCode = e.status || 500;
    result = { success: false, message: e.message || "Erro interno." };
  }
  const emails = (DB.emails || []).filter(e => e.pendente).map(e => ({ para: e.para, assunto: e.assunto, html: e.html }));
  (DB.emails || []).forEach(e => { if (e.pendente) { e.pendente = false; e.status = "Enviado via n8n/Gmail"; delete e.html; } });
  DB.atualizado_em = new Date().toISOString();
  return { statusCode, body: result, emails };
}

function linhasPlanilha(db) {
  const usuarios = [["email", "nome", "cargo", "departamento", "perfil", "status", "gestor_email", "disponibilidade", "projeto_atual", "risco"]];
  for (const u of db.users) usuarios.push([u.email, u.nome, u.cargo, u.departamento, u.perfil, u.status, u.gestorEmail || "", u.disponibilidade || "", u.projetoAtual || "", u.risco || ""]);
  const metas = [["id", "email", "titulo", "competencia", "status", "progresso", "inicio", "prazo", "projeto"]];
  for (const m of db.metas) metas.push([m.id, m.email, m.titulo, m.competencia, m.status, String(m.progresso ?? ""), m.inicio || "", m.prazo || "", m.projeto || ""]);
  const projetos = [["codigo", "nome", "area", "gestor_email", "status", "inicio", "fim", "criticidade", "participantes"]];
  for (const p of db.projetos) projetos.push([p.codigo, p.nome, p.area, p.gestorEmail, p.status, p.inicio || "", p.fim || "", p.criticidade || "", (p.participantes || []).join(", ")]);
  const skills = [["id", "nome", "categoria", "tipo", "estrategica", "status"]];
  for (const s of db.skillsCatalog) skills.push([s.id, s.nome, s.categoria, s.tipo, s.estrategica ? "sim" : "nao", s.status]);
  const logs = [["id", "data", "usuario", "acao", "entidade", "detalhe"]];
  for (const l of (db.logs || []).slice(0, 3000)) logs.push([l.id, l.data, l.usuario, l.acao, l.entidade, l.detalhe || ""]);
  const premiacoes = [["id", "nome", "descricao", "financeira", "valor", "ativa"]];
  for (const p of db.premiacoes || []) premiacoes.push([p.id, p.nome, p.descricao || "", p.financeira ? "sim" : "nao", String(p.valor ?? 0), p.ativa ? "sim" : "nao"]);
  const reconhecimentos = [["id", "de", "para", "premiacao_id", "mensagem", "financeira", "valor_sugerido", "valor_aprovado", "status", "criado_em", "gestor_email"]];
  for (const r of db.reconhecimentos || []) reconhecimentos.push([r.id, r.de, r.para, r.premiacaoId, r.mensagem, r.financeira ? "sim" : "nao", String(r.valorSugerido ?? ""), r.valorAprovado == null ? "" : String(r.valorAprovado), r.status, r.criadoEm || "", r.gestorEmail || ""]);
  return { usuarios, metas, projetos, skills, logs, premiacoes, reconhecimentos };
}

function registrosPlanilha(db) {
  const linhas = [["colecao", "id", "json"]];
  const push = (colecao, id, obj) => linhas.push([colecao, String(id), JSON.stringify(obj)]);
  for (const u of db.users || []) push("users", u.email, u);
  for (const c of db.categorias || []) push("categorias", c, { nome: c });
  for (const s of db.skillsCatalog || []) push("skillsCatalog", s.id, s);
  for (const s of db.userSkills || []) push("userSkills", s.id, s);
  for (const p of db.projetos || []) push("projetos", p.codigo, p);
  for (const p of db.pdis || []) push("pdis", p.id, p);
  for (const m of db.metas || []) push("metas", m.id, m);
  for (const a of db.aprovacoes || []) push("aprovacoes", a.id, a);
  for (const t of db.timeline || []) push("timeline", t.id, t);
  for (const m of db.mentoria || []) push("mentoria", m.id, m);
  for (const l of db.logs || []) push("logs", l.id, l);
  for (const e of db.emails || []) { const c = { ...e }; delete c.html; delete c.pendente; push("emails", c.id, c); }
  for (const p of db.premiacoes || []) push("premiacoes", p.id, p);
  for (const r of db.reconhecimentos || []) push("reconhecimentos", r.id, r);
  if (db.configuracoes) push("configuracoes", "config", db.configuracoes);
  if (db.estatisticas) push("estatisticas", "stats", db.estatisticas);
  return linhas;
}
`;

writeFileSync(join(root, "talentflow", "motor.js"), motor);
writeFileSync(join(root, "storage", "talentflow-seed.json"), JSON.stringify(seed));
const projetar = new Function(`${motor}\nreturn { linhasPlanilha, registrosPlanilha };`)();
writeFileSync(join(root, "storage", "talentflow-abas.json"), JSON.stringify(projetar.linhasPlanilha(seed)));
writeFileSync(join(root, "storage", "talentflow-registros.json"), JSON.stringify(projetar.registrosPlanilha(seed)));

const fn = new Function(`${motor}\nreturn { executarTalentFlow, linhasPlanilha };`)();
const db = JSON.parse(JSON.stringify(seed));
const login = fn.executarTalentFlow(db, "login", { email: "helena.rocha@empresa.com" }, "");
if (login.statusCode !== 200 || !login.body.token) throw new Error("login RH falhou: " + JSON.stringify(login.body));
const dash = fn.executarTalentFlow(db, "dashboard", {}, login.body.token);
if (!dash.body.users || dash.body.users.length < 8) throw new Error("dashboard RH incompleto");
if (dash.body.users.some(u => u.satisfacao && u.satisfacao.comentario)) throw new Error("RH recebeu comentário individual de bem-estar");
const ana = fn.executarTalentFlow(db, "login", { email: "ana.souza@empresa.com" }, "");
const dashAna = fn.executarTalentFlow(db, "dashboard", {}, ana.body.token);
if (dashAna.body.users.some(u => u.email !== "ana.souza@empresa.com")) throw new Error("colaborador viu dados de outra pessoa");
const marcos = fn.executarTalentFlow(db, "login", { email: "marcos.vieira@empresa.com" }, "");
const dashM = fn.executarTalentFlow(db, "dashboard", {}, marcos.body.token);
if (dashM.body.users.some(u => u.email !== "marcos.vieira@empresa.com" && u.satisfacao)) throw new Error("gestor viu clima individual da equipe");
const semToken = fn.executarTalentFlow(db, "dashboard", { solicitante: "helena.rocha@empresa.com" }, "");
if (semToken.statusCode !== 401) throw new Error("dashboard sem token deveria ser 401");
const meta = fn.executarTalentFlow(db, "metas", { acao: "salvar", enviar: true, meta: { titulo: "Meta de teste", competencia: "Comunicação", descricao: "Descrição da meta de teste.", objetivo: "Objetivo claro da meta.", inicio: "2026-10-06", prazo: "2026-12-01", progresso: 0 } }, ana.body.token);
if (meta.statusCode !== 200) throw new Error("salvar meta falhou: " + meta.body.message);
if (!meta.emails.length) throw new Error("envio de meta não enfileirou e-mail");
if (db.emails.some(e => e.html || e.pendente)) throw new Error("e-mail pendente permaneceu na base");
const indicou = fn.executarTalentFlow(db, "reconhecimento", { acao: "indicar", para: "diego.alves@empresa.com", premiacaoId: "PRM01", mensagem: "O Diego ajudou a revisar a API do portal nesta sprint." }, ana.body.token);
if (indicou.statusCode !== 200) throw new Error("indicação falhou: " + indicou.body.message);
const siMesmo = fn.executarTalentFlow(db, "reconhecimento", { acao: "indicar", para: "ana.souza@empresa.com", premiacaoId: "PRM01", mensagem: "Indicação inválida de si mesmo agora." }, ana.body.token);
if (siMesmo.statusCode !== 400) throw new Error("autoindicação deveria falhar");
const financeiro = fn.executarTalentFlow(db, "reconhecimento", { acao: "indicar", para: "diego.alves@empresa.com", premiacaoId: "PRM02", mensagem: "O Diego segurou a migração e evitou incidente.", querRecompensa: "sim" }, ana.body.token);
if (financeiro.statusCode !== 200 || !financeiro.body.reconhecimento.financeira) throw new Error("indicação financeira falhou");
const aprovado = fn.executarTalentFlow(db, "reconhecimento", { acao: "decidir", id: financeiro.body.reconhecimento.id, decisao: "aprovar" }, marcos.body.token);
if (aprovado.body.reconhecimento.status !== "Aguardando RH") throw new Error("gestor deveria encaminhar o valor ao RH");
const premiado = fn.executarTalentFlow(db, "reconhecimento", { acao: "decidir", id: financeiro.body.reconhecimento.id, decisao: "aprovar" }, login.body.token);
if (premiado.body.reconhecimento.status !== "Premiado") throw new Error("RH deveria premiar");
const fora = fn.executarTalentFlow(db, "reconhecimento", { acao: "decidir", id: indicou.body.reconhecimento.id, decisao: "aprovar" }, ana.body.token);
if (fora.statusCode !== 403) throw new Error("colaborador não pode decidir indicação");
const metaAna = db.metas.find(m => m.email === "ana.souza@empresa.com");
const plano = fn.executarTalentFlow(db, "mentorIA", { metaId: metaAna.id, dificuldade: "Não estou conseguindo tempo para estudar nesta semana.", planoIA: { mensagem: "Plano do nó", passos: ["Primeiro passo concreto.", "Segundo passo concreto.", "Terceiro passo concreto."], reflexao: "O que muda amanhã?", aviso: null, origem: "generativa" } }, ana.body.token);
if (plano.statusCode !== 200 || plano.body.origem !== "generativa") throw new Error("plano da IA generativa não foi aceito");
const clima = fn.executarTalentFlow(db, "avaliarColaborador", { consentimento: true, classeIA: "Alto", satisfacao: { geral: 9, projeto: 9, lideranca: 9, carga: "Baixa", sentimento: "Muito bem" } }, ana.body.token);
if (clima.statusCode !== 200 || db.users.find(u => u.email === "ana.souza@empresa.com").risco !== "Alto") throw new Error("classe da IA não generativa não foi aplicada");
const json = JSON.stringify(db);
console.log(JSON.stringify({
  ok: true,
  bytes: json.length,
  users: db.users.length,
  metas: db.metas.length,
  emails: meta.emails.length,
  assunto: meta.emails[0].assunto
}));
