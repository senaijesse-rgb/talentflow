<?php

declare(strict_types=1);

/**
 * Acesso às abas da planilha como tabelas (primeira linha = cabeçalho).
 *
 * Modos:
 *  - "sheets": Google Sheets API via conta de serviço (google/apiclient).
 *  - "mock":   arquivo JSON local com a mesma estrutura, para desenvolvimento sem credenciais.
 *
 * Todas as escritas usam valueInputOption=RAW, de modo que textos iniciados por "=", "+", "-" ou "@"
 * são gravados literalmente e nunca interpretados como fórmulas.
 */
final class GoogleSheetsService
{
    public const SCHEMA = [
        'Usuarios' => ['id_usuario', 'email', 'nome_completo', 'senha_hash', 'perfil', 'cargo', 'area', 'gestor_email', 'ativo', 'data_criacao', 'ultimo_login'],
        'Projetos' => ['id_projeto', 'nome_projeto', 'descricao', 'area_responsavel', 'gestor_email', 'status', 'data_inicio', 'data_fim'],
        'Colaborador_Projetos' => ['id_vinculo', 'email_colaborador', 'id_projeto', 'papel_no_projeto', 'data_inicio', 'data_fim', 'status_participacao'],
        'PDIs' => ['id_pdi', 'email_colaborador', 'competencia', 'meta', 'percentual_conclusao', 'status', 'prazo', 'data_inicio', 'data_ultima_atualizacao', 'data_conclusao', 'gestor_email', 'ativo'],
        'Atualizacoes_PDI' => ['id_atualizacao', 'id_pdi', 'email_colaborador', 'percentual_anterior', 'percentual_novo', 'status_anterior', 'status_novo', 'dificuldade', 'data_atualizacao', 'origem', 'registrado_por'],
        'Checkins_Clima' => ['id_checkin', 'email_colaborador', 'id_projeto', 'satisfacao_empresa', 'risco_saida_percebido', 'bem_estar_trabalho', 'comentario', 'consentimento_confirmado', 'data_checkin', 'score_risco', 'classificacao_risco'],
        'Regras' => ['chave', 'valor', 'descricao', 'ativo', 'atualizado_em', 'atualizado_por'],
        'Logs_Auditoria' => ['id_log', 'data_hora', 'email_usuario', 'perfil_usuario', 'acao', 'entidade', 'id_entidade', 'descricao', 'ip', 'resultado'],
        'Comentarios_Gestor' => ['id_comentario', 'email_colaborador', 'gestor_email', 'comentario', 'tipo_comentario', 'data_comentario', 'visivel_colaborador'],
        'Skills_Colaborador' => ['id_skill', 'email_colaborador', 'nome', 'nivel', 'evidencia', 'data_registro'],
        'Vagas_Internas' => ['id_vaga', 'titulo', 'area', 'descricao', 'requisitos', 'status', 'publicado_por', 'data_publicacao'],
        'Candidaturas_Vaga' => ['id_candidatura', 'id_vaga', 'email_colaborador', 'mensagem', 'data_candidatura'],
    ];

    private static ?self $instancia = null;

    private string $modo;
    private string $planilhaId = '';
    private ?object $sheets = null;
    private string $arquivoMock = '';

    /** @var array<string, list<array<string, string|int>>> */
    private array $cache = [];

    /** @var array<string, true>|null */
    private ?array $abasExistentes = null;

    public static function instancia(): self
    {
        return self::$instancia ??= new self();
    }

    private function __construct()
    {
        $this->modo = DATA_SOURCE;

        if ($this->modo === 'sheets') {
            $this->conectarGoogle();

            return;
        }

        $this->arquivoMock = diretorioStorage() . '/mock_db.json';
        if (!is_file($this->arquivoMock)) {
            $this->resetarMock();
        }
    }

    public function modo(): string
    {
        return $this->modo;
    }

    /** @return list<array<string, string>> */
    public function todos(string $aba): array
    {
        return array_map(fn (array $linha) => $this->semMetadados($linha), $this->ler($aba));
    }

    /** @return list<array<string, string>> */
    public function buscar(string $aba, string $coluna, string $valor): array
    {
        $this->validarColuna($aba, $coluna);
        $alvo = $this->normalizar($coluna, $valor);

        return array_values(array_filter(
            $this->todos($aba),
            fn (array $linha) => $this->normalizar($coluna, $linha[$coluna] ?? '') === $alvo
        ));
    }

