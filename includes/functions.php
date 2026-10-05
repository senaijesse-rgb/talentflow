<?php

declare(strict_types=1);

const STATUS_PDI = ['Em andamento', 'Atenção', 'Atrasado', 'Aguardando validação', 'Concluído'];

const ESCALA_SATISFACAO = [1 => 'Muito insatisfeito', 2 => 'Insatisfeito', 3 => 'Neutro', 4 => 'Satisfeito', 5 => 'Muito satisfeito'];
const ESCALA_RISCO_SAIDA = [1 => 'Muito baixo', 2 => 'Baixo', 3 => 'Moderado', 4 => 'Alto', 5 => 'Muito alto'];
const ESCALA_BEM_ESTAR = [1 => 'Muito baixo', 2 => 'Baixo', 3 => 'Regular', 4 => 'Bom', 5 => 'Muito bom'];

const AVISO_BEM_ESTAR = 'Este campo mede exclusivamente sua percepção de bem-estar no ambiente de trabalho. Não informe diagnósticos, condições médicas ou informações clínicas.';
const AVISO_CONFIDENCIALIDADE = 'Informações confidenciais. O acesso é restrito, registrado em log e destinado exclusivamente à gestão de pessoas e desenvolvimento profissional.';

/* ---------------------------------------------------------------
 * Utilidades gerais
 * ------------------------------------------------------------- */

function e(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $caminho = ''): string
{
    return BASE_PATH . '/' . ltrim($caminho, '/');
}

function redirect(string $caminho): never
{
    header('Location: ' . url($caminho));
    exit;
}

function requisicaoApi(): bool
{
    return str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/');
}

function responderJson(array $dados, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function exigirPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Método não permitido.');
    }
}

function flash(string $tipo, string $mensagem): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

function obterFlashes(): array
{
    $mensagens = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $mensagens;
}

function db(): GoogleSheetsService
{
    return GoogleSheetsService::instancia();
}

function normalizarEmail(?string $email): string
{
    return mb_strtolower(trim((string) $email));
}

function emailValido(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && mb_strlen($email) <= 254;
}

function boolValor(mixed $valor): bool
{
    return in_array(mb_strtolower(trim((string) $valor)), ['1', 'sim', 's', 'true', 'yes', 'ativo', 'verdadeiro'], true);
}

function gerarId(string $prefixo): string
{
    return strtoupper($prefixo) . '-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function agoraIso(): string
{
    return (new DateTimeImmutable())->format(DATE_ATOM);
}

function ipCliente(): string
{
    if (env('TRUST_PROXY') === 'true' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }

    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

function paraData(?string $valor): ?DateTimeImmutable
{
    if ($valor === null || trim($valor) === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($valor);
    } catch (Exception) {
        return null;
    }
}

function timestamp(?string $valor): int
{
    return paraData($valor)?->getTimestamp() ?? 0;
}

function formatarData(?string $valor, bool $comHora = false): string
{
    $data = paraData($valor);

    return $data ? $data->format($comHora ? 'd/m/Y H:i' : 'd/m/Y') : '—';
}

function dataPorExtenso(?DateTimeInterface $data = null): string
{
    $data ??= new DateTimeImmutable();
    $dias = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    $meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    return $dias[(int) $data->format('w')] . ', ' . $data->format('j') . ' de ' . $meses[(int) $data->format('n')] . ' de ' . $data->format('Y');
}

/** Dias decorridos desde a data (negativo quando a data está no futuro). */
function diasDesde(?string $valor): ?int
{
    $data = paraData($valor);
    if ($data === null) {
        return null;
    }

    $diferenca = (new DateTimeImmutable('today'))->diff($data->setTime(0, 0));

    return $diferenca->invert ? (int) $diferenca->days : -(int) $diferenca->days;
}

function diasAte(?string $valor): ?int
{
    $dias = diasDesde($valor);

    return $dias === null ? null : -$dias;
}

function textoRelativoDias(?int $dias): string
{
    return match (true) {
        $dias === null => '',
        $dias === 0 => 'hoje',
        $dias === 1 => 'há 1 dia',
        $dias > 1 => "há {$dias} dias",
        $dias === -1 => 'em 1 dia',
        default => 'em ' . abs($dias) . ' dias',
    };
}

function dataValida(string $valor): bool
{
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

    return $data !== false && $data->format('Y-m-d') === $valor;
}

function textoLimpo(?string $valor, int $limite = 1000): string
{
    $texto = trim(strip_tags((string) $valor));
    $texto = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto);

    return mb_substr($texto, 0, $limite);
}

function inteiroEntre(mixed $valor, int $minimo, int $maximo): ?int
{
    $numero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimo, 'max_range' => $maximo]]);

    return $numero === false ? null : $numero;
}

