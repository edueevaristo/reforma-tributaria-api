<?php

/**
 * Gera a colecao do Postman (e o ambiente) para testar os mocks WireMock.
 *
 * Saida:
 * - postman/reforma-tributaria-wiremock.postman_collection.json
 * - postman/wiremock-local.postman_environment.json
 *
 * A colecao e montada a partir dos proprios mocks: os cenarios da SEFAZ vem de
 * wiremock/mappings/sefaz e os envelopes SOAP de wiremock/__files/nfe/soap.
 * Rode de novo sempre que um mock ou XML de exemplo mudar.
 *
 * Uso: php bin/gerar-postman.php
 */

declare(strict_types=1);

const URL_PADRAO = 'http://localhost:8089';
const CENARIO_PADRAO = 'autorizado';
const JSON = 'application/json';
const XML = 'application/xml';
const SOAP = 'application/soap+xml; charset=utf-8';
const FORMATO_JSON = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

/**
 * Titulo e descricao de cada XML de exemplo (wiremock/__files/nfe/indice.json).
 */
const EXEMPLOS = [
    'venda' => [
        'Venda',
        'Venda de mercadoria tributada integralmente pelo IBS e pela CBS (CST 000, cClassTrib 000001), CFOP 5102.',
    ],
    'venda-com-reducao' => [
        'Venda com Redução',
        'Venda com um item de higiene pessoal com redução de 60% (CST 200, cClassTrib 200035, Anexo VIII) '
            . 'e um item tributado integralmente (CST 000).',
    ],
    'venda-nfce' => [
        'Venda',
        'Venda ao consumidor final em NFC-e (modelo 65, com `infNFeSupl`), com itens nos CST 200 e 000.',
    ],
    'devolucao' => [
        'Devolução',
        'Devolução de venda (finNFe 4, tpNF 0, CFOP 1202) referenciando a NF-e de venda em `NFref`. '
            . 'CST 000, cClassTrib 000001.',
    ],
    'bonificacao' => [
        'Bonificação',
        'Remessa em bonificação (CFOP 5910) sem IBS/CBS: CST 410, cClassTrib 410001, sem o grupo `gIBSCBS`.',
    ],
    'transferencia' => [
        'Transferência',
        'Transferência entre estabelecimentos do mesmo contribuinte (CFOP 5152): CST 410, cClassTrib 410002, '
            . 'sem o grupo `gIBSCBS`.',
    ],
    'evento-cancelamento' => ['Cancelamento', 'Evento de cancelamento (110111) da NF-e de venda.'],
    'evento-carta-correcao' => ['Carta de Correção', 'Evento de carta de correção (110110) da NF-e de venda.'],
];

$raiz = dirname(__DIR__);
$wiremock = $raiz . '/wiremock';
$destino = $raiz . '/postman';

/**
 * @return array<string, mixed>
 */
function lerJson(string $arquivo): array
{
    $dados = json_decode((string) file_get_contents($arquivo), true);
    if (!is_array($dados)) {
        throw new RuntimeException('JSON invalido: ' . $arquivo);
    }

    return $dados;
}

function lerArquivo(string $arquivo): string
{
    $conteudo = file_get_contents($arquivo);
    if ($conteudo === false) {
        throw new RuntimeException('Arquivo nao encontrado: ' . $arquivo);
    }

    return $conteudo;
}

/**
 * Descricao em Markdown, mostrada na aba Docs do Postman.
 */
function documentacao(string $oQueFaz, string $esperado, string $observacao = ''): string
{
    $texto = $oQueFaz . "\n\n**Resposta esperada:** " . $esperado;

    return $observacao === '' ? $texto : $texto . "\n\n" . $observacao;
}

/**
 * Teste que confere o status HTTP.
 */
function status(int $codigo): string
{
    return "pm.test('HTTP {$codigo}', () => pm.response.to.have.status({$codigo}));";
}

