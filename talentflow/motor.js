function executarTalentFlow(db, recurso, body, token) {
  const DB = db;
  if (!DB.sessions) DB.sessions = [];
  if (!DB.premiacoes) DB.premiacoes = [];
  if (!DB.reconhecimentos) DB.reconhecimentos = [];
const clone = o => (o === undefined ? undefined : JSON.parse(JSON.stringify(o)));
const avg = a => (a.length ? a.reduce((x, y) => x + Number(y || 0), 0) / a.length : 0);
const clampPct = v => Math.max(0, Math.min(100, Math.round(Number(v) || 0)));
const pick = (o, keys) => Object.fromEntries(keys.filter(k => o[k] !== undefined).map(k => [k, typeof o[k] === "string" ? o[k].trim() : o[k]]));
const EMAIL_RE = /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i;
const isoLocal = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
const daysFrom = n => { const d = new Date(); d.setHours(12, 0, 0, 0); d.setDate(d.getDate() + n); return isoLocal(d); };
const monthsFrom = n => { const d = new Date(); d.setMonth(d.getMonth() + n); return isoLocal(d); };
const dt = (n, h = 10) => { const d = new Date(); d.setDate(d.getDate() + n); d.setHours(h, 15, 0, 0); return d.toISOString(); };
const todayISO = () => daysFrom(0);
const nowISO = () => new Date().toISOString();
const daysBetween = (a, b) => Math.round((new Date(b + "T12:00:00") - new Date(a + "T12:00:00")) / 86400000);
const fmtDate = iso => (iso ? new Date(String(iso).slice(0, 10) + "T12:00:00").toLocaleDateString("pt-BR") : "—");
const fmtDateTime = iso => (iso ? new Date(iso).toLocaleString("pt-BR", { dateStyle: "short", timeStyle: "short" }) : "—");
const uid = p => `${p}-${Date.now().toString(36).slice(-4).toUpperCase()}${Math.random().toString(36).slice(2, 5).toUpperCase()}`;
const firstName = n => String(n || "").split(" ")[0];
const initials = n => String(n || "?").split(" ").filter(Boolean).slice(0, 2).map(p => p[0]).join("").toUpperCase();
const refreshIcons = () => { if (window.lucide) window.lucide.createIcons(); };

class ApiError extends Error {
  constructor(message, status = 0) { super(message); this.name = "ApiError"; this.status = status; }
}

/* ============================ 3. DOMÍNIO ============================ */
const LEVELS = ["Básico", "Intermediário", "Avançado", "Especialista"];
const lvl = n => LEVELS.indexOf(n) + 1;
const META_STATUS = ["Rascunho", "Aguardando avaliação do gestor", "Aprovada", "Necessita ajuste", "Em andamento", "Aguardando validação de conclusão", "Concluída", "Cancelada"];
const META_EDITAVEIS = ["Rascunho", "Necessita ajuste"];
const META_FINAIS = ["Concluída", "Cancelada"];
const SKILL_STATUS = ["Pendente", "Em validação", "Validada", "Rejeitada", "Expirada"];
const SKILL_TIPOS = ["Técnica", "Comportamental", "Ferramenta", "Idioma", "Gestão"];
const PROJ_STATUS = ["Planejado", "Ativo", "Em pausa", "Concluído", "Cancelado"];
const CRITICIDADE = ["Baixa", "Média", "Alta", "Crítica"];
const PDI_STATUS = ["Em elaboração", "Ativo", "Em revisão", "Concluído"];
const CARGAS = ["Baixa", "Adequada", "Alta", "Muito alta"];
const SENTIMENTOS = ["Muito bem", "Bem", "Neutro", "Sobrecarregado", "Preciso de apoio"];
const RISCOS = ["Baixo", "Médio", "Alto"];
const DISPONIBILIDADE = ["Disponível", "Parcial", "Alocado"];
const PERFIS = { colaborador: "Colaborador", gestor: "Gestor", rh: "Administrador RH" };
const SAT_FAIXAS = [["0-4", "0 a 4 (crítica)"], ["5-6", "5 a 6 (atenção)"], ["7-8", "7 a 8 (boa)"], ["9-10", "9 a 10 (excelente)"]];

// Transições de status permitidas na avaliação do gestor (replicar a regra no n8n)
const DECISOES = [
  { v: "aprovar", label: "Aprovar", icon: "circle-check", to: "Aprovada", hist: "Aprovada", from: ["Aguardando avaliação do gestor"] },
  { v: "ajuste", label: "Solicitar ajuste", icon: "pencil", to: "Necessita ajuste", hist: "Ajuste solicitado", req: true, from: ["Aguardando avaliação do gestor", "Aguardando validação de conclusão", "Aprovada", "Em andamento"] },
  { v: "rejeitar", label: "Rejeitar", icon: "circle-x", to: "Cancelada", hist: "Rejeitada", req: true, from: ["Aguardando avaliação do gestor"] },
  { v: "validar", label: "Validar conclusão", icon: "badge-check", to: "Concluída", hist: "Conclusão validada", from: ["Aguardando validação de conclusão"] }
];

const isOverdue = m => !!m && m.prazo < todayISO() && !META_FINAIS.includes(m.status);
const hasSkill = (us, skillId, nivel) => us.skillId === skillId && us.status === "Validada" && lvl(us.nivel) >= lvl(nivel);

/* ============================ 4. DADOS MOCKADOS (fictícios) ============================ */
function calcRisco(s) {
  if (!s) return "Baixo";
  let p = 0;
  if (s.geral <= 5) p += 2; else if (s.geral <= 7) p += 1;
  if (s.lideranca <= 5) p += 1;
  if (s.projeto <= 5) p += 1;
  if (s.carga === "Muito alta") p += 2; else if (s.carga === "Alta") p += 1;
  if (s.sentimento === "Preciso de apoio") p += 2; else if (s.sentimento === "Sobrecarregado") p += 1;
  return p >= DB.configuracoes.regras.limiteRiscoAlto ? "Alto" : p >= 2 ? "Médio" : "Baixo";
}
DB.users.forEach(u => { u.risco = calcRisco(u.satisfacao); });

/* ============================ 5. REGRAS COMPARTILHADAS ============================ */
// Cobertura de skills do projeto: compatíveis, disponíveis e gap
function coverageFor(p, users, userSkills) {
  return p.skillsRequeridas.map(r => {
    const comp = users.filter(u => u.status === "Ativo" && u.perfil !== "rh" && userSkills.some(s => s.email === u.email && hasSkill(s, r.skillId, r.nivel)));
    const noProjeto = comp.filter(u => p.participantes.includes(u.email)).length;
    const disponiveis = comp.filter(u => !p.participantes.includes(u.email) && u.disponibilidade !== "Alocado").length;
    const faltaNoProjeto = Math.max(0, r.quantidade - noProjeto);
    return { ...r, compativeis: comp.length, noProjeto, disponiveis, faltaNoProjeto, gap: Math.max(0, faltaNoProjeto - disponiveis), coberta: faltaNoProjeto === 0 };
  });
}
function similarProjects(projetos, p) {
  const ids = p.skillsRequeridas.map(r => r.skillId);
  return projetos.filter(x => x.codigo !== p.codigo && (x.area === p.area || x.skillsRequeridas.some(r => ids.includes(r.skillId))));
}
function compatibleUsers(data, p, req) {
  const similares = similarProjects(data.projetos, p).map(x => x.codigo);
  return data.users.filter(u => u.status === "Ativo" && u.perfil !== "rh").map(u => {
    const s = data.userSkills.find(x => x.email === u.email && hasSkill(x, req.skillId, req.nivel));
    if (!s) return null;
    return { u, s, noProjeto: p.participantes.includes(u.email), disponivel: u.disponibilidade !== "Alocado", similar: [u.projetoAtual, ...(u.projetosAnteriores || [])].some(c => c && similares.includes(c)) };
  }).filter(Boolean);
}
// Agregação anônima de clima: só retorna dados se houver amostra mínima
function aggregateClima(list) {
  const s = list.map(u => u.satisfacao).filter(Boolean);
  if (s.length < DB.configuracoes.regras.anonimatoMinimo) return { n: s.length, restrito: true };
  const count = (key, values) => Object.fromEntries(values.map(v => [v, s.filter(x => x[key] === v).length]));
  return { n: s.length, restrito: false, geral: avg(s.map(x => x.geral)), projeto: avg(s.map(x => x.projeto)), lideranca: avg(s.map(x => x.lideranca)), sentimentos: count("sentimento", SENTIMENTOS), cargas: count("carga", CARGAS) };
}
function inSatRange(v, faixa) {
  if (v == null) return false;
  const [a, b] = faixa.split("-").map(Number);
  return v >= a && v <= b;
}

/* ============================ 6. BACKEND SIMULADO (modo demo) ============================
   Reproduz o que os fluxos do n8n devem fazer: validar identidade, perfil e hierarquia,
   ler/gravar no Google Sheets, registrar logs e disparar e-mails pelo Gmail. */
const logAudit = (usuario, acao, entidade, detalhe = "") => DB.logs.unshift({ id: uid("LOG"), data: nowISO(), usuario, acao, entidade, detalhe });
const addTimeline = (email, tipo, texto) => DB.timeline.unshift({ id: uid("TL"), email, data: nowISO(), tipo, texto });
function queueEmail(templateId, para, vars = {}) {
  const tpl = DB.configuracoes.templates.find(x => x.id === templateId);
  const fill = s => String(s || "").replace(/\{\{(\w+)\}\}/g, (_, k) => vars[k] ?? "");
  const assunto = tpl ? fill(tpl.assunto) : templateId;
  const corpo = tpl ? fill(tpl.corpo) : "";
  const seguro = String(corpo).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  const html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#0f172a"><p>' + seguro.replace(/\n/g, "<br>") + '</p><p style="color:#64748b;font-size:12px">TalentFlow PDI · mensagem automática</p></div>';
  DB.emails.unshift({ id: uid("MAIL"), data: nowISO(), para, assunto, template: templateId, status: "Na fila", pendente: true, html });
}
const deny = (msg = "Acesso negado: seu perfil não tem permissão para esta ação.") => { throw new ApiError(msg, 403); };
const notFound = (msg = "Registro não encontrado.") => { throw new ApiError(msg, 404); };
const bad = msg => { throw new ApiError(msg, 400); };
const dbUser = email => DB.users.find(u => u.email === email);
const isTeamOf = (gestor, email) => dbUser(email)?.gestorEmail === gestor.email;
const canManageProjectDb = (actor, p) => actor.perfil === "rh" || (actor.perfil === "gestor" && p.gestorEmail === actor.email);
const nextId = (list, prefix, pad = 2) => prefix + String(list.reduce((mx, x) => Math.max(mx, parseInt(String(x.id).replace(/\D/g, ""), 10) || 0), 0) + 1).padStart(pad, "0");

// Retorna somente os dados que o perfil pode ver (o n8n deve aplicar o mesmo filtro)
function scopeFor(actor) {
  const withCoverage = p => ({ ...p, cobertura: coverageFor(p, DB.users, DB.userSkills) });
  const stripWellbeing = u => { const c = clone(u); if (c.satisfacao) { delete c.satisfacao.sentimento; delete c.satisfacao.comentario; } return c; };
  const contatos = DB.users.map(u => ({ email: u.email, nome: u.nome, cargo: u.cargo, departamento: u.departamento, perfil: u.perfil }));
  const base = { skillsCatalog: DB.skillsCatalog, categorias: DB.categorias, regras: DB.configuracoes.regras, contatos };
  const premiacoes = DB.premiacoes || [];
  const reconhecimentosTodos = DB.reconhecimentos || [];
  const enxugarValor = r => {
    if (actor.perfil === "rh" || actor.perfil === "gestor" || r.de === actor.email || r.status === "Premiado") return r;
    const c = clone(r);
    c.valorSugerido = null;
    c.valorAprovado = null;
    return c;
  };

  if (actor.perfil === "rh") {
    const ativos = DB.users.filter(u => u.status === "Ativo" && u.perfil !== "rh");
    return clone({
      ...base, users: DB.users.map(stripWellbeing), metas: DB.metas, userSkills: DB.userSkills, projetos: DB.projetos.map(withCoverage),
      pdis: DB.pdis, aprovacoes: DB.aprovacoes, timeline: DB.timeline, mentoria: [], logs: DB.logs, emails: DB.emails,
      premiacoes, reconhecimentos: reconhecimentosTodos.map(enxugarValor),
      configuracoes: DB.configuracoes, climaGlobal: aggregateClima(ativos), estatisticas: DB.estatisticas
    });
  }
  const self = dbUser(actor.email);
  const emails = actor.perfil === "gestor" ? [actor.email, ...DB.users.filter(u => u.gestorEmail === actor.email).map(u => u.email)] : [actor.email];
  const inScope = x => emails.includes(x.email);
  const users = DB.users.filter(inScope).map(u => {
    if (u.email === actor.email) { const c = clone(u); delete c.risco; return c; } // risco é indicador gerencial
    const c = stripWellbeing(u); c.satisfacao = null; return c; // gestor não vê clima individual
  });
  const metas = DB.metas.filter(inScope);
  const metaIds = metas.map(m => m.id);
  const projetos = DB.projetos.filter(p => actor.perfil === "gestor"
    ? p.gestorEmail === actor.email || p.participantes.some(e => emails.includes(e))
    : p.participantes.includes(actor.email) || self.projetoAtual === p.codigo || self.projetosAnteriores.includes(p.codigo)).map(withCoverage);
  return clone({
    ...base, users, metas, userSkills: DB.userSkills.filter(inScope), projetos, pdis: DB.pdis.filter(inScope),
    premiacoes: premiacoes.filter(p => p.ativa),
    reconhecimentos: reconhecimentosTodos.filter(r => r.de === actor.email || r.para === actor.email || (actor.perfil === "gestor" && (emails.includes(r.de) || emails.includes(r.para)))).map(enxugarValor),
    aprovacoes: DB.aprovacoes.filter(a => metaIds.includes(a.metaId)), timeline: DB.timeline.filter(inScope),
    mentoria: actor.perfil === "colaborador" ? DB.mentoria.filter(inScope) : [],
    climaEquipe: actor.perfil === "gestor" ? aggregateClima(DB.users.filter(u => u.gestorEmail === actor.email && u.status === "Ativo")) : null
  });
}

// MentorIA: orientação de desenvolvimento profissional (sem terapia, diagnóstico ou aconselhamento clínico)
function gerarPlanoMentoria(meta, dificuldade) {
  const txt = dificuldade.toLowerCase();
  const sensivel = /(ansiedad|depress|burnout|esgotad|ass[eé]dio|p[aâ]nico|crise|n[aã]o aguento|ins[oô]nia|chor|adoec|discrimina|medica)/.test(txt);
  const temas = [
    { re: /(tempo|prazo|agenda|corrid|demanda|entrega|sobrecarg|prioriz)/, foco: "organização do tempo e priorização",
      passos: ["Dias 1–2: liste as atividades da semana e reserve dois blocos fixos de 45 minutos na agenda exclusivamente para a meta “{m}”.",
               "Dias 3–5: divida a meta em uma entrega mínima que caiba nesses blocos (um módulo, um rascunho ou um teste) e conclua-a.",
               "Dias 6–7: registre a evidência na plataforma e leve ao seu gestor uma proposta de priorização caso a demanda continue alta."],
      reflexao: "Qual atividade da sua semana poderia ser delegada, adiada ou simplificada para abrir espaço para o seu desenvolvimento?" },
    { re: /(conhec|t[eé]cnic|aprend|curso|estud|entend|complex|dif[ií]cil|teoria|pr[aá]tica)/, foco: "aprendizagem técnica",
      passos: ["Dias 1–2: identifique exatamente qual conceito de “{c}” está travando seu avanço e escolha um único material de referência.",
               "Dias 3–5: pratique com um exercício curto aplicado ao seu contexto{p} e anote dúvidas objetivas.",
               "Dias 6–7: agende 30 minutos com um colega mais experiente para revisar as dúvidas e registre o aprendizado como evidência."],
      reflexao: "O que você já sabe hoje que pode servir de ponte para o conhecimento que está faltando?" },
    { re: /(motiva|desanim|foco|procrastin|interesse|sentido|cansad)/, foco: "engajamento e ritmo",
      passos: ["Dias 1–2: reescreva em uma frase por que a meta “{m}” importa para sua carreira e para o seu próximo passo profissional.",
               "Dias 3–5: defina uma micro-meta diária de 20 minutos e marque cada dia cumprido — pequenas vitórias geram ritmo.",
               "Dias 6–7: compartilhe o avanço com seu gestor ou um colega e peça um feedback específico sobre o que já evoluiu."],
      reflexao: "Em que momento você se sentiu mais motivado com essa meta e o que estava diferente naquele momento?" },
    { re: /(acesso|dados|ferrament|recurso|licen[cç]a|sistema|permiss|or[cç]amento|equipamento)/, foco: "recursos e acessos",
      passos: ["Dias 1–2: liste quais acessos, ferramentas ou informações estão faltando e o impacto de cada um na meta “{m}”.",
               "Dias 3–5: formalize o pedido ao responsável (gestor, TI ou área dona do dado) com justificativa e prazo sugerido.",
               "Dias 6–7: enquanto aguarda, avance em uma parte da meta que não dependa desse recurso e registre a evidência."],
      reflexao: "Existe alguma alternativa temporária que permita avançar mesmo sem o recurso ideal?" },
    { re: /(gestor|feedback|equipe|colega|comunica|alinhament|expectativa|conflit|relacion)/, foco: "alinhamento e comunicação",
      passos: ["Dias 1–2: escreva quais expectativas você entende que existem sobre a meta “{m}” e onde estão as dúvidas.",
               "Dias 3–5: agende uma conversa curta com seu gestor para validar critérios de sucesso, prazo e evidências esperadas.",
               "Dias 6–7: atualize a meta na plataforma com o que foi combinado e defina o próximo ponto de acompanhamento."],
      reflexao: "Que pergunta, se feita ao seu gestor hoje, eliminaria a maior parte da sua incerteza?" }
  ];
  const padrao = { foco: "execução da meta",
    passos: ["Dias 1–2: revise a meta “{m}” e defina qual é a próxima entrega concreta e verificável.",
             "Dias 3–5: execute essa entrega em blocos curtos e anote o que facilitou ou dificultou o avanço.",
             "Dias 6–7: registre a evidência, atualize o percentual de progresso e compartilhe o resultado com seu gestor."],
    reflexao: "Como você saberá, de forma objetiva, que avançou nesta meta ao final da semana?" };
  const tema = temas.find(x => x.re.test(txt)) || padrao;
  const fill = s => s.replace("{m}", () => meta.titulo).replace("{c}", () => meta.competencia).replace("{p}", () => (meta.projeto ? ` (por exemplo, no projeto ${meta.projeto})` : ""));
  let aviso = null;
  if (sensivel) aviso = "Sua mensagem menciona questões que vão além do plano de desenvolvimento. A MentorIA não realiza diagnóstico nem aconselhamento clínico ou psicológico. Recomendamos conversar com seu gestor ou com o RH, que podem acionar os canais de apoio da empresa de forma confidencial. Em situação de urgência, procure atendimento especializado (CVV: 188 · SAMU: 192).";
  else if (isOverdue(meta)) aviso = "Esta meta está com o prazo vencido. Procure seu gestor para repactuar o prazo e registrar o novo combinado.";
  else if (/(bloque|imposs[ií]vel|n[aã]o consigo)/.test(txt)) aviso = "Se o bloqueio persistir após esses passos, procure seu gestor ou o RH para avaliar ajustes na meta.";
  return {
    mensagem: `Obrigado por compartilhar. Entendi que sua principal dificuldade na meta “${meta.titulo}” está relacionada a ${tema.foco}. Montei um plano curto e prático para os próximos sete dias:`,
    passos: tema.passos.map(fill), reflexao: tema.reflexao, aviso
  };
}

function buildReport(f) {
  const rows = [];
  DB.metas.forEach(m => {
    const u = dbUser(m.email); if (!u) return;
    const pdi = DB.pdis.find(p => p.email === m.email);
    if (f.area && u.departamento !== f.area) return;
    if (f.gestor && u.gestorEmail !== f.gestor) return;
    if (f.projeto && m.projeto !== f.projeto && u.projetoAtual !== f.projeto) return;
    if (f.statusPdi && pdi?.status !== f.statusPdi) return;
    if (f.statusMeta && m.status !== f.statusMeta) return;
    if (f.skill && !DB.userSkills.some(s => s.email === u.email && s.skillId === f.skill && s.status === "Validada")) return;
    if (f.risco && u.risco !== f.risco) return;
    if (f.satisfacao && !inSatRange(u.satisfacao?.geral, f.satisfacao)) return;
    if (f.de && m.prazo < f.de) return;
    if (f.ate && m.prazo > f.ate) return;
    rows.push({
      colaborador: u.nome, email: u.email, area: u.departamento, gestor: dbUser(u.gestorEmail)?.nome || "", projeto: m.projeto || u.projetoAtual || "",
      statusPdi: pdi?.status || "", meta: m.titulo, statusMeta: m.status, progresso: m.progresso, prazo: m.prazo,
      atrasada: isOverdue(m) ? "Sim" : "Não", risco: u.risco, satisfacao: u.satisfacao?.geral ?? ""
    });
  });
  return { rows, geradoEm: nowISO() };
}

const MOCK = {
  login({ email }) {
    const e = String(email || "").trim().toLowerCase();
    if (!EMAIL_RE.test(e)) bad("Informe um e-mail corporativo válido.");
    const u = dbUser(e);
    if (!u) throw new ApiError("E-mail não encontrado na base de colaboradores.", 404);
    if (u.status !== "Ativo") throw new ApiError("Usuário inativo. Procure o RH.", 403);
    logAudit(u.email, "Login", "Sessão", "Acesso à plataforma");
    return { user: { email: u.email, nome: u.nome, perfil: u.perfil, cargo: u.cargo, departamento: u.departamento } };
  },

  dashboard(_, actor) { return scopeFor(actor); },

  pdi(b, actor) {
    const p = DB.pdis.find(x => x.id === b.pdiId) || notFound();
    if (actor.perfil !== "rh" && !(actor.perfil === "gestor" && isTeamOf(actor, p.email))) deny();
    if (b.status && PDI_STATUS.includes(b.status)) p.status = b.status;
    logAudit(actor.email, "Atualizou PDI", `PDI ${p.id}`, p.status);
    return { pdi: p };
  },

  metas(b, actor) {
    if (actor.perfil !== "colaborador") deny("Apenas o próprio colaborador cadastra e atualiza as metas do seu PDI.");
    const today = todayISO();
    if (b.acao === "salvar") {
      const m = b.meta || {};
      ["titulo", "competencia", "descricao", "objetivo", "inicio", "prazo"].forEach(k => { if (!String(m[k] || "").trim()) bad(`Campo obrigatório não informado: ${k}.`); });
      if (m.prazo < m.inicio) bad("O prazo final deve ser posterior à data de início.");
      let meta;
      if (m.id) {
        meta = DB.metas.find(x => x.id === m.id) || notFound();
        if (meta.email !== actor.email) deny();
        if (!META_EDITAVEIS.includes(meta.status)) deny("Só é possível editar metas em rascunho ou com ajuste solicitado.");
      } else {
        meta = { id: nextId(DB.metas, "M"), email: actor.email, status: "Rascunho", criadaEm: today, pdiId: DB.pdis.find(p => p.email === actor.email)?.id };
        DB.metas.push(meta);
      }
      Object.assign(meta, pick(m, ["titulo", "competencia", "descricao", "objetivo", "inicio", "prazo", "projeto", "evidencia", "dificuldade"]), { progresso: clampPct(m.progresso), atualizadaEm: today });
      if (b.enviar) {
        meta.status = "Aguardando avaliação do gestor";
        const u = dbUser(actor.email);
        queueEmail("meta_enviada", u.gestorEmail, { nome: u.nome, gestor: dbUser(u.gestorEmail)?.nome, meta: meta.titulo });
      }
      logAudit(actor.email, b.enviar ? "Envio para avaliação" : (m.id ? "Editou meta" : "Criou meta"), `Meta ${meta.id}`, `Status: ${meta.status}`);
      addTimeline(actor.email, "meta", b.enviar ? `Meta “${meta.titulo}” enviada para avaliação do gestor.` : `Meta “${meta.titulo}” salva como rascunho.`);
      return { meta };
    }
    const meta = DB.metas.find(x => x.id === b.metaId) || notFound();
    if (meta.email !== actor.email) deny("Você só pode alterar suas próprias metas.");
    if (b.acao === "enviar") {
      if (!META_EDITAVEIS.includes(meta.status)) deny("Esta meta não pode ser enviada no status atual.");
      meta.status = "Aguardando avaliação do gestor"; meta.atualizadaEm = today;
      const u = dbUser(actor.email);
      queueEmail("meta_enviada", u.gestorEmail, { nome: u.nome, gestor: dbUser(u.gestorEmail)?.nome, meta: meta.titulo });
      logAudit(actor.email, "Envio para avaliação", `Meta ${meta.id}`, "Status: Aguardando avaliação do gestor");
      addTimeline(actor.email, "meta", `Meta “${meta.titulo}” enviada para avaliação do gestor.`);
      return { meta };
    }
    if (b.acao === "progresso") {
      if (!["Aprovada", "Em andamento"].includes(meta.status)) deny("O progresso só pode ser atualizado em metas aprovadas ou em andamento.");
      meta.progresso = clampPct(b.progresso);
      if (b.dificuldade !== undefined) meta.dificuldade = String(b.dificuldade).trim();
      if (b.evidencia) meta.evidencia = String(b.evidencia).trim();
      meta.status = meta.progresso >= 100 ? "Aguardando validação de conclusão" : "Em andamento";
      meta.atualizadaEm = today;
      if (meta.progresso >= 100) {
        const u = dbUser(actor.email);
        queueEmail("meta_enviada", u.gestorEmail, { nome: u.nome, gestor: dbUser(u.gestorEmail)?.nome, meta: meta.titulo + " (validação de conclusão)" });
      }
      logAudit(actor.email, "Atualizou progresso", `Meta ${meta.id}`, `${meta.progresso}%`);
      addTimeline(actor.email, "progresso", `Progresso da meta “${meta.titulo}” atualizado para ${meta.progresso}%.`);
      return { meta };
    }
    bad("Ação inválida.");
  },

  aprovarMeta(b, actor) {
    if (actor.perfil === "colaborador") deny("Colaboradores não podem aprovar metas.");
    const meta = DB.metas.find(x => x.id === b.metaId) || notFound();
    if (meta.email === actor.email) deny("Não é permitido avaliar a própria meta.");
    if (actor.perfil === "gestor" && !isTeamOf(actor, meta.email)) deny("Esta meta não pertence à sua equipe.");
    const comentario = String(b.comentario || "").trim();
    const owner = dbUser(meta.email);
    if (b.decisao === "comentario" || b.decisao === "conversa") {
      if (b.decisao === "comentario" && !comentario) bad("Escreva o comentário.");
      const label = b.decisao === "comentario" ? "Comentário" : "Conversa de acompanhamento solicitada";
      DB.aprovacoes.unshift({ id: nextId(DB.aprovacoes, "AP", 3), metaId: meta.id, email: meta.email, gestor: actor.email, decisao: label, comentario, data: nowISO() });
      logAudit(actor.email, label, `Meta ${meta.id}`, owner.email);
      addTimeline(meta.email, "aprovacao", `${label} do gestor na meta “${meta.titulo}”.`);
      queueEmail(b.decisao === "conversa" ? "conversa" : "meta_decisao", meta.email, { nome: owner.nome, gestor: actor.nome, meta: meta.titulo, decisao: label, comentario });
      return { meta, emailEnviado: true };
    }
    const dec = DECISOES.find(x => x.v === b.decisao) || bad("Decisão inválida.");
    if (dec.req && !comentario) bad("O comentário é obrigatório para esta decisão.");
    if (!dec.from.includes(meta.status)) throw new ApiError(`Ação indisponível para metas com status “${meta.status}”.`, 409);
    meta.status = dec.to;
    meta.atualizadaEm = todayISO();
    if (dec.v === "validar") meta.progresso = 100;
    DB.aprovacoes.unshift({ id: nextId(DB.aprovacoes, "AP", 3), metaId: meta.id, email: meta.email, gestor: actor.email, decisao: dec.hist, comentario, data: nowISO() });
    logAudit(actor.email, dec.hist, `Meta ${meta.id}`, `Novo status: ${meta.status}`);
    addTimeline(meta.email, "aprovacao", `${dec.hist}: “${meta.titulo}”.`);
    queueEmail("meta_decisao", meta.email, { nome: owner.nome, meta: meta.titulo, decisao: dec.hist, comentario: comentario || "—" });
    return { meta, emailEnviado: true };
  },

  avaliarColaborador(b, actor) {
    // Sempre grava para o próprio solicitante: o e-mail do corpo não é confiável
    const s = b.satisfacao || {};
    ["geral", "projeto", "lideranca"].forEach(k => { const v = Number(s[k]); if (!(v >= 0 && v <= 10)) bad("As escalas de satisfação devem estar entre 0 e 10."); });
    if (!CARGAS.includes(s.carga)) bad("Informe a carga de trabalho percebida.");
    if (!SENTIMENTOS.includes(s.sentimento)) bad("Informe como você se sente nesta semana.");
    if (!b.consentimento) bad("É necessário marcar o consentimento.");
    const u = dbUser(actor.email);
    u.satisfacao = { geral: +s.geral, projeto: +s.projeto, lideranca: +s.lideranca, carga: s.carga, sentimento: s.sentimento, comentario: String(s.comentario || "").slice(0, 600), data: todayISO() };
    u.risco = ["Baixo", "Médio", "Alto"].includes(b.classeIA) ? b.classeIA : calcRisco(u.satisfacao);
    u.ultimaAtualizacao = todayISO();
    logAudit(actor.email, "Registro de clima", "Bem-estar", "Conteúdo individual restrito");
    addTimeline(actor.email, "clima", "Indicador de satisfação e bem-estar atualizado.");
    const apoio = s.sentimento === "Preciso de apoio";
    if (apoio) DB.users.filter(x => x.perfil === "rh" && x.status === "Ativo").forEach(rh => queueEmail("apoio_rh", rh.email, {}));
    return { ok: true, apoio };
  },

  skills(b, actor) {
    if (b.acao === "solicitar") {
      if (actor.perfil !== "colaborador") deny();
      const cat = DB.skillsCatalog.find(x => x.id === b.skillId) || bad("Selecione uma skill do catálogo.");
      if (!LEVELS.includes(b.nivel)) bad("Selecione o nível declarado.");
      if (String(b.evidencia || "").trim().length < 10) bad("Descreva uma evidência (mínimo de 10 caracteres).");
      const exist = DB.userSkills.find(x => x.email === actor.email && x.skillId === b.skillId && !["Rejeitada", "Expirada"].includes(x.status) && lvl(x.nivel) >= lvl(b.nivel));
      if (exist) throw new ApiError("Você já possui esta skill registrada neste nível ou superior.", 409);
      const rec = { id: nextId(DB.userSkills, "US", 3), email: actor.email, skillId: b.skillId, nivel: b.nivel, evidencia: String(b.evidencia).trim(), projeto: b.projeto || "", dataSolicitacao: todayISO(), status: "Pendente", avaliacaoGestor: null, validacaoRH: null, validade: null, competenciaPDI: "", observacao: "" };
      DB.userSkills.push(rec);
      logAudit(actor.email, "Solicitou skill", `UserSkill ${rec.id}`, `${cat.nome} (${b.nivel})`);
      addTimeline(actor.email, "skill", `Solicitação da skill ${cat.nome} (${b.nivel}) registrada.`);
      return { userSkill: rec };
    }
    if (b.acao === "parecerGestor") {
      if (actor.perfil !== "gestor") deny();
      const rec = DB.userSkills.find(x => x.id === b.userSkillId) || notFound();
      if (!isTeamOf(actor, rec.email)) deny("Esta skill não pertence a um colaborador da sua equipe.");
      if (!["Pendente", "Em validação"].includes(rec.status)) bad("Parecer disponível apenas para skills pendentes ou em validação.");
      if (!["Recomendada", "Não recomendada"].includes(b.parecer)) bad("Parecer inválido.");
      rec.avaliacaoGestor = { parecer: b.parecer, por: actor.email, data: todayISO(), comentario: String(b.comentario || "") };
      rec.status = "Em validação"; // validação definitiva é sempre do RH
      logAudit(actor.email, `Parecer de skill: ${b.parecer}`, `UserSkill ${rec.id}`, rec.email);
      return { userSkill: rec };
    }
    if (actor.perfil !== "rh") deny("Somente o RH administra o catálogo de skills.");
    if (b.acao === "criarCatalogo") {
      const nome = String(b.nome || "").trim();
      if (nome.length < 2) bad("Informe o nome da skill.");
      if (DB.skillsCatalog.some(x => x.nome.toLowerCase() === nome.toLowerCase())) throw new ApiError("Já existe uma skill com este nome.", 409);
      if (!SKILL_TIPOS.includes(b.tipo)) bad("Selecione o tipo.");
      if (!DB.categorias.includes(b.categoria)) bad("Selecione a categoria.");
      const rec = { id: nextId(DB.skillsCatalog, "SK"), nome, categoria: b.categoria, tipo: b.tipo, descricao: String(b.descricao || "").trim(), estrategica: !!b.estrategica, status: SKILL_STATUS.includes(b.status) ? b.status : "Validada" };
      DB.skillsCatalog.push(rec);
      logAudit(actor.email, "Criou skill no catálogo", `Skill ${rec.id}`, rec.nome);
      return { skill: rec };
    }
    if (b.acao === "statusCatalogo") {
      const rec = DB.skillsCatalog.find(x => x.id === b.skillId) || notFound();
      if (!SKILL_STATUS.includes(b.status)) bad("Status inválido.");
      rec.status = b.status;
      logAudit(actor.email, "Alterou status do catálogo", `Skill ${rec.id}`, b.status);
      return { skill: rec };
    }
    if (b.acao === "criarCategoria") {
      const nome = String(b.nome || "").trim();
      if (nome.length < 2) bad("Informe o nome da categoria.");
      if (DB.categorias.some(c => c.toLowerCase() === nome.toLowerCase())) throw new ApiError("Categoria já existente.", 409);
      DB.categorias.push(nome);
      logAudit(actor.email, "Criou categoria", "Categoria de skill", nome);
      return { categoria: nome };
    }
    bad("Ação inválida.");
  },

  validarSkill(b, actor) {
    if (actor.perfil !== "rh") deny("Somente o RH valida skills definitivamente.");
    const rec = DB.userSkills.find(x => x.id === b.userSkillId) || notFound();
    if (rec.email === actor.email) deny("Não é permitido validar a própria skill.");
    const cat = DB.skillsCatalog.find(x => x.id === rec.skillId);
    const owner = dbUser(rec.email);
    const comentario = String(b.comentario || "").trim();
    let label;
    switch (b.acao) {
      case "validar":
        rec.status = "Validada"; rec.validacaoRH = { por: actor.email, data: todayISO(), comentario };
        rec.validade = monthsFrom(DB.configuracoes.regras.validadeSkillMeses); rec.observacao = ""; label = "Validada"; break;
      case "rejeitar":
        if (!comentario) bad("Informe o motivo da rejeição.");
        rec.status = "Rejeitada"; rec.validacaoRH = { por: actor.email, data: todayISO(), comentario }; rec.observacao = `Rejeitada: ${comentario}`; label = "Rejeitada"; break;
      case "evidencia":
        rec.status = "Pendente"; rec.observacao = `Evidência adicional solicitada pelo RH${comentario ? ": " + comentario : "."}`; label = "Evidência solicitada"; break;
      case "expirar":
        rec.status = "Expirada"; rec.validade = todayISO(); label = "Expirada"; break;
      case "ajustar":
        if (b.nivel && !LEVELS.includes(b.nivel)) bad("Nível inválido.");
        if (b.nivel) rec.nivel = b.nivel;
        rec.competenciaPDI = String(b.competenciaPDI || "").trim();
        rec.projeto = b.projeto || "";
        label = "Dados ajustados"; break;
      default: bad("Ação inválida.");
    }
    logAudit(actor.email, `Skill: ${label}`, `UserSkill ${rec.id}`, `${cat?.nome} — ${rec.email}`);
    addTimeline(rec.email, "skill", `Skill ${cat?.nome}: ${label.toLowerCase()} pelo RH.`);
    if (b.acao !== "ajustar") queueEmail("skill_status", rec.email, { nome: owner?.nome, skill: cat?.nome, decisao: label, comentario });
    return { userSkill: rec };
  },

  projetos(b, actor) {
    if (actor.perfil === "colaborador") deny("Colaboradores não podem alterar projetos.");
    if (b.acao === "salvar") {
      if (actor.perfil !== "rh") deny("Somente o RH cria e edita projetos.");
      const p = b.projeto || {};
      ["nome", "codigo", "area", "gestorEmail", "status", "inicio", "fim", "criticidade"].forEach(k => { if (!String(p[k] || "").trim()) bad(`Campo obrigatório não informado: ${k}.`); });
      if (p.fim < p.inicio) bad("A data de encerramento deve ser posterior ao início.");
      if (dbUser(p.gestorEmail)?.perfil !== "gestor") bad("O responsável precisa ter perfil de Gestor.");
      const codigo = String(p.codigo).trim().toUpperCase();
      let proj = DB.projetos.find(x => x.codigo === codigo);
      if (b.novo && proj) throw new ApiError("Já existe um projeto com este código.", 409);
      if (!b.novo && !proj) notFound();
      if (!proj) { proj = { codigo, participantes: [], skillsRequeridas: [] }; DB.projetos.push(proj); }
      Object.assign(proj, pick(p, ["nome", "area", "gestorEmail", "status", "inicio", "fim", "criticidade", "descricao"]));
      logAudit(actor.email, b.novo ? "Criou projeto" : "Editou projeto", `Projeto ${codigo}`, proj.status);
      return { projeto: proj };
    }
    const p = DB.projetos.find(x => x.codigo === b.codigo) || notFound("Projeto não encontrado.");
    const manage = canManageProjectDb(actor, p);
    switch (b.acao) {
      case "encerrar": {
        if (actor.perfil !== "rh") deny("Somente o RH encerra projetos.");
        p.status = "Concluído";
        p.participantes.forEach(e => { const u = dbUser(e); if (u && u.projetoAtual === p.codigo) { u.projetoAtual = null; if (!u.projetosAnteriores.includes(p.codigo)) u.projetosAnteriores.push(p.codigo); u.disponibilidade = "Disponível"; } });
        logAudit(actor.email, "Encerrou projeto", `Projeto ${p.codigo}`);
        return { projeto: p };
      }
      case "addParticipante": {
        if (!manage) deny("Você só pode alterar projetos sob sua responsabilidade.");
        const u = dbUser(b.email);
        if (!u || u.status !== "Ativo" || u.perfil === "rh") bad("Selecione um colaborador ativo.");
        if (actor.perfil === "gestor" && !isTeamOf(actor, u.email)) deny("Para alocar profissionais de outras equipes, solicite análise de alocação ao RH.");
        if (p.participantes.includes(u.email)) throw new ApiError("Colaborador já participa do projeto.", 409);
        p.participantes.push(u.email);
        if (!u.projetoAtual) u.projetoAtual = p.codigo;
        if (u.disponibilidade === "Disponível") u.disponibilidade = "Parcial";
        logAudit(actor.email, "Adicionou participante", `Projeto ${p.codigo}`, u.email);
        addTimeline(u.email, "projeto", `Você foi adicionado(a) ao ${p.nome}.`);
        return { projeto: p };
      }
      case "removerParticipante": {
        if (!manage) deny();
        p.participantes = p.participantes.filter(e => e !== b.email);
        const u = dbUser(b.email);
        if (u && u.projetoAtual === p.codigo) { u.projetoAtual = null; if (!u.projetosAnteriores.includes(p.codigo)) u.projetosAnteriores.push(p.codigo); u.disponibilidade = "Disponível"; }
        logAudit(actor.email, "Removeu participante", `Projeto ${p.codigo}`, b.email);
        return { projeto: p };
      }
      case "addSkill": {
        if (!manage) deny("Você só pode indicar skills em projetos sob sua responsabilidade.");
        if (!DB.skillsCatalog.some(x => x.id === b.skillId)) bad("Selecione uma skill do catálogo.");
        if (!LEVELS.includes(b.nivel)) bad("Selecione o nível mínimo.");
        const qtd = Math.max(1, Math.min(50, parseInt(b.quantidade, 10) || 1));
        const ex = p.skillsRequeridas.find(r => r.skillId === b.skillId);
        if (ex) Object.assign(ex, { nivel: b.nivel, quantidade: qtd }); else p.skillsRequeridas.push({ skillId: b.skillId, nivel: b.nivel, quantidade: qtd });
        logAudit(actor.email, "Definiu skill requerida", `Projeto ${p.codigo}`, `${b.skillId} ${b.nivel} x${qtd}`);
        return { projeto: p };
      }
      case "removerSkill": {
        if (!manage) deny();
        p.skillsRequeridas = p.skillsRequeridas.filter(r => r.skillId !== b.skillId);
        logAudit(actor.email, "Removeu skill requerida", `Projeto ${p.codigo}`, b.skillId);
        return { projeto: p };
      }
      case "solicitarAlocacao": {
        if (!manage) deny();
        const skill = DB.skillsCatalog.find(x => x.id === b.skillId);
        DB.users.filter(x => x.perfil === "rh" && x.status === "Ativo").forEach(rh => queueEmail("alocacao", rh.email, { gestor: actor.nome, projeto: p.nome, skill: skill?.nome || "—", comentario: String(b.comentario || "—") }));
        logAudit(actor.email, "Solicitou análise de alocação", `Projeto ${p.codigo}`, skill?.nome || "");
        return { ok: true };
      }
      case "solicitarCapacitacao": {
        if (!manage) deny();
        const skill = DB.skillsCatalog.find(x => x.id === b.skillId);
        logAudit(actor.email, "Solicitou capacitação", `Projeto ${p.codigo}`, skill?.nome || "");
        queueEmail("alocacao", dbUser(p.gestorEmail).email, { gestor: actor.nome, projeto: p.nome, skill: skill?.nome, comentario: "Plano de capacitação aberto pelo RH." });
        return { ok: true };
      }
      default: bad("Ação inválida.");
    }
  },

  colaboradores(b, actor) {
    if (b.acao === "conversa") {
      const u = dbUser(b.email) || notFound();
      if (!(actor.perfil === "rh" || (actor.perfil === "gestor" && isTeamOf(actor, u.email)))) deny("Colaborador fora da sua equipe.");
      logAudit(actor.email, "Solicitou conversa de acompanhamento", `Usuário ${u.email}`, b.data || "");
      addTimeline(u.email, "aprovacao", `${actor.nome} solicitou uma conversa de acompanhamento${b.data ? " para " + fmtDate(b.data) : ""}.`);
      queueEmail("conversa", u.email, { nome: u.nome, gestor: actor.nome, comentario: String(b.comentario || "") });
      return { ok: true };
    }
    if (actor.perfil !== "rh") deny("Somente o RH gerencia usuários.");
    if (b.acao === "salvar") {
      const x = b.usuario || {};
      const email = String(x.email || "").trim().toLowerCase();
      if (!EMAIL_RE.test(email)) bad("E-mail corporativo inválido.");
      ["nome", "cargo", "departamento"].forEach(k => { if (!String(x[k] || "").trim()) bad(`Campo obrigatório não informado: ${k}.`); });
      if (!PERFIS[x.perfil]) bad("Perfil inválido.");
      if (x.gestorEmail && dbUser(x.gestorEmail)?.perfil !== "gestor") bad("O gestor responsável precisa ter perfil de Gestor.");
      if (x.gestorEmail === email) bad("Um usuário não pode ser gestor de si mesmo.");
      let u = dbUser(email);
      if (b.novo && u) throw new ApiError("Já existe um usuário com este e-mail (chave primária).", 409);
      if (!b.novo && !u) notFound();
      if (u && u.email === actor.email && x.perfil !== "rh") deny("Você não pode remover seu próprio perfil de RH.");
      if (!u) { u = { email, projetoAtual: null, projetosAnteriores: [], satisfacao: null, risco: "Baixo" }; DB.users.push(u); }
      Object.assign(u, pick(x, ["nome", "cargo", "departamento", "perfil", "status", "disponibilidade"]), { gestorEmail: x.gestorEmail || null, ultimaAtualizacao: todayISO() });
      if (u.perfil === "colaborador" && !DB.pdis.some(p => p.email === u.email)) DB.pdis.push({ id: nextId(DB.pdis, "PDI-", 3), email: u.email, ciclo: "2026/2027", titulo: "PDI inicial", objetivo: "Definir metas de desenvolvimento.", status: "Em elaboração", inicio: todayISO(), fim: daysFrom(365) });
      logAudit(actor.email, b.novo ? "Criou usuário" : "Editou usuário", `Usuário ${email}`, `Perfil: ${u.perfil} · Status: ${u.status}`);
      return { usuario: u };
    }
    if (b.acao === "status") {
      const u = dbUser(b.email) || notFound();
      if (u.email === actor.email) deny("Você não pode inativar o próprio usuário.");
      u.status = u.status === "Ativo" ? "Inativo" : "Ativo";
      u.ultimaAtualizacao = todayISO();
      logAudit(actor.email, u.status === "Ativo" ? "Ativou usuário" : "Inativou usuário", `Usuário ${u.email}`);
      return { usuario: u };
    }
    bad("Ação inválida.");
  },

  mentorIA(b, actor) {
    const meta = DB.metas.find(x => x.id === b.metaId) || bad("Selecione uma meta.");
    if (meta.email !== actor.email) deny("A MentorIA só pode ser usada para suas próprias metas.");
    const dificuldade = String(b.dificuldade || "").trim();
    if (dificuldade.length < 10) bad("Descreva sua dificuldade com pelo menos 10 caracteres.");
    const vindo = b.planoIA;
    const planoPronto = vindo && typeof vindo.mensagem === "string" && Array.isArray(vindo.passos) && vindo.passos.length === 3 && vindo.passos.every(p => typeof p === "string" && p.trim()) && typeof vindo.reflexao === "string";
    const resposta = planoPronto ? { mensagem: vindo.mensagem, passos: vindo.passos, reflexao: vindo.reflexao, aviso: vindo.aviso || null, origem: vindo.origem || "" } : gerarPlanoMentoria(meta, dificuldade);
    DB.mentoria.unshift({ id: uid("MT"), email: actor.email, metaId: meta.id, data: nowISO(), dificuldade: dificuldade.slice(0, 1000), resposta });
    meta.dificuldade = dificuldade.slice(0, 300);
    addTimeline(actor.email, "mentoria", `Plano de ação da MentorIA gerado para “${meta.titulo}”.`);
    logAudit(actor.email, "Consultou MentorIA", `Meta ${meta.id}`, "Conteúdo privado");
    return resposta;
  },

  relatorios(f, actor) {
    if (actor.perfil !== "rh") deny("Relatórios globais são exclusivos do RH.");
    logAudit(actor.email, "Gerou relatório", "Relatórios", Object.entries(f).filter(([k, v]) => v && k !== "solicitante").map(([k, v]) => `${k}=${v}`).join(", ") || "sem filtros");
    return buildReport(f);
  },

  configuracoes(b, actor) {
    if (actor.perfil !== "rh") deny("Configurações globais são exclusivas do RH.");
    const r = b.regras || {};
    const n = (v, min, max, label) => { const x = parseInt(v, 10); if (!(x >= min && x <= max)) bad(`${label}: informe um valor entre ${min} e ${max}.`); return x; };
    DB.configuracoes.regras = {
      diasAlertaPrazo: n(r.diasAlertaPrazo, 1, 90, "Dias de alerta"),
      limiteRiscoAlto: n(r.limiteRiscoAlto, 2, 9, "Limite de risco alto"),
      anonimatoMinimo: n(r.anonimatoMinimo, 2, 20, "Anonimato mínimo"),
      validadeSkillMeses: n(r.validadeSkillMeses, 1, 120, "Validade das skills")
    };
    (b.templates || []).forEach(tp => { const ex = DB.configuracoes.templates.find(x => x.id === tp.id); if (ex) { ex.assunto = String(tp.assunto || "").slice(0, 200); ex.corpo = String(tp.corpo || "").slice(0, 2000); } });
    DB.users.forEach(u => { u.risco = calcRisco(u.satisfacao); });
    logAudit(actor.email, "Alterou configurações", "Configurações globais", "Regras e templates de e-mail");
    return { configuracoes: DB.configuracoes };
  },

  reconhecimento(b, actor) {
    if (!DB.premiacoes) DB.premiacoes = [];
    if (!DB.reconhecimentos) DB.reconhecimentos = [];
    const premioDe = id => DB.premiacoes.find(p => p.id === id);
    const avisar = (id, para, vars) => { if (para) queueEmail(id, para, vars); };
    if (b.acao === "premiacao") {
      if (actor.perfil !== "rh") deny("Somente o RH cadastra premiações.");
      const nome = String(b.nome || "").trim();
      if (nome.length < 3) bad("Informe o nome da premiação.");
      const financeira = b.financeira === true || b.financeira === "sim";
      const valor = financeira ? Number(String(b.valor || "").replace(",", ".")) : 0;
      if (financeira && !(valor > 0 && valor <= 100000)) bad("Informe um valor financeiro entre 0,01 e 100.000.");
      if (DB.premiacoes.some(p => p.nome.toLowerCase() === nome.toLowerCase())) throw new ApiError("Já existe uma premiação com este nome.", 409);
      const rec = { id: nextId(DB.premiacoes, "PRM", 2), nome, descricao: String(b.descricao || "").trim().slice(0, 400), financeira, valor: financeira ? Math.round(valor * 100) / 100 : 0, ativa: true };
      DB.premiacoes.push(rec);
      logAudit(actor.email, "Criou premiação", `Premiação ${rec.id}`, rec.financeira ? `Com recompensa de ${rec.valor}` : "Sem recompensa financeira");
      return { premiacao: rec };
    }
    if (b.acao === "indicar") {
      if (actor.perfil === "rh") deny("O RH administra as premiações. A indicação é feita por colaborador ou gestor.");
      const para = String(b.para || "").trim().toLowerCase();
      if (para === actor.email) bad("Não é possível indicar a si mesmo.");
      const colega = dbUser(para);
      if (!colega || colega.status !== "Ativo") bad("Escolha um colega ativo.");
      const premio = premioDe(b.premiacaoId);
      if (!premio || !premio.ativa) bad("Escolha uma premiação ativa.");
      const mensagem = String(b.mensagem || "").trim();
      if (mensagem.length < 10) bad("Conte em pelo menos 10 caracteres por que essa pessoa merece o reconhecimento.");
      const quer = premio.financeira && (b.querRecompensa === true || b.querRecompensa === "sim");
      const gestorEhQuemIndica = colega.gestorEmail && colega.gestorEmail === actor.email;
      let status = "Aguardando gestor";
      if (!colega.gestorEmail || gestorEhQuemIndica) status = quer ? "Aguardando RH" : "Reconhecido";
      const rec = {
        id: nextId(DB.reconhecimentos, "RC", 3), de: actor.email, para, premiacaoId: premio.id, mensagem: mensagem.slice(0, 500),
        financeira: quer, valorSugerido: quer ? premio.valor : 0, valorAprovado: status === "Reconhecido" ? 0 : null,
        status, criadoEm: todayISO(), atualizadoEm: todayISO(), gestorEmail: colega.gestorEmail || "", comentarioGestor: ""
      };
      DB.reconhecimentos.unshift(rec);
      logAudit(actor.email, "Indicou colega", `Reconhecimento ${rec.id}`, `${colega.email} · ${premio.nome}`);
      addTimeline(para, "reconhecimento", `${actor.nome} indicou você para “${premio.nome}”.`);
      avisar("reconhecimento_colega", para, { nome: colega.nome, de: actor.nome, premio: premio.nome });
      if (status === "Aguardando gestor" && colega.gestorEmail) avisar("reconhecimento_gestor", colega.gestorEmail, { gestor: dbUser(colega.gestorEmail)?.nome || "", de: actor.nome, nome: colega.nome, premio: premio.nome, financeira: quer ? `sim, R$ ${premio.valor}` : "não" });
      if (status === "Aguardando RH") DB.users.filter(x => x.perfil === "rh" && x.status === "Ativo").forEach(rh => avisar("reconhecimento_rh", rh.email, { nome: colega.nome, premio: premio.nome, valor: `R$ ${premio.valor}` }));
      return { reconhecimento: rec };
    }
    if (b.acao === "decidir") {
      const rec = DB.reconhecimentos.find(x => x.id === b.id) || notFound();
      const premio = premioDe(rec.premiacaoId) || { nome: "Premiação", valor: rec.valorSugerido || 0 };
      const comentario = String(b.comentario || "").trim().slice(0, 400);
      const indicado = dbUser(rec.para);
      const indicador = dbUser(rec.de);
      if (!["aprovar", "recusar"].includes(b.decisao)) bad("Decisão inválida.");
      if (b.decisao === "recusar" && !comentario) bad("Informe o motivo da recusa.");
      if (actor.perfil === "gestor") {
        if (rec.gestorEmail !== actor.email) deny("Esta indicação não é de um colega da sua equipe.");
        if (rec.status !== "Aguardando gestor") bad("Esta indicação não está aguardando o gestor.");
        rec.status = b.decisao === "recusar" ? "Recusado" : (rec.financeira ? "Aguardando RH" : "Reconhecido");
        if (rec.status === "Reconhecido") rec.valorAprovado = 0;
      } else if (actor.perfil === "rh") {
        if (!rec.financeira) deny("Reconhecimento sem valor financeiro é confirmado pelo gestor.");
        if (rec.status !== "Aguardando RH") bad("Esta indicação não está aguardando o RH.");
        rec.status = b.decisao === "aprovar" ? "Premiado" : "Recusado";
        rec.valorAprovado = rec.status === "Premiado" ? (premio.valor || rec.valorSugerido || 0) : 0;
      } else deny("Colaboradores não decidem indicações.");
      rec.comentarioGestor = comentario;
      rec.decididoPor = actor.email;
      rec.atualizadoEm = todayISO();
      logAudit(actor.email, rec.status, `Reconhecimento ${rec.id}`, indicado?.email || rec.para);
      const vars = { nome: indicado?.nome || rec.para, premio: premio.nome, decisao: rec.status, comentario: comentario || (rec.status === "Premiado" ? `Recompensa autorizada: R$ ${rec.valorAprovado}.` : "—") };
      if (rec.status === "Aguardando RH") DB.users.filter(x => x.perfil === "rh" && x.status === "Ativo").forEach(rh => avisar("reconhecimento_rh", rh.email, { nome: indicado?.nome || rec.para, premio: premio.nome, valor: `R$ ${rec.valorSugerido || premio.valor || 0}` }));
      else {
        avisar("reconhecimento_resultado", rec.para, vars);
        if (indicador) avisar("reconhecimento_resultado", rec.de, { ...vars, nome: indicador.nome });
      }
      return { reconhecimento: rec };
    }
    bad("Ação inválida.");
  }
};


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
