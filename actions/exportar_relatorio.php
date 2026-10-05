<?php

declare(strict_types=1);

require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/admin_apoio.php';

exigirPost();
exigirPerfil('administrador');
$tipo = (string) ($_POST['tipo'] ?? '');
exigirCsrf('relatorios.php');

$linhas = match ($tipo) {
    'pdis' => linhasPdis(),
    'indicadores' => linhasIndicadores(),
    'usuarios' => linhasUsuarios(),
    'projetos' => linhasProjetos(),
    default => null,
};

if ($linhas === null) {
    flash('erro', 'Escolha um relatório válido.');
    redirect('relatorios.php');
}

registrarLog('exportar_csv', 'Relatorios', $tipo, count($linhas) . ' linha(s), sem bem-estar individual.');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="pdi-' . $tipo . '-' . date('Ymd') . '.csv"');
header('Cache-Control: no-store');
echo "\xEF\xBB\xBF";

$saida = fopen('php://output', 'wb');
foreach ($linhas as $linha) {
    fputcsv($saida, array_map('celulaCsv', $linha), ';');
}
fclose($saida);
exit;

function linhasPdis(): array
{
    $linhas = [['id', 'email', 'nome', 'competencia', 'meta', 'percentual', 'status', 'status_efetivo', 'prazo', 'gestor', 'ativo']];
    foreach (db()->todos('PDIs') as $pdi) {
        $linhas[] = [
            $pdi['id_pdi'],
            $pdi['email_colaborador'],
            nomeUsuario($pdi['email_colaborador']),
            $pdi['competencia'],
            $pdi['meta'],
            $pdi['percentual_conclusao'],
            $pdi['status'],
            statusEfetivo($pdi),
            substr((string) $pdi['prazo'], 0, 10),
            $pdi['gestor_email'],
            boolValor($pdi['ativo']) ? 'sim' : 'nao',
        ];
    }

    return $linhas;
}

function linhasIndicadores(): array
{
    $linhas = [['email', 'nome', 'area', 'cargo', 'gestor', 'pdis', 'progresso_medio', 'status_geral', 'classificacao_risco', 'score_risco', 'ultima_atualizacao']];
    foreach (usuariosAtivos() as $pessoa) {
        if ($pessoa['perfil'] === 'administrador') {
            continue;
        }
        $resumo = resumoColaborador($pessoa);
        $linhas[] = [
            $pessoa['email'],
            $pessoa['nome_completo'],
            $pessoa['area'],
            $pessoa['cargo'],
            $pessoa['gestor_email'],
            $resumo['total'],
            $resumo['progresso_medio'] === null ? '' : (string) round($resumo['progresso_medio'], 1),
            $resumo['status_geral'] ?? '',
            $resumo['risco']['classificacao'],
            $resumo['risco']['score'],
            substr((string) ($resumo['ultima_atualizacao'] ?? ''), 0, 10),
        ];
    }

    return $linhas;
}

function linhasUsuarios(): array
{
    $linhas = [['email', 'nome', 'perfil', 'cargo', 'area', 'gestor', 'ativo']];
    foreach (db()->todos('Usuarios') as $pessoa) {
        $linhas[] = [
            $pessoa['email'],
            $pessoa['nome_completo'],
            $pessoa['perfil'],
            $pessoa['cargo'],
            $pessoa['area'],
            $pessoa['gestor_email'],
            boolValor($pessoa['ativo']) ? 'sim' : 'nao',
        ];
    }

    return $linhas;
}

function linhasProjetos(): array
{
    $linhas = [['id', 'nome', 'area', 'gestor', 'status', 'inicio', 'fim', 'participacoes_ativas']];
    $vinculos = db()->todos('Colaborador_Projetos');
    foreach (db()->todos('Projetos') as $projeto) {
        $ativos = 0;
        foreach ($vinculos as $vinculo) {
            if ($vinculo['id_projeto'] === $projeto['id_projeto'] && mb_strtolower($vinculo['status_participacao']) === 'ativo') {
                $ativos++;
            }
        }
        $linhas[] = [
            $projeto['id_projeto'],
            $projeto['nome_projeto'],
            $projeto['area_responsavel'],
            $projeto['gestor_email'],
            $projeto['status'],
            substr((string) $projeto['data_inicio'], 0, 10),
            substr((string) $projeto['data_fim'], 0, 10),
            $ativos,
        ];
    }

    return $linhas;
}
