<?php

/**
 * Gera os XML de exemplo de NF-e e NFC-e com IBS/CBS usados pelos mocks WireMock.
 *
 * Saida:
 * - wiremock/__files/nfe/xml/{exemplo}.xml           NF-e completa (NFe assinada para teste)
 * - wiremock/__files/nfe/soap/{exemplo}.xml          envelope SOAP pronto para NFeAutorizacao4
 * - wiremock/__files/nfe/soap/evento-{tipo}.xml      envelope SOAP pronto para NFeRecepcaoEvento4
 * - wiremock/__files/nfe/indice.json                 lista dos exemplos (GET /exemplos/nfe no WireMock)
 *
 * Os valores de IBS/CBS sao calculados pela biblioteca. A assinatura e apenas estrutural:
 * o DigestValue e o hash real do infNFe, mas SignatureValue e certificado sao ficticios.
 *
 * Uso: php bin/gerar-xml-exemplos.php
 */

declare(strict_types=1);

use EduardoEvaristo\ReformaTributaria\BaseCalculo\CalculadoraBaseCalculo;
use EduardoEvaristo\ReformaTributaria\BaseCalculo\ComponentesBaseCalculo;
use EduardoEvaristo\ReformaTributaria\Calculo\CalculadoraIbsCbs;
use EduardoEvaristo\ReformaTributaria\Calculo\ItemIbsCbs;
use EduardoEvaristo\ReformaTributaria\Suporte\Numero;
use EduardoEvaristo\ReformaTributaria\Xml\GeradorXmlNfe;

require __DIR__ . '/../vendor/autoload.php';

const NS_NFE = 'http://www.portalfiscal.inf.br/nfe';
const NS_DSIG = 'http://www.w3.org/2000/09/xmldsig#';
const CNPJ_EMITENTE = '11222333000181';
const CNPJ_DESTINATARIO = '99888777000163';
const DATA_EMISSAO = '2026-11-20T10:00:00-03:00';
const CNPJ_RESP_TECNICO = '11222333000181';

$destino = __DIR__ . '/../wiremock/__files/nfe';

/**
 * Cada exemplo: modelo, finalidade, tipo de operacao, CFOP, itens e chave referenciada.
 */
$exemplos = [
    'venda' => [
        'descricao' => 'Venda de mercadoria tributada integralmente (CST 000)',
        'modelo' => 55, 'numero' => 1001, 'finNFe' => 1, 'tpNF' => 1, 'natOp' => 'VENDA DE MERCADORIA',
        'itens' => [
            ['NOTEBOOK 15 POLEGADAS', '84713012', '5102', '000', '000001', '1000.00'],
        ],
    ],
    'venda-com-reducao' => [
        'descricao' => 'Venda com reducao de 60% (CST 200, higiene pessoal - Anexo VIII)',
        'modelo' => 55, 'numero' => 1002, 'finNFe' => 1, 'tpNF' => 1, 'natOp' => 'VENDA DE MERCADORIA',
        'itens' => [
            ['CREME DENTAL 90G', '33061000', '5102', '200', '200035', '500.00'],
            ['NOTEBOOK 15 POLEGADAS', '84713012', '5102', '000', '000001', '1000.00'],
        ],
    ],
    'venda-nfce' => [
        'descricao' => 'Venda ao consumidor final em NFC-e (modelo 65)',
        'modelo' => 65, 'numero' => 2001, 'finNFe' => 1, 'tpNF' => 1, 'natOp' => 'VENDA AO CONSUMIDOR',
        'itens' => [
            ['CREME DENTAL 90G', '33061000', '5102', '200', '200035', '15.90'],
            ['FONE DE OUVIDO', '85183000', '5102', '000', '000001', '89.90'],
        ],
    ],
    'devolucao' => [
        'descricao' => 'Devolucao de venda (finNFe 4), referenciando a NF-e de venda',
        'modelo' => 55, 'numero' => 1003, 'finNFe' => 4, 'tpNF' => 0, 'natOp' => 'DEVOLUCAO DE VENDA',
        'semPagamento' => true,
        'referencia' => 'venda',
        'itens' => [
            ['NOTEBOOK 15 POLEGADAS', '84713012', '1202', '000', '000001', '1000.00'],
        ],
    ],
    'bonificacao' => [
        'descricao' => 'Remessa em bonificacao sem IBS/CBS (CST 410, cClassTrib 410001)',
        'modelo' => 55, 'numero' => 1004, 'finNFe' => 1, 'tpNF' => 1, 'natOp' => 'REMESSA EM BONIFICACAO',
        'semPagamento' => true,
        'itens' => [
            ['CREME DENTAL 90G', '33061000', '5910', '410', '410001', '50.00'],
        ],
    ],
    'transferencia' => [
        'descricao' => 'Transferencia entre estabelecimentos do mesmo contribuinte (CST 410, cClassTrib 410002)',
        'modelo' => 55, 'numero' => 1005, 'finNFe' => 1, 'tpNF' => 1, 'natOp' => 'TRANSFERENCIA DE MERCADORIA',
        'semPagamento' => true,
        'itens' => [
            ['NOTEBOOK 15 POLEGADAS', '84713012', '5152', '410', '410002', '1000.00'],
        ],
    ],
];