    public function buscarUm(string $aba, string $coluna, string $valor): ?array
    {
        return $this->buscar($aba, $coluna, $valor)[0] ?? null;
    }

    public function inserir(string $aba, array $dados): array
    {
        $registro = $this->montarRegistro($aba, $dados);

        if ($this->modo === 'sheets') {
            $corpo = new \Google\Service\Sheets\ValueRange(['values' => [array_values($registro)]]);
            $this->executarGoogle(fn () => $this->sheets->spreadsheets_values->append(
                $this->planilhaId,
                $this->intervalo($aba, 'A1'),
                $corpo,
                ['valueInputOption' => 'RAW', 'insertDataOption' => 'INSERT_ROWS']
            ));
        } else {
            $this->alterarMock(function (array &$banco) use ($aba, $registro): void {
                $banco[$aba][] = $registro;
            });
        }

        unset($this->cache[$aba]);

        return $registro;
    }

    /** Atualiza a primeira linha em que $colunaChave = $valorChave. Retorna false se não encontrada. */
    public function atualizar(string $aba, string $colunaChave, string $valorChave, array $dados): bool
    {
        $this->validarColuna($aba, $colunaChave);
        $alvo = $this->normalizar($colunaChave, $valorChave);
        $alteracoes = array_intersect_key($dados, array_flip(self::SCHEMA[$aba]));
        unset($alteracoes[$colunaChave]);

        foreach ($this->ler($aba) as $linha) {
            if ($this->normalizar($colunaChave, (string) $linha[$colunaChave]) !== $alvo) {
                continue;
            }

            $registro = $this->montarRegistro($aba, array_merge($this->semMetadados($linha), $alteracoes));

            if ($this->modo === 'sheets') {
                $corpo = new \Google\Service\Sheets\ValueRange(['values' => [array_values($registro)]]);
                $this->executarGoogle(fn () => $this->sheets->spreadsheets_values->update(
                    $this->planilhaId,
                    $this->intervalo($aba, 'A' . $linha['_linha']),
                    $corpo,
                    ['valueInputOption' => 'RAW']
                ));
            } else {
                $this->alterarMock(function (array &$banco) use ($aba, $colunaChave, $alvo, $registro): void {
                    foreach ($banco[$aba] ?? [] as $i => $existente) {
                        if ($this->normalizar($colunaChave, (string) ($existente[$colunaChave] ?? '')) === $alvo) {
                            $banco[$aba][$i] = $registro;

                            return;
                        }
                    }
                });
            }

            unset($this->cache[$aba]);

            return true;
        }

        return false;
    }

    /** Recria a base mock a partir de data/mock_seed.php. */
    public function resetarMock(): void
    {
        if ($this->modo !== 'mock') {
            throw new LogicException('resetarMock() só pode ser usado no modo mock.');
        }

        $semente = require ROOT_PATH . '/data/mock_seed.php';
        $banco = [];
        foreach (array_keys(self::SCHEMA) as $aba) {
            $banco[$aba] = array_map(fn (array $linha) => $this->montarRegistro($aba, $linha), $semente[$aba] ?? []);
        }

        $this->gravarMock($banco);
        $this->cache = [];
    }

    /**
     * Lê um intervalo das abas do TalentFlow. Essas abas ficam fora do esquema do PDI Connect.
     *
     * @return list<list<string>>
     */
    public function lerIntervaloTalentFlow(string $aba, string $celulas): array
    {
        $this->exigirAbaTalentFlow($aba);
        if ($this->modo !== 'sheets') {
            return [];
        }

        $resposta = $this->executarGoogle(fn () => $this->sheets->spreadsheets_values->get(
            $this->planilhaId,
            $this->intervalo($aba, $celulas)
        ));

        return array_map(
            static fn ($linha) => array_map(static fn ($celula) => trim((string) $celula), $linha),
            $resposta->getValues() ?? []
        );
    }