function resumirTexto(string $texto, int $limite = 60): string
{
    return mb_strimwidth($texto, 0, $limite, '…');
}

function primeiroNome(string $nome): string
{
    return explode(' ', trim($nome))[0] ?: $nome;
}

function iniciais(string $nome): string
{
    $partes = array_values(array_filter(explode(' ', trim($nome))));
    $letras = mb_substr($partes[0] ?? '?', 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '');

    return mb_strtoupper($letras);
}

function media(array $valores): ?float
{
    $valores = array_filter($valores, static fn ($v) => is_numeric($v));

    return $valores ? array_sum($valores) / count($valores) : null;
}

function formatarNumero(?float $valor, int $casas = 1): string
{
    return $valor === null ? '—' : number_format($valor, $casas, ',', '.');
}

function paginaDisponivel(string $arquivo): bool
{
    return is_file(ROOT_PATH . '/' . ltrim(explode('?', $arquivo)[0], '/'));
}

/* ---------------------------------------------------------------
 * Regras configuráveis (aba Regras)
 * ------------------------------------------------------------- */

function regras(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->todos('Regras') as $linha) {
            if (boolValor($linha['ativo'] ?? 'sim')) {
                $cache[$linha['chave']] = $linha['valor'];
            }
        }
    }

    return $cache;
}

function regra(string $chave, mixed $padrao = null): mixed
{
    $valor = regras()[$chave] ?? null;

    return ($valor === null || $valor === '') ? $padrao : $valor;
}

/* ---------------------------------------------------------------
 * Consultas de domínio
 * ------------------------------------------------------------- */

function buscarUsuario(string $email): ?array
{
    $email = normalizarEmail($email);

    return $email === '' ? null : db()->buscarUm('Usuarios', 'email', $email);
}

function usuariosAtivos(): array
{
    return array_values(array_filter(db()->todos('Usuarios'), static fn ($u) => boolValor($u['ativo'])));
}

function equipeDoGestor(string $emailGestor): array
{
    $emailGestor = normalizarEmail($emailGestor);

    return array_values(array_filter(
        usuariosAtivos(),
        static fn ($u) => normalizarEmail($u['gestor_email']) === $emailGestor && normalizarEmail($u['email']) !== $emailGestor
    ));
}

function nomeUsuario(string $email): string
{
    return buscarUsuario($email)['nome_completo'] ?? ($email !== '' ? $email : 'Não definido');
}

function pdisDoColaborador(string $email, bool $apenasAtivos = true): array
{
    $pdis = db()->buscar('PDIs', 'email_colaborador', $email);
    if ($apenasAtivos) {
        $pdis = array_filter($pdis, static fn ($p) => boolValor($p['ativo']));
    }

    return array_values($pdis);
}

function buscarPdi(string $idPdi): ?array
{
    return $idPdi === '' ? null : db()->buscarUm('PDIs', 'id_pdi', $idPdi);
}

/** Status considerando prazo vencido, mesmo que a planilha não tenha sido atualizada. */
function statusEfetivo(array $pdi): string
{
    $status = in_array($pdi['status'] ?? '', STATUS_PDI, true) ? $pdi['status'] : 'Em andamento';

    if (!in_array($status, ['Concluído', 'Aguardando validação'], true) && (diasAte($pdi['prazo'] ?? '') ?? 0) < 0) {
        return 'Atrasado';
    }

    return $status;
}

