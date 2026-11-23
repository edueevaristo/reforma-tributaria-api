<?php

/**
 * Regenera resources/dados/*.json a partir do banco SQLite da Calculadora de Tributos oficial.
 *
 * Passo a passo (detalhado no CONTRIBUTING.md):
 * 1. git clone https://github.com/ReceitaFederal/calculadora-rtc-regime-regular.git
 * 2. Gere o banco na pasta da calculadora:
 *    ./mvnw flyway:migrate -Dflyway.configFiles=./flyway/flyway-pro.conf --batch-mode --errors
 * 3. Rode este script apontando para o banco gerado:
 *    php bin/atualizar-tabelas.php /caminho/calculadora-rtc-regime-regular/calculadora/db/calculadora-pro.db
 *
 * Requer ext-pdo_sqlite.
 */

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Uso: php bin/atualizar-tabelas.php /caminho/calculadora-pro.db\n");
    exit(1);
}

$caminhoBanco = $argv[1];
if (!is_file($caminhoBanco)) {
    fwrite(STDERR, "Banco nao encontrado: {$caminhoBanco}\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $caminhoBanco);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$versao = $pdo->query('SELECT * FROM VERSAO_BASE_DADO ORDER BY VRBD_ID DESC LIMIT 1')->fetch();

$meta = [
    'fonte' => 'Calculadora de Tributos - Regime Regular (Receita Federal do Brasil / Serpro), licenca MIT',
    'repositorio' => 'https://github.com/ReceitaFederal/calculadora-rtc-regime-regular',
    'versaoBaseDados' => $versao['VRBD_VERSAO_BASE_DADO'],
    'dataBaseDados' => substr($versao['VRBD_DATA'], 0, 10),
    'descricao' => $versao['VRBD_DESCRICAO'],
];

$tributosPorSituacao = [];
$sql = 'SELECT ts.TRST_SITR_ID AS sitr, t.TBTO_SIGLA AS sigla
        FROM TRIBUTO_SITUACAO_TRIBUTARIA ts JOIN TRIBUTO t ON t.TBTO_ID = ts.TRST_TBTO_ID';
foreach ($pdo->query($sql) as $linha) {
    $tributosPorSituacao[$linha['sitr']][$linha['sigla']] = true;
}

$cstIbsCbs = [];
$cstIs = [];
$grupoPorSituacao = [];
foreach ($pdo->query('SELECT * FROM SITUACAO_TRIBUTARIA ORDER BY SITR_CD') as $linha) {
    $somenteIs = array_keys($tributosPorSituacao[$linha['SITR_ID']] ?? []) === ['IS'];
    $grupoPorSituacao[$linha['SITR_ID']] = [$somenteIs ? 'IS' : 'IBSCBS', $linha['SITR_CD']];

    $registro = [
        'codigo' => $linha['SITR_CD'],
        'descricao' => $linha['SITR_DESCRICAO'],
        'inicioVigencia' => substr($linha['SITR_INICIO_VIGENCIA'], 0, 10),
        'fimVigencia' => $linha['SITR_FIM_VIGENCIA'] === null ? null : substr($linha['SITR_FIM_VIGENCIA'], 0, 10),
    ];

    if ($somenteIs) {
        $cstIs[] = $registro;
        continue;
    }

    $registro['grupos'] = [
        'gIBSCBS' => (bool) $linha['SITR_IND_GIBSCBS'],
        'gIBSCBSMono' => (bool) $linha['SITR_IND_GIBSCBSMONO'],
        'gRed' => (bool) $linha['SITR_IND_GRED'],
        'gDif' => (bool) $linha['SITR_IND_GDIF'],
        'gTransfCred' => (bool) $linha['SITR_IND_GTRANSFCRED'],
        'gCredPresIBSZFM' => (bool) $linha['SITR_IND_GCREDPRESIBSZFM'],
        'gAjusteCompet' => (bool) $linha['SITR_IND_GAJUSTECOMPET'],
    ];
    $cstIbsCbs[] = $registro;
}

$reducoes = [];
$sql = 'SELECT p.PERE_CLTR_ID AS cltr, p.PERE_VALOR AS valor, t.TBTO_SIGLA AS sigla
        FROM PERCENTUAL_REDUCAO p JOIN TRIBUTO t ON t.TBTO_ID = p.PERE_TBTO_ID';
foreach ($pdo->query($sql) as $linha) {
    $reducoes[$linha['cltr']][$linha['sigla']] = (float) $linha['valor'];
}

$classificacaoIbsCbs = [];
$classificacaoIs = [];
foreach ($pdo->query('SELECT * FROM CLASSIFICACAO_TRIBUTARIA ORDER BY CLTR_CD, CLTR_INICIO_VIGENCIA') as $linha) {
    list($grupo, $cst) = $grupoPorSituacao[$linha['CLTR_SITR_ID']];
    $registro = [
        'codigo' => $linha['CLTR_CD'],
        'cst' => $cst,
        'descricao' => $linha['CLTR_DESCRICAO'],
        'tipoAliquota' => $linha['CLTR_TIPO_ALIQUOTA'],
        'nomenclatura' => $linha['CLTR_NOMENCLATURA'],
        'anexo' => $linha['CLTR_ANEXO'],
        'inicioVigencia' => substr($linha['CLTR_INICIO_VIGENCIA'], 0, 10),
        'fimVigencia' => $linha['CLTR_FIM_VIGENCIA'] === null ? null : substr($linha['CLTR_FIM_VIGENCIA'], 0, 10),
    ];

    if ($grupo === 'IS') {
        $classificacaoIs[] = $registro;
        continue;
    }

    $reducao = $reducoes[$linha['CLTR_ID']] ?? [];
    $registro['percentualReducao'] = [
        'cbs' => $reducao['CBS'] ?? 0.0,
        'ibsUf' => $reducao['IBSUF'] ?? 0.0,
        'ibsMun' => $reducao['IBSMun'] ?? 0.0,
    ];
    $registro['apropriacaoCreditoAdquirente'] = [
        'cbs' => (bool) $linha['CLTR_IN_APROPRIACAO_CREDITOS_ADQUIRENTES_CBS'],
        'ibs' => (bool) $linha['CLTR_IN_APROPRIACAO_CREDITOS_ADQUIRENTES_IBS'],
    ];
    $classificacaoIbsCbs[] = $registro;
}

$destino = __DIR__ . '/../resources/dados/';
$arquivos = [
    'cst-ibs-cbs' => $cstIbsCbs,
    'cst-is' => $cstIs,
    'cclasstrib-ibs-cbs' => $classificacaoIbsCbs,
    'cclasstrib-is' => $classificacaoIs,
];

foreach ($arquivos as $nome => $dados) {
    $json = json_encode(
        ['meta' => $meta, 'dados' => $dados],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
    );
    file_put_contents($destino . $nome . '.json', $json . "\n");
    printf("%s.json: %d registros\n", $nome, count($dados));
}

printf("Base de dados %s de %s\n", $meta['versaoBaseDados'], $meta['dataBaseDados']);