/**
 * Testes de uma resposta SOAP da SEFAZ: status HTTP e cStat esperados.
 *
 * @param string[] $cStats
 * @return string[]
 */
function testesSefaz(array $cStats): array
{
    $linhas = [
        status(200),
        "const cStats = (pm.response.text().match(/<cStat>(\\d+)<\\/cStat>/g) || [])"
            . ".map(c => c.replace(/\\D/g, ''));",
    ];
    foreach ($cStats as $cStat) {
        $linhas[] = "pm.test('cStat {$cStat}', () => pm.expect(cStats).to.include('{$cStat}'));";
    }

    return $linhas;
}

/**
 * @param array<string, string> $parametros
 * @return array<string, mixed>
 */
function url(string $caminho, array $parametros = []): array
{
    $partes = array_values(array_filter(explode('/', $caminho), 'strlen'));
    $url = ['raw' => '{{BASE_URL}}/' . implode('/', $partes), 'host' => ['{{BASE_URL}}'], 'path' => $partes];

    if ($parametros === []) {
        return $url;
    }

    $url['raw'] .= '?' . http_build_query($parametros);
    $url['query'] = array_map(
        static fn (string $chave, string $valor): array => ['key' => $chave, 'value' => $valor],
        array_keys($parametros),
        array_values($parametros)
    );

    return $url;
}

/**
 * Uma requisicao da colecao.
 *
 * @param array<string, mixed> $opcoes metodo, caminho, parametros, corpo, tipo, testes, descricao
 * @return array<string, mixed>
 */
function requisicao(string $nome, array $opcoes): array
{
    $tipo = $opcoes['tipo'] ?? JSON;
    $corpo = $opcoes['corpo'] ?? null;
    $cabecalhos = [['key' => 'Accept', 'value' => $tipo === SOAP ? SOAP : $tipo]];
    $request = [
        'method' => $opcoes['metodo'] ?? 'GET',
        'header' => $cabecalhos,
        'url' => url($opcoes['caminho'], $opcoes['parametros'] ?? []),
        'description' => $opcoes['descricao'],
    ];

    if ($corpo !== null) {
        $request['header'][] = ['key' => 'Content-Type', 'value' => $tipo];
        $request['body'] = [
            'mode' => 'raw',
            'raw' => $corpo,
            'options' => ['raw' => ['language' => $tipo === JSON ? 'json' : 'xml']],
        ];
    }

    return [
        'name' => $nome,
        'event' => [['listen' => 'test', 'script' => ['type' => 'text/javascript', 'exec' => $opcoes['testes']]]],
        'request' => $request,
    ];
}

/**
 * @param array<int, array<string, mixed>> $itens
 * @return array<string, mixed>
 */
function pasta(string $nome, string $descricao, array $itens): array
{
    return ['name' => $nome, 'description' => $descricao, 'item' => $itens];
}

/**
 * Prefixo do nome de um exemplo: NFe, NFCe ou Evento.
 *
 * @param array<string, mixed> $exemplo
 */
function prefixo(array $exemplo): string
{
    if ($exemplo['servico'] === 'NFeRecepcaoEvento4') {
        return 'Evento';
    }

    return ($exemplo['modelo'] ?? 55) === 65 ? 'NFCe' : 'NFe';
}

/**
 * @param array<string, mixed> $exemplo
 * @return array{0: string, 1: string}
 */
function tituloEDescricao(array $exemplo): array
{
    return EXEMPLOS[$exemplo['nome']] ?? [(string) $exemplo['nome'], (string) $exemplo['descricao']];
}

