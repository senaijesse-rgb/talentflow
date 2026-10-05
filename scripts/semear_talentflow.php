<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/config.php';

$seed = file_get_contents(ROOT_PATH . '/storage/talentflow-seed.json');
$abas = json_decode((string) file_get_contents(ROOT_PATH . '/storage/talentflow-abas.json'), true);
if ($seed === false || !is_array($abas)) {
    fwrite(STDERR, "seed ausente\n");
    exit(1);
}

$partes = mb_str_split($seed, 20000, 'UTF-8');
$estado = [['json']];
foreach ($partes as $parte) {
    $estado[] = [$parte];
}

$planilha = (string) env('GOOGLE_SHEETS_ID', '');
$credencial = trim((string) env('GOOGLE_SERVICE_ACCOUNT_JSON', ''));
$arquivo = is_file($credencial) ? $credencial : ROOT_PATH . '/' . $credencial;
$cliente = new Google\Client();
$cliente->setApplicationName('TalentFlow PDI');
$cliente->setScopes([Google\Service\Sheets::SPREADSHEETS]);
$cliente->setAuthConfig($arquivo);
$certificados = ROOT_PATH . '/storage/cacert.pem';
if (is_file($certificados)) {
    $cliente->setHttpClient(new GuzzleHttp\Client(['verify' => $certificados]));
}
$sheets = new Google\Service\Sheets($cliente);
$meta = $sheets->spreadsheets->get($planilha);
$existentes = [];
foreach ($meta->getSheets() as $folha) {
    $existentes[$folha->getProperties()->getTitle()] = true;
}

$codigo = json_decode((string) file_get_contents(ROOT_PATH . '/storage/talentflow-code.json'), true);
$trechos = mb_str_split((string) ($codigo['code'] ?? ''), 20000, 'UTF-8');
$motor = [];
foreach ($trechos as $trecho) {
    $motor[] = [$trecho];
}

$novas = ['TalentFlow_Estado', 'TF_Usuarios', 'TF_Metas', 'TF_Projetos', 'TF_Skills', 'TF_Logs', 'TF_Motor', 'TF_Registros', 'TF_Sessoes', 'TF_Premiacoes', 'TF_Reconhecimentos'];
$pedidos = [];
foreach ($novas as $nome) {
    if (!isset($existentes[$nome])) {
        $pedidos[] = new Google\Service\Sheets\Request([
            'addSheet' => ['properties' => ['title' => $nome]],
        ]);
    }
}
if ($pedidos) {
    $sheets->spreadsheets->batchUpdate($planilha, new Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
        'requests' => $pedidos,
    ]));
}

$registros = json_decode((string) file_get_contents(ROOT_PATH . '/storage/talentflow-registros.json'), true);
if (!is_array($registros)) {
    fwrite(STDERR, "registros ausentes\n");
    exit(1);
}

$dados = [
    'TalentFlow_Estado' => $estado,
    'TF_Usuarios' => $abas['usuarios'],
    'TF_Metas' => $abas['metas'],
    'TF_Projetos' => $abas['projetos'],
    'TF_Skills' => $abas['skills'],
    'TF_Logs' => $abas['logs'],
    'TF_Premiacoes' => $abas['premiacoes'],
    'TF_Reconhecimentos' => $abas['reconhecimentos'],
    'TF_Registros' => $registros,
    'TF_Sessoes' => [['token', 'email', 'exp']],
    'TF_Motor' => $motor,
];

$sheets->spreadsheets_values->batchClear($planilha, new Google\Service\Sheets\BatchClearValuesRequest([
    'ranges' => array_map(static fn (string $nome) => $nome . '!A:Z', $novas),
]));

$payload = [];
foreach ($dados as $nome => $linhas) {
    $payload[] = new Google\Service\Sheets\ValueRange([
        'range' => $nome . '!A1',
        'values' => $linhas,
    ]);
}
$sheets->spreadsheets_values->batchUpdate($planilha, new Google\Service\Sheets\BatchUpdateValuesRequest([
    'valueInputOption' => 'RAW',
    'data' => $payload,
]));

echo 'ok partes=' . count($partes) . ' chars=' . mb_strlen($seed, 'UTF-8') . PHP_EOL;