function projetosDoColaborador(string $email): array
{
    $projetos = array_column(db()->todos('Projetos'), null, 'id_projeto');
    $resultado = ['atuais' => [], 'anteriores' => []];

    foreach (db()->buscar('Colaborador_Projetos', 'email_colaborador', $email) as $vinculo) {
        $projeto = $projetos[$vinculo['id_projeto']] ?? null;
        if ($projeto === null) {
            continue;
        }

        $item = $projeto + [
            'papel_no_projeto' => $vinculo['papel_no_projeto'],
            'vinculo_inicio' => $vinculo['data_inicio'],
            'vinculo_fim' => $vinculo['data_fim'],
            'status_participacao' => $vinculo['status_participacao'],
        ];

        $atual = mb_strtolower($vinculo['status_participacao']) === 'ativo'
            && ($vinculo['data_fim'] === '' || (diasAte($vinculo['data_fim']) ?? 0) >= 0);

        $resultado[$atual ? 'atuais' : 'anteriores'][] = $item;
    }

    return $resultado;
}

/** Projetos oferecidos nos formulários: os atuais do colaborador ou, na falta, todos os ativos. */
function projetosParaFormulario(string $email): array
{
    $atuais = projetosDoColaborador($email)['atuais'];
    if ($atuais) {
        return $atuais;
    }

    return array_values(array_filter(db()->todos('Projetos'), static fn ($p) => mb_strtolower($p['status']) === 'ativo'));
}

function ordenarPorDataDesc(array $linhas, string $coluna): array
{
    usort($linhas, static fn ($a, $b) => timestamp($b[$coluna] ?? '') <=> timestamp($a[$coluna] ?? ''));

    return $linhas;
}

function atualizacoesDoColaborador(string $email): array
{
    return ordenarPorDataDesc(db()->buscar('Atualizacoes_PDI', 'email_colaborador', $email), 'data_atualizacao');
}

function checkinsDoColaborador(string $email): array
{
    return ordenarPorDataDesc(db()->buscar('Checkins_Clima', 'email_colaborador', $email), 'data_checkin');
}

function comentariosDoColaborador(string $email, bool $apenasVisiveis = false): array
{
    $comentarios = db()->buscar('Comentarios_Gestor', 'email_colaborador', $email);
    if ($apenasVisiveis) {
        $comentarios = array_filter($comentarios, static fn ($c) => boolValor($c['visivel_colaborador']));
    }

    return ordenarPorDataDesc(array_values($comentarios), 'data_comentario');
}