$operacao = static function (string $ncm): string {
    return (string) json_encode([
        'id' => 'postman',
        'versao' => '1.0.0',
        'dhFatoGerador' => '2026-11-20T10:00:00-03:00',
        'municipio' => 3550308,
        'uf' => 'SP',
        'itens' => [
            ['numero' => 1, 'ncm' => $ncm, 'cst' => '000', 'cClassTrib' => '000001', 'baseCalculo' => 1000.00],
            ['numero' => 2, 'nbs' => '122011200', 'cst' => '200', 'cClassTrib' => '200028', 'baseCalculo' => 1200.00],
        ],
    ], FORMATO_JSON);
};
$envelope = static fn (string $nome): string => lerArquivo($wiremock . '/__files/nfe/soap/' . $nome . '.xml');

// WireMock | Administracao
$administracao = [
    requisicao('Health', [
        'caminho' => '__admin/health',
        'testes' => [status(200)],
        'descricao' => documentacao(
            'Confere se o WireMock está no ar. Rode antes dos demais testes.',
            'HTTP 200 com `"status": "healthy"`.'
        ),
    ]),
    requisicao('Requisições Recebidas', [
        'caminho' => '__admin/requests',
        'testes' => [status(200)],
        'descricao' => documentacao(
            'Lista as requisições que o WireMock recebeu, com cabeçalhos e corpo. '
                . 'Útil para conferir o XML que o seu emissor enviou.',
            'HTTP 200 com a lista `requests`.'
        ),
    ]),
];

// Calculadora | Tributos
$xmlGrupo = trim(lerArquivo($wiremock . '/__files/calculadora/xml-grupo-nfe.xml'));
if (strpos($xmlGrupo, '<CST>000</CST>') === false) {
    throw new RuntimeException('xml-grupo-nfe.xml nao tem <CST>000</CST> para montar o caso invalido.');
}
$calc = 'api/calculadora/';
$classTrib = $calc . 'dados-abertos/classificacoes-tributarias/cbs-ibs/class-trib/';
$validar = ['tipo' => 'nfe', 'subtipo' => 'grupo'];
$calculadora = [
    requisicao('Regime Geral', [
        'metodo' => 'POST',
        'caminho' => $calc . 'regime-geral',
        'corpo' => $operacao('22030000'),
        'testes' => [
            status(200),
            "pm.test('2 itens', () => pm.expect(pm.response.json().objetos).to.have.lengthOf(2));",
        ],
        'descricao' => documentacao(
            'Calcula IBS e CBS de uma operação com dois itens: um tributado integralmente (CST 000) '
                . 'e um serviço de educação com redução de 60% (CST 200, cClassTrib 200028).',
            'HTTP 200 com os dois itens calculados em `objetos` e os totais.'
        ),
    ]),
    requisicao('Regime Geral | NCM Inválido', [
        'metodo' => 'POST',
        'caminho' => $calc . 'regime-geral',
        'corpo' => $operacao('00000000'),
        'testes' => [status(422)],
        'descricao' => documentacao(
            'Mesma operação, com o NCM `00000000` no primeiro item.',
            'HTTP 422 com o erro `REG-011`.',
            'Qualquer item com `"ncm": "00000000"` aciona este erro.'
        ),
    ]),
    requisicao('Observabilidade | Regime Geral', [
        'metodo' => 'POST',
        'caminho' => $calc . 'observabilidade/regime-geral',
        'corpo' => $operacao('22030000'),
        'testes' => [status(200)],
        'descricao' => documentacao(
            'Calcula sem interromper a operação no primeiro erro: cada item traz o seu `estadoItem`.',
            'HTTP 200 com 2 itens `CALCULADO` e 1 item `INCONSISTENCIA_ENTRADA`.'
        ),
    ]),
    requisicao('Base de Cálculo Unificada', [
        'metodo' => 'POST',
        'caminho' => $calc . 'base-calculo/base-calculo-unificada',
        'corpo' => (string) json_encode(['anoFatoGerador' => 2027, 'tipoDocumento' => 55], FORMATO_JSON),
        'testes' => [status(200)],
        'descricao' => documentacao(
            'Calcula as bases do regime regular, do Imposto Seletivo e do Simples Nacional da NF-e.',
            'HTTP 200 com o exemplo oficial: `baseRG` 870,00, `baseIS` 840,00 e `baseSN` 1060,00.'
        ),
    ]),
    requisicao('Gerar XML | IBSCBS e IBSCBSTot', [
        'metodo' => 'POST',
        'caminho' => $calc . 'xml/generate',
        'parametros' => ['tipo' => 'nfe'],
        'corpo' => (string) json_encode(['objetos' => []], FORMATO_JSON),
        'testes' => [status(200), "pm.test('Grupo IBSCBS', () => pm.expect(pm.response.text()).to.include('IBSCBS'));"],
        'descricao' => documentacao(
            'Gera os grupos XML `IBSCBS` e `IBSCBSTot` a partir do resultado do cálculo.',
            'HTTP 200 com o XML dos grupos.'
        ),
    ]),
    requisicao('Validar XML | Válido', [
        'metodo' => 'POST',
        'caminho' => $calc . 'xml/validate',
        'parametros' => $validar,
        'corpo' => $xmlGrupo,
        'tipo' => XML,
        'testes' => [status(200)],
        'descricao' => documentacao('Valida os grupos IBS/CBS contra o schema da NF-e.', 'HTTP 200 com `true`.'),
    ]),
    requisicao('Validar XML | Inválido (CST 999)', [
        'metodo' => 'POST',
        'caminho' => $calc . 'xml/validate',
        'parametros' => $validar,
        'corpo' => str_replace('<CST>000</CST>', '<CST>999</CST>', $xmlGrupo),
        'tipo' => XML,
        'testes' => [status(422)],
        'descricao' => documentacao(
            'Valida um grupo com o CST inexistente 999.',
            'HTTP 422 com a linha e a coluna do erro.',
            'Qualquer XML que contenha `<CST>999</CST>` aciona este erro.'
        ),
    ]),
    requisicao('Classificação Tributária | 200028', [
        'caminho' => $classTrib . '200028',
        'parametros' => ['data' => '2027-01-01'],
        'testes' => [status(200)],
        'descricao' => documentacao(
            'Consulta a cClassTrib 200028 (serviços de educação) vigente na data.',
            'HTTP 200 com a classificação: CST 200 e redução de 60%.'
        ),
    ]),
    requisicao('Classificação Tributária | Inexistente', [
        'caminho' => $classTrib . '999999',
        'parametros' => ['data' => '2027-01-01'],
        'testes' => [status(404)],
        'descricao' => documentacao(
            'Consulta uma cClassTrib que não existe.',
            'HTTP 404.',
            'Qualquer código diferente de 200028 responde 404.'
        ),
    ]),
];