    /** Acrescenta linhas nas abas do TalentFlow, sem reescrever o que já existe. */
    public function anexarLinhasTalentFlow(string $aba, array $linhas): void
    {
        $this->exigirAbaTalentFlow($aba);
        if ($linhas === [] || $this->modo !== 'sheets') {
            return;
        }

        $corpo = new \Google\Service\Sheets\ValueRange(['values' => $linhas]);
        $this->executarGoogle(fn () => $this->sheets->spreadsheets_values->append(
            $this->planilhaId,
            $this->intervalo($aba, 'A1'),
            $corpo,
            ['valueInputOption' => 'RAW', 'insertDataOption' => 'INSERT_ROWS']
        ));
    }

    /** Grava um intervalo já existente nas abas do TalentFlow. */
    public function gravarIntervaloTalentFlow(string $aba, string $celulas, array $linhas): void
    {
        $this->exigirAbaTalentFlow($aba);
        if ($linhas === [] || $this->modo !== 'sheets') {
            return;
        }

        $corpo = new \Google\Service\Sheets\ValueRange(['values' => $linhas]);
        $this->executarGoogle(fn () => $this->sheets->spreadsheets_values->update(
            $this->planilhaId,
            $this->intervalo($aba, $celulas),
            $corpo,
            ['valueInputOption' => 'RAW']
        ));
    }

    /* ---------------------------------------------------------- */

    private function conectarGoogle(): void
    {
        if (!class_exists(\Google\Client::class)) {
            throw new RuntimeException('Biblioteca google/apiclient não encontrada. Execute "composer install".');
        }

        $this->planilhaId = (string) env('GOOGLE_SHEETS_ID', '');
        if ($this->planilhaId === '') {
            throw new RuntimeException('GOOGLE_SHEETS_ID não configurado.');
        }

        $credencial = trim((string) env('GOOGLE_SERVICE_ACCOUNT_JSON', ''));
        $config = match (true) {
            str_starts_with($credencial, '{') => json_decode($credencial, true),
            $credencial !== '' && is_file($credencial) => $credencial,
            $credencial !== '' && is_file(ROOT_PATH . '/' . $credencial) => ROOT_PATH . '/' . $credencial,
            default => null,
        };

        if (empty($config)) {
            throw new RuntimeException('GOOGLE_SERVICE_ACCOUNT_JSON inválido ou arquivo não encontrado.');
        }

        $cliente = new \Google\Client();
        $cliente->setApplicationName(APP_NAME);
        $cliente->setScopes([\Google\Service\Sheets::SPREADSHEETS]);
        $cliente->setAuthConfig($config);

        $certificados = ROOT_PATH . '/storage/cacert.pem';
        if (is_file($certificados) && class_exists(\GuzzleHttp\Client::class)) {
            $cliente->setHttpClient(new \GuzzleHttp\Client(['verify' => $certificados]));
        }

        $this->sheets = new \Google\Service\Sheets($cliente);
    }

    private function executarGoogle(callable $operacao): mixed
    {
        try {
            return $operacao();
        } catch (\Google\Service\Exception $erro) {
            error_log('[GoogleSheetsService] ' . $erro->getMessage());
            throw new RuntimeException('Não foi possível acessar a planilha do Google Sheets. Verifique credenciais, compartilhamento e nomes das abas.', 0, $erro);
        }
    }

    /** @return list<array<string, string|int>> */
    private function ler(string $aba): array
    {
        $this->validarAba($aba);
        $this->garantirAba($aba);

        if (isset($this->cache[$aba])) {
            return $this->cache[$aba];
        }

        if ($this->modo === 'sheets') {
            $resposta = $this->executarGoogle(fn () => $this->sheets->spreadsheets_values->get(
                $this->planilhaId,
                $this->intervalo($aba, 'A1:ZZ')
            ));
            $valores = $resposta->getValues() ?? [];
            $cabecalho = array_map(static fn ($c) => trim((string) $c), array_shift($valores) ?? []);
            $cabecalho = $cabecalho ?: self::SCHEMA[$aba];
        } else {
            $valores = array_map('array_values', $this->lerMock()[$aba] ?? []);
            $cabecalho = self::SCHEMA[$aba];
        }

        $linhas = [];
        foreach ($valores as $indice => $valoresLinha) {
            if (!array_filter($valoresLinha, static fn ($v) => trim((string) $v) !== '')) {
                continue;
            }
            $linha = ['_linha' => $indice + 2];
            foreach (self::SCHEMA[$aba] as $coluna) {
                $posicao = array_search($coluna, $cabecalho, true);
                $linha[$coluna] = $posicao === false ? '' : trim((string) ($valoresLinha[$posicao] ?? ''));
            }
            $linhas[] = $linha;
        }

        return $this->cache[$aba] = $linhas;
    }