/** Consolida PDIs, projetos, check-ins e risco de um colaborador. */
function resumoColaborador(array $usuario): array
{
    $email = normalizarEmail($usuario['email'] ?? '');
    $pdis = array_map(static fn ($p) => $p + ['status_efetivo' => statusEfetivo($p)], pdisDoColaborador($email));
    $abertos = array_values(array_filter($pdis, static fn ($p) => $p['status_efetivo'] !== 'Concluído'));

    $progresso = media(array_map(static fn ($p) => (float) $p['percentual_conclusao'], $pdis));

    $comPrazo = array_values(array_filter($abertos, static fn ($p) => paraData($p['prazo']) !== null));
    usort($comPrazo, static fn ($a, $b) => timestamp($a['prazo']) <=> timestamp($b['prazo']));

    $ultimaAtualizacao = null;
    foreach ($abertos as $pdi) {
        $data = $pdi['data_ultima_atualizacao'] ?: $pdi['data_inicio'];
        if ($data !== '' && timestamp($data) > timestamp($ultimaAtualizacao)) {
            $ultimaAtualizacao = $data;
        }
    }
    $diasSemAtualizacao = $ultimaAtualizacao ? diasDesde($ultimaAtualizacao) : null;

    $prioridade = ['Atrasado' => 5, 'Atenção' => 4, 'Aguardando validação' => 3, 'Em andamento' => 2, 'Concluído' => 1];
    $statusGeral = null;
    foreach ($pdis as $pdi) {
        if ($statusGeral === null || $prioridade[$pdi['status_efetivo']] > $prioridade[$statusGeral]) {
            $statusGeral = $pdi['status_efetivo'];
        }
    }

    $diasRecente = (int) regra('dias_meta_concluida_recente', 30);
    $metaConcluidaRecente = false;
    foreach ($pdis as $pdi) {
        $dias = diasDesde($pdi['data_conclusao']);
        if ($pdi['status_efetivo'] === 'Concluído' && $dias !== null && $dias <= $diasRecente) {
            $metaConcluidaRecente = true;
        }
    }

    $checkins = checkinsDoColaborador($email);
    $ultimoCheckin = $checkins[0] ?? null;
    $numeroOuNulo = static fn (?array $c, string $campo): ?int => ($c && $c[$campo] !== '') ? (int) $c[$campo] : null;

    $risco = calcularScoreRisco([
        'risco_saida_percebido' => $numeroOuNulo($ultimoCheckin, 'risco_saida_percebido'),
        'satisfacao_empresa' => $numeroOuNulo($ultimoCheckin, 'satisfacao_empresa'),
        'bem_estar_trabalho' => $numeroOuNulo($ultimoCheckin, 'bem_estar_trabalho'),
        'pdi_atrasado' => in_array('Atrasado', array_column($pdis, 'status_efetivo'), true),
        'dias_sem_atualizacao' => $diasSemAtualizacao,
        'meta_concluida_recente' => $metaConcluidaRecente,
        'percentual_medio' => $progresso,
    ]);

    return [
        'email' => $email,
        'pdis' => $pdis,
        'abertos' => $abertos,
        'total' => count($pdis),
        'concluidas' => count($pdis) - count($abertos),
        'progresso_medio' => $progresso,
        'proximo_prazo' => $comPrazo[0] ?? null,
        'ultima_atualizacao' => $ultimaAtualizacao,
        'dias_sem_atualizacao' => $diasSemAtualizacao,
        'status_geral' => $statusGeral,
        'projetos' => projetosDoColaborador($email),
        'ultimo_checkin' => $ultimoCheckin,
        'risco' => $risco,
    ];
}

/**
 * Média agregada de um campo do check-in (última resposta de cada pessoa na janela).
 * Retorna média nula quando há menos respondentes que o mínimo configurado, preservando o anonimato.
 */
function agregadoCheckin(array $emails, string $campo, int $inicioDias = 0, int $fimDias = 30, ?int $minimoRespostas = null): array
{
    $minimo = max(1, $minimoRespostas ?? (int) regra('minimo_respostas_agregado', 3));
    $alvo = array_flip(array_map('normalizarEmail', $emails));
    $porPessoa = [];

    foreach (db()->todos('Checkins_Clima') as $checkin) {
        $email = normalizarEmail($checkin['email_colaborador']);
        $dias = diasDesde($checkin['data_checkin']);
        if (!isset($alvo[$email]) || $checkin[$campo] === '' || $dias === null || $dias < $inicioDias || $dias > $fimDias) {
            continue;
        }
        if (!isset($porPessoa[$email]) || timestamp($checkin['data_checkin']) > timestamp($porPessoa[$email]['data_checkin'])) {
            $porPessoa[$email] = $checkin;
        }
    }

    $valores = array_map(static fn ($c) => (int) $c[$campo], array_values($porPessoa));
    $suficiente = count($valores) >= $minimo;

    return [
        'respondentes' => count($valores),
        'minimo' => $minimo,
        'suficiente' => $suficiente,
        'media' => $suficiente ? media($valores) : null,
        'alertas' => $suficiente ? count(array_filter($valores, static fn ($v) => $v <= 2)) : null,
    ];
}