// NFes | XML e NFes | SOAP
$indice = lerJson($wiremock . '/__files/nfe/indice.json')['exemplos'];
$quantidade = count($indice);
$nfesXml = [
    requisicao('NFes para Testes', [
        'caminho' => 'exemplos/nfe',
        'testes' => [
            status(200),
            "pm.test('{$quantidade} exemplos', () => "
                . "pm.expect(pm.response.json().exemplos).to.have.lengthOf({$quantidade}));",
        ],
        'descricao' => documentacao(
            'Lista os XMLs de exemplo com nome, descrição, chave de acesso e caminhos do XML e do envelope SOAP.',
            "HTTP 200 com os {$quantidade} exemplos em `exemplos`."
        ),
    ]),
];
$nfesSoap = [];
foreach ($indice as $exemplo) {
    [$titulo, $descricao] = tituloEDescricao($exemplo);
    $chave = "\n\nChave de acesso: `{$exemplo['chave']}`.";

    if (!empty($exemplo['xml'])) {
        $nfesXml[] = requisicao(prefixo($exemplo) . ' Xml ' . $titulo, [
            'caminho' => $exemplo['xml'],
            'tipo' => XML,
            'testes' => [status(200)],
            'descricao' => documentacao(
                $descricao . $chave,
                'HTTP 200 com a NF-e (`<NFe>`), como você guardaria ou importaria no seu sistema.'
            ),
        ]);
    }

    $nfesSoap[] = requisicao(prefixo($exemplo) . ' Soap ' . $titulo, [
        'caminho' => $exemplo['soap'],
        'tipo' => XML,
        'testes' => [status(200)],
        'descricao' => documentacao(
            $descricao . $chave,
            'HTTP 200 com o envelope SOAP 1.2 completo, pronto para enviar ao `' . $exemplo['servico'] . '`.'
        ),
    ]);
}