$chaves = [];
$gerados = [];
$indice = [];

foreach ($exemplos as $nome => $exemplo) {
    $chaves[$nome] = montarChave($exemplo['modelo'], $exemplo['numero']);
}

foreach ($exemplos as $nome => $exemplo) {
    $exemplo['chave'] = $chaves[$nome];
    $exemplo['chaveReferenciada'] = isset($exemplo['referencia']) ? $chaves[$exemplo['referencia']] : null;

    $nfe = montarNfe($exemplo);
    salvar($destino . '/xml/' . $nome . '.xml', $nfe->saveXML());
    salvar($destino . '/soap/' . $nome . '.xml', envelope('NFeAutorizacao4', loteAutorizacao($nfe)));
    $gerados[] = sprintf('%-18s %s  %s', $nome, $exemplo['chave'], $exemplo['descricao']);
    $indice[] = [
        'nome' => $nome,
        'descricao' => $exemplo['descricao'],
        'modelo' => $exemplo['modelo'],
        'finNFe' => $exemplo['finNFe'],
        'chave' => $exemplo['chave'],
        'xml' => '/exemplos/nfe/xml/' . $nome . '.xml',
        'soap' => '/exemplos/nfe/soap/' . $nome . '.xml',
        'servico' => 'NFeAutorizacao4',
    ];
}

salvar($destino . '/soap/evento-cancelamento.xml', envelope('NFeRecepcaoEvento4', evento(
    $chaves['venda'],
    '110111',
    '<descEvento>Cancelamento</descEvento><nProt>135260000000001</nProt>'
    . '<xJust>Pedido cancelado pelo cliente antes da entrega</xJust>'
)));

salvar($destino . '/soap/evento-carta-correcao.xml', envelope('NFeRecepcaoEvento4', evento(
    $chaves['venda'],
    '110110',
    '<descEvento>Carta de Correcao</descEvento>'
    . '<xCorrecao>Correcao do complemento do endereco do destinatario para Sala 12</xCorrecao>'
    . '<xCondUso>A Carta de Correcao e disciplinada pelo paragrafo 1o-A do art. 7o do Convenio S/N, '
    . 'de 15 de dezembro de 1970 e pode ser utilizada para regularizacao de erro ocorrido na emissao de '
    . 'documento fiscal, desde que o erro nao esteja relacionado com: I - as variaveis que determinam o '
    . 'valor do imposto tais como: base de calculo, aliquota, diferenca de preco, quantidade, valor da '
    . 'operacao ou da prestacao; II - a correcao de dados cadastrais que implique mudanca do remetente '
    . 'ou do destinatario; III - a data de emissao ou de saida.</xCondUso>'
)));