function mensagemIncentivo(array $resumo): string
{
    $progresso = $resumo['progresso_medio'];
    $limiteDias = (int) regra('dias_sem_atualizacao', 14);

    return match (true) {
        $resumo['total'] === 0 => 'Converse com seu gestor para definir as primeiras metas do seu PDI. Todo grande desenvolvimento começa com um objetivo claro.',
        $resumo['status_geral'] === 'Atrasado' => 'Algumas metas passaram do prazo. Registre o que já avançou e alinhe os próximos passos com seu gestor — replanejar também é evoluir.',
        ($resumo['dias_sem_atualizacao'] ?? 0) > $limiteDias => 'Faz um tempo desde sua última atualização. Pequenos avanços também contam: que tal registrá-los hoje?',
        $progresso !== null && $progresso >= 80 => 'Excelente ritmo! Você está muito perto de concluir suas metas de desenvolvimento.',
        $progresso !== null && $progresso >= 50 => 'Bom trabalho! Mais da metade do caminho já foi percorrida. Mantenha a constância.',
        default => 'Cada passo conta. Defina uma pequena ação para esta semana e registre seu progresso.',
    };
}

function lembretesColaborador(array $resumo): array
{
    $lembretes = [];
    $diasPrazo = (int) regra('dias_prazo_proximo', 7);
    $limitePercentual = (float) regra('limite_atencao_percentual', 50);
    $limiteDias = (int) regra('dias_sem_atualizacao', 14);

    foreach ($resumo['abertos'] as $pdi) {
        $meta = resumirTexto($pdi['meta'], 70);
        $dias = diasAte($pdi['prazo']);

        if ($pdi['status_efetivo'] === 'Atrasado') {
            $lembretes[] = ['tom' => 'vermelho', 'texto' => "Meta atrasada: {$meta}. Plano de ação: registre o progresso e combine um novo prazo com seu gestor."];
        } elseif ($dias !== null && $dias >= 0 && $dias <= $diasPrazo) {
            $lembretes[] = ['tom' => 'amarelo', 'texto' => "Prazo próximo: {$meta} vence " . textoRelativoDias(-$dias) . '.'];
        }

        if ($pdi['status_efetivo'] === 'Aguardando validação') {
            $lembretes[] = ['tom' => 'azul', 'texto' => "Aguardando validação do gestor: {$meta}."];
        } elseif ((float) $pdi['percentual_conclusao'] < $limitePercentual) {
            $lembretes[] = ['tom' => 'amarelo', 'texto' => "Meta abaixo de {$limitePercentual}%: {$meta}. Plano de ação: defina uma entrega concreta para os próximos 7 dias."];
        }
    }

    if (($resumo['dias_sem_atualizacao'] ?? 0) > $limiteDias) {
        $lembretes[] = ['tom' => 'amarelo', 'texto' => 'Seu PDI não é atualizado ' . textoRelativoDias($resumo['dias_sem_atualizacao']) . '. Registre seus avanços.'];
    }

    $ultimoCheckin = $resumo['ultimo_checkin'];
    if ($ultimoCheckin === null || (diasDesde($ultimoCheckin['data_checkin']) ?? 999) > 30) {
        $lembretes[] = ['tom' => 'azul', 'texto' => 'Faça seu Check-in de Experiência do mês. Leva menos de 2 minutos.'];
    }

    return $lembretes;
}

/** Linha do tempo sem dados individuais de check-in (somente o registro do envio). */
function atividadesRecentes(string $email, int $limite = 8, bool $incluirComentariosOcultos = false): array
{
    $metas = array_column(pdisDoColaborador($email, false), 'meta', 'id_pdi');
    $itens = [];

    foreach (atualizacoesDoColaborador($email) as $a) {
        $itens[] = [
            'data' => $a['data_atualizacao'],
            'icone' => 'tendencia',
            'titulo' => 'Atualização de PDI',
            'texto' => "Progresso de {$a['percentual_anterior']}% para {$a['percentual_novo']}% · {$a['status_novo']}",
            'detalhe' => $metas[$a['id_pdi']] ?? '',
        ];
    }

    foreach (comentariosDoColaborador($email, !$incluirComentariosOcultos) as $c) {
        $itens[] = [
            'data' => $c['data_comentario'],
            'icone' => 'chat',
            'titulo' => 'Comentário de ' . nomeUsuario($c['gestor_email']),
            'texto' => $c['comentario'],
            'detalhe' => $c['tipo_comentario'],
        ];
    }

    foreach (checkinsDoColaborador($email) as $c) {
        $itens[] = [
            'data' => $c['data_checkin'],
            'icone' => 'sorriso',
            'titulo' => 'Check-in de experiência enviado',
            'texto' => 'Respostas registradas de forma confidencial.',
            'detalhe' => '',
        ];
    }

    return array_slice(ordenarPorDataDesc($itens, 'data'), 0, $limite);
}

