<?php

/**
 * Envia um XML de exemplo para a SEFAZ simulada no WireMock e mostra o retorno.
 *
 * Uso:
 *   php exemplos/enviar-wiremock.php                         # venda no cenario autorizado
 *   php exemplos/enviar-wiremock.php devolucao               # outro exemplo
 *   php exemplos/enviar-wiremock.php venda rejeicao-1091     # outro cenario
 *   php exemplos/enviar-wiremock.php evento-cancelamento     # eventos vao para NFeRecepcaoEvento4
 *
 * Variavel de ambiente WIREMOCK_URL muda o endereco (padrao: http://localhost:8089).
 */

declare(strict_types=1);

use DuNf\ReformaTributaria\CalculadoraOficial\TransporteCurl;

require __DIR__ . '/../vendor/autoload.php';

$exemplo = $argv[1] ?? 'venda';
$cenario = $argv[2] ?? 'autorizado';
$urlWireMock = rtrim((string) (getenv('WIREMOCK_URL') ?: 'http://localhost:8089'), '/');
$arquivo = __DIR__ . '/../wiremock/__files/nfe/soap/' . $exemplo . '.xml';

if (!is_file($arquivo)) {
    fwrite(STDERR, "Exemplo nao encontrado: {$exemplo}. Veja a lista em wiremock/__files/nfe/indice.json\n");
    exit(1);
}

$servico = strpos($exemplo, 'evento-') === 0 ? 'NFeRecepcaoEvento4' : 'NFeAutorizacao4';
$url = $urlWireMock . '/sefaz/' . $cenario . '/' . $servico;

$resposta = (new TransporteCurl())->enviar(
    'POST',
    $url,
    ['Content-Type' => 'application/soap+xml; charset=utf-8'],
    (string) file_get_contents($arquivo)
);

echo "POST {$url}", PHP_EOL, "HTTP {$resposta->status()}", PHP_EOL;

if ($resposta->status() !== 200) {
    exit(1);
}

$dom = new DOMDocument();
$dom->loadXML($resposta->corpo());
$xpath = new DOMXPath($dom);

foreach (['chNFe', 'cStat', 'xMotivo', 'nProt', 'digVal', 'xEvento'] as $campo) {
    $nos = $xpath->query('//*[local-name()="infProt" or local-name()="infEvento"]/*[local-name()="' . $campo . '"]');
    if ($nos !== false && $nos->length > 0) {
        printf("%-8s %s%s", $campo, $nos->item(0)->textContent, PHP_EOL);
    }
}