    private function montarRegistro(string $aba, array $dados): array
    {
        $this->validarAba($aba);
        $registro = [];

        foreach (self::SCHEMA[$aba] as $coluna) {
            $valor = $dados[$coluna] ?? '';
            $registro[$coluna] = match (true) {
                is_bool($valor) => $valor ? 'sim' : 'nao',
                is_scalar($valor) => (string) $valor,
                $valor === null => '',
                default => throw new InvalidArgumentException("Valor inválido para {$aba}.{$coluna}."),
            };
        }

        return $registro;
    }

    private function semMetadados(array $linha): array
    {
        unset($linha['_linha']);

        return $linha;
    }

    private function normalizar(string $coluna, string $valor): string
    {
        return str_contains($coluna, 'email') ? normalizarEmail($valor) : trim($valor);
    }

    /** Cria a aba e a linha de cabeçalho quando ainda não existem na planilha. */
    private function garantirAba(string $aba): void
    {
        if ($this->modo !== 'sheets' || isset($this->abasExistentes[$aba])) {
            return;
        }

        if ($this->abasExistentes === null) {
            $meta = $this->executarGoogle(fn () => $this->sheets->spreadsheets->get(
                $this->planilhaId,
                ['fields' => 'sheets.properties.title']
            ));
            $this->abasExistentes = [];
            foreach ($meta->getSheets() as $folha) {
                $titulo = (string) $folha->getProperties()->getTitle();
                if ($titulo !== '') {
                    $this->abasExistentes[$titulo] = true;
                }
            }
        }

        if (isset($this->abasExistentes[$aba])) {
            return;
        }

        $pedido = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
            'requests' => [[
                'addSheet' => ['properties' => ['title' => $aba]],
            ]],
        ]);
        $this->executarGoogle(fn () => $this->sheets->spreadsheets->batchUpdate($this->planilhaId, $pedido));

        $cabecalho = new \Google\Service\Sheets\ValueRange(['values' => [self::SCHEMA[$aba]]]);
        $this->executarGoogle(fn () => $this->sheets->spreadsheets_values->update(
            $this->planilhaId,
            $this->intervalo($aba, 'A1'),
            $cabecalho,
            ['valueInputOption' => 'RAW']
        ));
        $this->abasExistentes[$aba] = true;
    }

    private function intervalo(string $aba, string $celulas): string
    {
        return "'" . str_replace("'", "''", $aba) . "'!" . $celulas;
    }

    private function exigirAbaTalentFlow(string $aba): void
    {
        if (!in_array($aba, ['TF_Registros', 'TF_Usuarios'], true)) {
            throw new InvalidArgumentException('Aba fora do vínculo com o TalentFlow.');
        }
    }

    private function validarAba(string $aba): void
    {
        if (!isset(self::SCHEMA[$aba])) {
            throw new InvalidArgumentException("Aba desconhecida: {$aba}");
        }
    }

    private function validarColuna(string $aba, string $coluna): void
    {
        $this->validarAba($aba);
        if (!in_array($coluna, self::SCHEMA[$aba], true)) {
            throw new InvalidArgumentException("Coluna desconhecida: {$aba}.{$coluna}");
        }
    }

    private function lerMock(): array
    {
        $conteudo = is_file($this->arquivoMock) ? (string) file_get_contents($this->arquivoMock) : '';

        return json_decode($conteudo, true) ?: [];
    }

    private function gravarMock(array $banco): void
    {
        $json = json_encode($banco, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($this->arquivoMock, (string) $json, LOCK_EX) === false) {
            throw new RuntimeException('Não foi possível gravar a base mock em ' . $this->arquivoMock);
        }
    }

    private function alterarMock(callable $alteracao): void
    {
        $handle = fopen($this->arquivoMock, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Não foi possível abrir a base mock.');
        }

        try {
            flock($handle, LOCK_EX);
            $banco = json_decode((string) stream_get_contents($handle), true) ?: [];
            $alteracao($banco);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($banco, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