/* ---------------------------------------------------------------
 * Componentes visuais
 * ------------------------------------------------------------- */

function icone(string $nome, string $classes = 'h-5 w-5'): string
{
    static $caminhos = [
        'home' => 'm2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
        'usuario' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
        'equipe' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
        'grafico' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
        'check' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'relogio' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'alerta' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
        'info' => 'm11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z',
        'cadeado' => 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z',
        'escudo' => 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
        'pasta' => 'M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z',
        'calendario' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
        'sorriso' => 'M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z',
        'prancheta' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z',
        'chat' => 'M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z',
        'ajustes' => 'M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75',
        'download' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
        'sair' => 'M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9',
        'menu' => 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5',
        'fechar' => 'M6 18 18 6M6 6l12 12',
        'tendencia' => 'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941',
        'sino' => 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
        'editar' => 'm16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10',
        'bandeira' => 'M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5',
        'olho' => 'M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    ];

    $d = $caminhos[$nome] ?? $caminhos['info'];

    return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="' . e($classes) . '" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="' . $d . '"/></svg>';
}

function logoPdiConnect(string $classes = 'h-10 w-10'): string
{
    return '<span class="inline-flex ' . e($classes) . ' items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 text-white shadow-md shadow-blue-900/30">'
        . icone('tendencia', 'h-3/5 w-3/5') . '</span>';
}

function badgeStatus(?string $status): string
{
    $cores = [
        'Em andamento' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'Atenção' => 'bg-amber-50 text-amber-800 ring-amber-600/30',
        'Atrasado' => 'bg-red-50 text-red-700 ring-red-600/20',
        'Aguardando validação' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        'Concluído' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    ];

    if ($status === null || $status === '') {
        return '<span class="text-sm text-slate-400">Sem PDI</span>';
    }

    $classe = $cores[$status] ?? 'bg-slate-100 text-slate-700 ring-slate-500/20';

    return '<span class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ' . $classe . '">' . e($status) . '</span>';
}

function badgeRisco(string $classificacao): string
{
    $cores = [
        'BAIXO' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'MEDIO' => 'bg-amber-50 text-amber-800 ring-amber-600/30',
        'ALTO' => 'bg-red-50 text-red-700 ring-red-600/20',
        'CRITICO' => 'bg-red-600 text-white ring-red-700',
    ];
    $rotulo = RiskService::ROTULOS[$classificacao] ?? 'Sem dados';

    return '<span class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ' . ($cores[$classificacao] ?? 'bg-slate-100 text-slate-600 ring-slate-400/20') . '">' . e($rotulo) . '</span>';
}

function barraProgresso(float $percentual): string
{
    $percentual = max(0.0, min(100.0, $percentual));
    $cor = match (true) {
        $percentual >= 100 => 'bg-emerald-500',
        $percentual < (float) regra('limite_atencao_percentual', 50) => 'bg-amber-500',
        default => 'bg-blue-600',
    };
    $inteiro = (int) round($percentual);

    return '<div class="flex items-center gap-3">'
        . '<div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="' . $inteiro . '" aria-valuemin="0" aria-valuemax="100" aria-label="Progresso">'
        . '<div class="h-full rounded-full ' . $cor . ' transition-all" style="width: ' . $inteiro . '%"></div></div>'
        . '<span class="w-11 text-right text-sm font-semibold tabular-nums text-slate-700">' . $inteiro . '%</span></div>';
}

function cardKpi(string $titulo, string $valor, string $legenda = '', string $icone = 'grafico', string $tom = 'azul'): string
{
    $tons = [
        'azul' => 'bg-blue-50 text-blue-700',
        'marinho' => 'bg-marinho-50 text-marinho-800',
        'verde' => 'bg-emerald-50 text-emerald-700',
        'amarelo' => 'bg-amber-50 text-amber-700',
        'vermelho' => 'bg-red-50 text-red-700',
        'cinza' => 'bg-slate-100 text-slate-600',
    ];

    return '<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">'
        . '<div class="flex items-start justify-between gap-3"><div class="min-w-0">'
        . '<p class="text-sm font-medium text-slate-500">' . e($titulo) . '</p>'
        . '<p class="mt-2 truncate text-2xl font-semibold text-marinho-900">' . e($valor) . '</p>'
        . ($legenda !== '' ? '<p class="mt-1 line-clamp-2 text-xs text-slate-500">' . e($legenda) . '</p>' : '')
        . '</div><span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ' . ($tons[$tom] ?? $tons['azul']) . '">' . icone($icone) . '</span>'
        . '</div></div>';
}

function estadoVazio(string $titulo, string $texto, string $icone = 'pasta'): string
{
    return '<div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50/60 px-6 py-10 text-center">'
        . '<span class="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-full bg-white text-slate-400 shadow-sm">' . icone($icone, 'h-6 w-6') . '</span>'
        . '<p class="font-medium text-slate-700">' . e($titulo) . '</p>'
        . '<p class="mt-1 max-w-sm text-sm text-slate-500">' . e($texto) . '</p></div>';
}

function avisoConfidencialidade(string $texto = AVISO_CONFIDENCIALIDADE): string
{
    return '<div class="flex gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900" role="note">'
        . icone('escudo', 'h-5 w-5 shrink-0 text-blue-700') . '<p>' . e($texto) . '</p></div>';
}

function renderizarFlashes(): string
{
    $estilos = [
        'sucesso' => ['border-emerald-200 bg-emerald-50 text-emerald-900', 'check', 'text-emerald-600'],
        'erro' => ['border-red-200 bg-red-50 text-red-900', 'alerta', 'text-red-600'],
        'aviso' => ['border-amber-200 bg-amber-50 text-amber-900', 'info', 'text-amber-600'],
        'info' => ['border-blue-200 bg-blue-50 text-blue-900', 'info', 'text-blue-600'],
    ];

    $html = '';
    foreach (obterFlashes() as $flash) {
        [$classe, $icone, $corIcone] = $estilos[$flash['tipo']] ?? $estilos['info'];
        $html .= '<div class="mb-4 flex items-start gap-3 rounded-xl border p-4 text-sm ' . $classe . '" role="' . ($flash['tipo'] === 'erro' ? 'alert' : 'status') . '" data-flash>'
            . icone($icone, 'h-5 w-5 shrink-0 ' . $corIcone)
            . '<p class="flex-1">' . e($flash['mensagem']) . '</p>'
            . '<button type="button" class="rounded p-0.5 opacity-60 hover:opacity-100" data-flash-close aria-label="Fechar mensagem">' . icone('fechar', 'h-4 w-4') . '</button></div>';
    }

    return $html;
}

function renderizarPaginaErro(int $codigo, string $titulo, string $mensagem): never
{
    if (!headers_sent()) {
        http_response_code($codigo);
    }

    $voltar = e(url(usuarioAtual() ? 'dashboard.php' : 'login.php'));
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($titulo) . ' · ' . APP_NAME . '</title><script src="https://cdn.tailwindcss.com"></script></head>'
        . '<body class="flex min-h-screen items-center justify-center bg-slate-50 p-6 text-slate-800">'
        . '<main class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">'
        . '<p class="text-sm font-semibold text-blue-700">Erro ' . $codigo . '</p>'
        . '<h1 class="mt-2 text-2xl font-semibold text-slate-900">' . e($titulo) . '</h1>'
        . '<p class="mt-3 text-sm text-slate-600">' . e($mensagem) . '</p>'
        . '<a href="' . $voltar . '" class="mt-6 inline-flex rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Voltar</a>'
        . '</main></body></html>';
    exit;
}