$indice[] = [
    'nome' => 'evento-cancelamento',
    'descricao' => 'Cancelamento (110111) da NF-e de venda',
    'chave' => $chaves['venda'],
    'soap' => '/exemplos/nfe/soap/evento-cancelamento.xml',
    'servico' => 'NFeRecepcaoEvento4',
];
$indice[] = [
    'nome' => 'evento-carta-correcao',
    'descricao' => 'Carta de correcao (110110) da NF-e de venda',
    'chave' => $chaves['venda'],
    'soap' => '/exemplos/nfe/soap/evento-carta-correcao.xml',
    'servico' => 'NFeRecepcaoEvento4',
];

salvar(
    $destino . '/indice.json',
    json_encode(['exemplos' => $indice], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);

echo implode(PHP_EOL, $gerados), PHP_EOL;

function montarChave(int $modelo, int $numero): string
{
    $cNF = str_pad((string) ($numero * 7919 % 100000000), 8, '0', STR_PAD_LEFT);
    $base = '35' . '2611' . CNPJ_EMITENTE . $modelo . '001' . str_pad((string) $numero, 9, '0', STR_PAD_LEFT)
        . '1' . $cNF;

    return $base . digitoVerificador($base);
}

function digitoVerificador(string $base): string
{
    $soma = 0;
    $peso = 2;
    for ($i = strlen($base) - 1; $i >= 0; $i--) {
        $soma += (int) $base[$i] * $peso;
        $peso = $peso === 9 ? 2 : $peso + 1;
    }

    $resto = $soma % 11;

    return (string) ($resto < 2 ? 0 : 11 - $resto);
}

/**
 * @param array<string, mixed> $exemplo
 */
function montarNfe(array $exemplo): DOMDocument
{
    $modelo = $exemplo['modelo'];
    $chave = $exemplo['chave'];
    $data = new DateTimeImmutable(DATA_EMISSAO);

    $itensCalculo = [];
    $linhas = [];
    foreach ($exemplo['itens'] as $indice => $item) {
        list($descricao, $ncm, $cfop, $cst, $cClassTrib, $valor) = $item;
        $tributado = $cst !== '410';
        $vICMS = $tributado ? Numero::valorFiscal(Numero::aplicarPercentual($valor, '18')) : '0.00';
        $basePis = Numero::subtrair($valor, $vICMS);
        $vPIS = $tributado ? Numero::valorFiscal(Numero::aplicarPercentual($basePis, '1.65')) : '0.00';
        $vCOFINS = $tributado ? Numero::valorFiscal(Numero::aplicarPercentual($basePis, '7.6')) : '0.00';

        $base = (new CalculadoraBaseCalculo())->calcular(
            new ComponentesBaseCalculo(['vProd' => $valor, 'vICMS' => $vICMS, 'vPIS' => $vPIS, 'vCOFINS' => $vCOFINS]),
            2026,
            CalculadoraBaseCalculo::NATUREZA_BEM,
            $modelo
        );

        $itensCalculo[] = new ItemIbsCbs($indice + 1, $cst, $cClassTrib, $base->baseRegimeRegular());
        $linhas[] = compact('descricao', 'ncm', 'cfop', 'valor', 'vICMS', 'vPIS', 'vCOFINS', 'tributado', 'basePis');
    }

    $documento = (new CalculadoraIbsCbs())->calcularDocumento($itensCalculo, $data);
    $gerador = new GeradorXmlNfe();

    $dom = new DOMDocument('1.0', 'UTF-8');
    $nfe = $dom->createElementNS(NS_NFE, 'NFe');
    $dom->appendChild($nfe);
    $inf = el($nfe, 'infNFe');
    $inf->setAttribute('Id', 'NFe' . $chave);
    $inf->setAttribute('versao', '4.00');

    $ide = el($inf, 'ide');
    el($ide, 'cUF', '35');
    el($ide, 'cNF', substr($chave, 35, 8));
    el($ide, 'natOp', $exemplo['natOp']);
    el($ide, 'mod', (string) $modelo);
    el($ide, 'serie', '1');
    el($ide, 'nNF', (string) $exemplo['numero']);
    el($ide, 'dhEmi', DATA_EMISSAO);
    el($ide, 'tpNF', (string) $exemplo['tpNF']);
    el($ide, 'idDest', '1');
    el($ide, 'cMunFG', '3550308');
    el($ide, 'tpImp', $modelo === 65 ? '4' : '1');
    el($ide, 'tpEmis', '1');
    el($ide, 'cDV', substr($chave, -1));
    el($ide, 'tpAmb', '2');
    el($ide, 'finNFe', (string) $exemplo['finNFe']);
    el($ide, 'indFinal', $modelo === 65 ? '1' : '0');
    el($ide, 'indPres', '1');
    el($ide, 'procEmi', '0');
    el($ide, 'verProc', 'reforma-trib-api');
    if ($exemplo['chaveReferenciada'] !== null) {
        $ref = el($ide, 'NFref');
        el($ref, 'refNFe', $exemplo['chaveReferenciada']);
    }

    $emit = el($inf, 'emit');
    el($emit, 'CNPJ', CNPJ_EMITENTE);
    el($emit, 'xNome', 'EMPRESA EXEMPLO LTDA');
    endereco(el($emit, 'enderEmit'), 'AVENIDA PAULISTA', '1000');
    el($emit, 'IE', '111111111119');
    el($emit, 'CRT', '3');

    if ($modelo === 55) {
        $dest = el($inf, 'dest');
        el($dest, 'CNPJ', CNPJ_DESTINATARIO);
        el($dest, 'xNome', 'NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL');
        endereco(el($dest, 'enderDest'), 'RUA DOS TESTES', '200');
        el($dest, 'indIEDest', '1');
        el($dest, 'IE', '222222222226');
    }

    $totais = ['vBC' => '0', 'vICMS' => '0', 'vProd' => '0', 'vPIS' => '0', 'vCOFINS' => '0'];
    foreach ($linhas as $indice => $linha) {
        $det = el($inf, 'det');
        $det->setAttribute('nItem', (string) ($indice + 1));

        $prod = el($det, 'prod');
        el($prod, 'cProd', str_pad((string) ($indice + 1), 4, '0', STR_PAD_LEFT));
        el($prod, 'cEAN', 'SEM GTIN');
        el($prod, 'xProd', $modelo === 65 && $indice === 0
            ? 'NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL'
            : $linha['descricao']);
        el($prod, 'NCM', $linha['ncm']);
        el($prod, 'CFOP', $linha['cfop']);
        el($prod, 'uCom', 'UN');
        el($prod, 'qCom', '1.0000');
        el($prod, 'vUnCom', $linha['valor']);
        el($prod, 'vProd', $linha['valor']);
        el($prod, 'cEANTrib', 'SEM GTIN');
        el($prod, 'uTrib', 'UN');
        el($prod, 'qTrib', '1.0000');
        el($prod, 'vUnTrib', $linha['valor']);
        el($prod, 'indTot', '1');

        $imposto = el($det, 'imposto');
        $icms = el(el($imposto, 'ICMS'), $linha['tributado'] ? 'ICMS00' : 'ICMS40');
        el($icms, 'orig', '0');
        if ($linha['tributado']) {
            el($icms, 'CST', '00');
            el($icms, 'modBC', '3');
            el($icms, 'vBC', $linha['valor']);
            el($icms, 'pICMS', '18.00');
            el($icms, 'vICMS', $linha['vICMS']);
            $totais['vBC'] = Numero::somar($totais['vBC'], $linha['valor']);
        } else {
            el($icms, 'CST', '41');
        }

        if ($linha['tributado']) {
            $pis = el(el($imposto, 'PIS'), 'PISAliq');
            el($pis, 'CST', '01');
            el($pis, 'vBC', Numero::valorFiscal($linha['basePis']));
            el($pis, 'pPIS', '1.65');
            el($pis, 'vPIS', $linha['vPIS']);
            $cofins = el(el($imposto, 'COFINS'), 'COFINSAliq');
            el($cofins, 'CST', '01');
            el($cofins, 'vBC', Numero::valorFiscal($linha['basePis']));
            el($cofins, 'pCOFINS', '7.60');
            el($cofins, 'vCOFINS', $linha['vCOFINS']);
        } else {
            el(el(el($imposto, 'PIS'), 'PISNT'), 'CST', '08');
            el(el(el($imposto, 'COFINS'), 'COFINSNT'), 'CST', '08');
        }

        $imposto->appendChild($gerador->grupoItem($documento->itens()[$indice], $dom));

        $totais['vICMS'] = Numero::somar($totais['vICMS'], $linha['vICMS']);
        $totais['vProd'] = Numero::somar($totais['vProd'], $linha['valor']);
        $totais['vPIS'] = Numero::somar($totais['vPIS'], $linha['vPIS']);
        $totais['vCOFINS'] = Numero::somar($totais['vCOFINS'], $linha['vCOFINS']);
    }

    $total = el($inf, 'total');
    $icmsTot = el($total, 'ICMSTot');
    el($icmsTot, 'vBC', Numero::valorFiscal($totais['vBC']));
    el($icmsTot, 'vICMS', Numero::valorFiscal($totais['vICMS']));
    foreach (['vICMSDeson', 'vFCP', 'vBCST', 'vST', 'vFCPST', 'vFCPSTRet'] as $campo) {
        el($icmsTot, $campo, '0.00');
    }
    el($icmsTot, 'vProd', Numero::valorFiscal($totais['vProd']));
    foreach (['vFrete', 'vSeg', 'vDesc', 'vII', 'vIPI', 'vIPIDevol'] as $campo) {
        el($icmsTot, $campo, '0.00');
    }
    el($icmsTot, 'vPIS', Numero::valorFiscal($totais['vPIS']));
    el($icmsTot, 'vCOFINS', Numero::valorFiscal($totais['vCOFINS']));
    el($icmsTot, 'vOutro', '0.00');
    el($icmsTot, 'vNF', Numero::valorFiscal($totais['vProd']));
    $total->appendChild($gerador->grupoTotal($documento->totais(), $dom));

    el(el($inf, 'transp'), 'modFrete', '9');

    $detPag = el(el($inf, 'pag'), 'detPag');
    $semPagamento = !empty($exemplo['semPagamento']);
    el($detPag, 'tPag', $semPagamento ? '90' : '01');
    el($detPag, 'vPag', $semPagamento ? '0.00' : Numero::valorFiscal($totais['vProd']));

    el(el($inf, 'infAdic'), 'infCpl', 'XML de exemplo gerado pela biblioteca reforma-tributaria-api para testes. '
        . 'Sem valor fiscal.');

    $resp = el($inf, 'infRespTec');
    el($resp, 'CNPJ', CNPJ_RESP_TECNICO);
    el($resp, 'xContato', 'Eduardo Evaristo');
    el($resp, 'email', 'contato@exemplo.com.br');
    el($resp, 'fone', '11999999999');

    if ($modelo === 65) {
        $supl = el($nfe, 'infNFeSupl');
        el($supl, 'qrCode', 'https://www.homologacao.nfce.fazenda.sp.gov.br/qrcode?p=' . $chave . '|2|2|1|'
            . strtoupper(sha1($chave)));
        el($supl, 'urlChave', 'https://www.homologacao.nfce.fazenda.sp.gov.br/consulta');
    }

    assinarParaTeste($dom, $inf, $chave);

    return $dom;
}

function endereco(DOMElement $endereco, string $logradouro, string $numero): void
{
    el($endereco, 'xLgr', $logradouro);
    el($endereco, 'nro', $numero);
    el($endereco, 'xBairro', 'CENTRO');
    el($endereco, 'cMun', '3550308');
    el($endereco, 'xMun', 'SAO PAULO');
    el($endereco, 'UF', 'SP');
    el($endereco, 'CEP', '01310100');
    el($endereco, 'cPais', '1058');
    el($endereco, 'xPais', 'BRASIL');
}

/**
 * Assinatura estrutural: DigestValue real (SHA-1 do infNFe canonicalizado),
 * SignatureValue e certificado ficticios. Nao passa na validacao de uma SEFAZ real.
 */
function assinarParaTeste(DOMDocument $dom, DOMElement $alvo, string $chave): void
{
    $digest = base64_encode(sha1($alvo->C14N(false, false), true));

    $assinatura = $dom->createElementNS(NS_DSIG, 'Signature');
    $dom->documentElement->appendChild($assinatura);
    $signedInfo = elNs($assinatura, NS_DSIG, 'SignedInfo');
    elNs($signedInfo, NS_DSIG, 'CanonicalizationMethod')
        ->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
    elNs($signedInfo, NS_DSIG, 'SignatureMethod')
        ->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
    $reference = elNs($signedInfo, NS_DSIG, 'Reference');
    $reference->setAttribute('URI', '#NFe' . $chave);
    $transforms = elNs($reference, NS_DSIG, 'Transforms');
    elNs($transforms, NS_DSIG, 'Transform')
        ->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
    elNs($transforms, NS_DSIG, 'Transform')
        ->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
    elNs($reference, NS_DSIG, 'DigestMethod')->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
    elNs($reference, NS_DSIG, 'DigestValue', $digest);
    elNs($assinatura, NS_DSIG, 'SignatureValue', base64_encode('ASSINATURA FICTICIA PARA TESTES - ' . $chave));
    $x509 = elNs(elNs($assinatura, NS_DSIG, 'KeyInfo'), NS_DSIG, 'X509Data');
    elNs($x509, NS_DSIG, 'X509Certificate', base64_encode('CERTIFICADO FICTICIO PARA TESTES'));
}

function loteAutorizacao(DOMDocument $nfe): string
{
    $xmlNfe = $nfe->saveXML($nfe->documentElement);

    return '<enviNFe xmlns="' . NS_NFE . '" versao="4.00"><idLote>1</idLote><indSinc>1</indSinc>'
        . $xmlNfe . '</enviNFe>';
}

function evento(string $chave, string $tipo, string $detalhe): string
{
    $id = 'ID' . $tipo . $chave . '01';

    return '<envEvento xmlns="' . NS_NFE . '" versao="1.00"><idLote>1</idLote>'
        . '<evento versao="1.00"><infEvento Id="' . $id . '"><cOrgao>35</cOrgao><tpAmb>2</tpAmb>'
        . '<CNPJ>' . CNPJ_EMITENTE . '</CNPJ><chNFe>' . $chave . '</chNFe>'
        . '<dhEvento>2026-11-20T11:00:00-03:00</dhEvento><tpEvento>' . $tipo . '</tpEvento>'
        . '<nSeqEvento>1</nSeqEvento><verEvento>1.00</verEvento>'
        . '<detEvento versao="1.00">' . $detalhe . '</detEvento></infEvento>'
        . '<Signature xmlns="' . NS_DSIG . '"><SignedInfo><Reference URI="#' . $id . '">'
        . '<DigestValue>' . base64_encode(sha1($id, true)) . '</DigestValue></Reference></SignedInfo>'
        . '</Signature></evento></envEvento>';
}

function envelope(string $servico, string $conteudo): string
{
    return '<?xml version="1.0" encoding="utf-8"?>' . "\n"
        . '<soap12:Envelope xmlns:soap12="http://www.w3.org/2003/05/soap-envelope"><soap12:Body>'
        . '<nfeDadosMsg xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/' . $servico . '">' . $conteudo
        . '</nfeDadosMsg></soap12:Body></soap12:Envelope>' . "\n";
}

function el(DOMElement $pai, string $nome, ?string $valor = null): DOMElement
{
    return elNs($pai, NS_NFE, $nome, $valor);
}

function elNs(DOMElement $pai, string $namespace, string $nome, ?string $valor = null): DOMElement
{
    $elemento = $pai->ownerDocument->createElementNS($namespace, $nome);
    if ($valor !== null) {
        $elemento->appendChild($pai->ownerDocument->createTextNode($valor));
    }
    $pai->appendChild($elemento);

    return $elemento;
}

function salvar(string $caminho, string $conteudo): void
{
    if (!is_dir(dirname($caminho))) {
        mkdir(dirname($caminho), 0777, true);
    }

    file_put_contents($caminho, $conteudo);
}