// SEFAZ | Autorizacao
$autorizacao = [];
$mapeamentos = glob($wiremock . '/mappings/sefaz/autorizacao-*.json') ?: [];
sort($mapeamentos);
foreach ($mapeamentos as $arquivo) {
    $mapa = lerJson($arquivo);
    $cenario = explode('/', $mapa['request']['urlPath'])[2];
    $parametros = $mapa['response']['transformerParameters'] ?? [];
    $cStat = (string) ($parametros['cStat'] ?? '100');
    $xMotivo = (string) ($parametros['xMotivo'] ?? 'Autorizado o uso da NF-e');
    $atraso = (int) ($mapa['response']['fixedDelayMilliseconds'] ?? 0) / 1000;
    $motivo = trim((string) preg_replace(['/^Rejeição:\s*/u', '/\s*\[nItem: \d+\]$/'], '', $xMotivo));
    $nome = 'Autorizado | cStat 100';
    $observacao = '';

    if (strpos($cenario, 'rejeicao-') === 0) {
        $nome = 'Rejeição ' . $cStat . ' | ' . $motivo;
    }

    if ($atraso > 0) {
        $nome = 'Lento | ' . $atraso . ' s';
        $observacao = 'Use para testar o timeout e a contingência do seu emissor.';
    }

    $autorizacao[] = requisicao($nome, [
        'metodo' => 'POST',
        'caminho' => 'sefaz/' . $cenario . '/NFeAutorizacao4',
        'corpo' => $envelope('venda'),
        'tipo' => SOAP,
        'testes' => testesSefaz(['104', $cStat]),
        'descricao' => documentacao(
            'Envia a NF-e de venda ao cenário `' . $cenario . '` do `NFeAutorizacao4`.',
            'HTTP 200 com `retEnviNFe` (cStat 104) e o protocolo com cStat ' . $cStat . ': ' . $xMotivo . '.'
                . ($atraso > 0 ? ' A resposta demora ' . $atraso . ' segundos.' : ''),
            $observacao
        ),
    ]);
}
$autorizacao[] = requisicao('Fora do Ar | HTTP 503', [
    'metodo' => 'POST',
    'caminho' => 'sefaz/fora-do-ar/NFeAutorizacao4',
    'corpo' => $envelope('venda'),
    'tipo' => SOAP,
    'testes' => [status(503)],
    'descricao' => documentacao(
        'Envia a NF-e de venda a uma SEFAZ indisponível.',
        'HTTP 503 com uma página de erro.',
        'Use para testar a contingência do seu emissor.'
    ),
]);

// SEFAZ | Status e Eventos
$statusServico = '<?xml version="1.0" encoding="utf-8"?>'
    . '<soap12:Envelope xmlns:soap12="http://www.w3.org/2003/05/soap-envelope"><soap12:Body>'
    . '<nfeDadosMsg xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/NFeStatusServico4">'
    . '<consStatServ xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">'
    . '<tpAmb>2</tpAmb><cUF>35</cUF><xServ>STATUS</xServ></consStatServ>'
    . '</nfeDadosMsg></soap12:Body></soap12:Envelope>';
$statusEEventos = [
    requisicao('Status do Serviço | cStat 107', [
        'metodo' => 'POST',
        'caminho' => 'sefaz/autorizado/NFeStatusServico4',
        'corpo' => $statusServico,
        'tipo' => SOAP,
        'testes' => testesSefaz(['107']),
        'descricao' => documentacao(
            'Consulta o status do serviço da SEFAZ de SP em homologação.',
            'HTTP 200 com `retConsStatServ` e cStat 107 (serviço em operação).',
            'Responde assim em qualquer cenário, menos `fora-do-ar`.'
        ),
    ]),
    requisicao('Status do Serviço | Fora do Ar', [
        'metodo' => 'POST',
        'caminho' => 'sefaz/fora-do-ar/NFeStatusServico4',
        'corpo' => $statusServico,
        'tipo' => SOAP,
        'testes' => [status(503)],
        'descricao' => documentacao(
            'Consulta o status de uma SEFAZ indisponível.',
            'HTTP 503 com uma página de erro.'
        ),
    ]),
    requisicao('Cancelamento | cStat 135', [
        'metodo' => 'POST',
        'caminho' => 'sefaz/autorizado/NFeRecepcaoEvento4',
        'corpo' => $envelope('evento-cancelamento'),
        'tipo' => SOAP,
        'testes' => testesSefaz(['128', '135']),
        'descricao' => documentacao(
            'Envia o cancelamento (110111) da NF-e de venda.',
            'HTTP 200 com `retEnvEvento` (cStat 128) e o evento registrado e vinculado à NF-e (cStat 135).'
        ),
    ]),
    requisicao('Carta de Correção | cStat 135', [
        'metodo' => 'POST',
        'caminho' => 'sefaz/autorizado/NFeRecepcaoEvento4',
        'corpo' => $envelope('evento-carta-correcao'),
        'tipo' => SOAP,
        'testes' => testesSefaz(['128', '135']),
        'descricao' => documentacao(
            'Envia a carta de correção (110110) da NF-e de venda.',
            'HTTP 200 com `retEnvEvento` (cStat 128) e o evento registrado e vinculado à NF-e (cStat 135).'
        ),
    ]),
];

// SEFAZ | Enviar Exemplos
$envios = [];
foreach ($indice as $exemplo) {
    [$titulo, $descricao] = tituloEDescricao($exemplo);
    $evento = $exemplo['servico'] === 'NFeRecepcaoEvento4';
    $envios[] = requisicao('Enviar ' . prefixo($exemplo) . ' ' . $titulo, [
        'metodo' => 'POST',
        'caminho' => 'sefaz/{{CENARIO}}/' . $exemplo['servico'],
        'corpo' => $envelope($exemplo['nome']),
        'tipo' => SOAP,
        'testes' => [
            status(200),
            "const cStats = (pm.response.text().match(/<cStat>(\\d+)<\\/cStat>/g) || [])"
                . ".map(c => c.replace(/\\D/g, ''));",
            "const cStat = cStats.pop();",
            "pm.test('Tem cStat', () => pm.expect(cStat).to.be.a('string'));",
            "console.log('" . $exemplo['nome'] . "', cStat);",
        ],
        'descricao' => documentacao(
            $descricao . ' Enviada ao cenário da variável `CENARIO`.',
            $evento
                ? 'HTTP 200 com o evento registrado (cStat 135) em qualquer cenário, menos `fora-do-ar`.'
                : 'HTTP 200 com o cStat do cenário escolhido (100 no `autorizado`, o código da rejeição nos demais).'
        ),
    ]);
}

$colecao = [
    'info' => [
        'name' => 'WireMock - ReformaTributária',
        'description' => "Testa os mocks WireMock do pacote [du-nf/reforma-tributaria]"
            . "(https://github.com/edueevaristo/reforma-tributaria-api): Calculadora de Tributos oficial, "
            . "web services de NF-e 4.00 da SEFAZ com as rejeições da Reforma Tributária e XMLs de exemplo "
            . "de NF-e e NFC-e com IBS e CBS.\n\n"
            . "## Como usar\n\n"
            . "1. Suba o WireMock: `docker compose -f wiremock/docker-compose.yml up -d`.\n"
            . "2. Selecione o ambiente **WireMock local**.\n"
            . "3. Rode a coleção inteira (**Run collection**) ou uma pasta.\n\n"
            . "## Variáveis\n\n"
            . "| Variável | Padrão | Uso |\n|---|---|---|\n"
            . "| `BASE_URL` | `" . URL_PADRAO . "` | Endereço do WireMock |\n"
            . "| `CENARIO` | `" . CENARIO_PADRAO . "` | Cenário usado no envio dos exemplos "
            . "(ex.: `rejeicao-1091`) |\n\n"
            . "Os mocks não validam assinatura, schema nem regras de negócio: o cenário é escolhido pela URL.\n\n"
            . "Coleção gerada por `bin/gerar-postman.php`. Não edite à mão: rode o script de novo.",
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],
    'item' => [
        pasta('WireMock | Administração', 'Saúde do WireMock e requisições recebidas.', $administracao),
        pasta(
            'Calculadora | Tributos',
            'Mocks da Calculadora de Tributos oficial da Receita Federal (`/api/calculadora`), '
                . 'com os casos de sucesso e de erro.',
            $calculadora
        ),
        pasta(
            'NFes | XML',
            'NF-e e NFC-e de exemplo com os grupos `IBSCBS` e `IBSCBSTot`, validadas no schema oficial. '
                . 'Ambiente de homologação (tpAmb 2), emissão em 20/11/2026 em São Paulo.',
            $nfesXml
        ),
        pasta(
            'NFes | SOAP',
            'Os mesmos exemplos dentro do envelope SOAP 1.2 (`enviNFe` ou `envEvento`), prontos para enviar à SEFAZ.',
            $nfesSoap
        ),
        pasta(
            'SEFAZ | Autorização',
            'A NF-e de venda enviada ao `NFeAutorizacao4` em cada cenário: autorizada, lenta, fora do ar '
                . 'e cada rejeição da Reforma Tributária (NT 2025.002-RTC).',
            $autorizacao
        ),
        pasta(
            'SEFAZ | Status e Eventos',
            'Status do serviço (`NFeStatusServico4`), cancelamento e carta de correção (`NFeRecepcaoEvento4`).',
            $statusEEventos
        ),
        pasta(
            'SEFAZ | Enviar Exemplos',
            'Envia todos os exemplos ao cenário da variável `CENARIO`. '
                . 'Troque para `rejeicao-1091`, por exemplo, para ver todos rejeitados.',
            $envios
        ),
    ],
    'variable' => [
        ['key' => 'BASE_URL', 'value' => URL_PADRAO],
        ['key' => 'CENARIO', 'value' => CENARIO_PADRAO],
    ],
];

$ambiente = [
    'name' => 'WireMock local',
    'values' => [
        ['key' => 'BASE_URL', 'value' => URL_PADRAO, 'type' => 'default', 'enabled' => true],
        ['key' => 'CENARIO', 'value' => CENARIO_PADRAO, 'type' => 'default', 'enabled' => true],
    ],
    '_postman_variable_scope' => 'environment',
];

if (!is_dir($destino) && !mkdir($destino, 0777, true)) {
    throw new RuntimeException('Nao foi possivel criar ' . $destino);
}
$arquivoColecao = $destino . '/reforma-tributaria-wiremock.postman_collection.json';
file_put_contents($arquivoColecao, json_encode($colecao, FORMATO_JSON) . "\n");
file_put_contents($destino . '/wiremock-local.postman_environment.json', json_encode($ambiente, FORMATO_JSON) . "\n");

$total = array_sum(array_map(static fn (array $pasta): int => count($pasta['item']), $colecao['item']));
echo "Colecao gerada com {$total} requisicoes em postman/\n";
