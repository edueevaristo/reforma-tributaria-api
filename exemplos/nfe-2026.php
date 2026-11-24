<?php

/**
 * Exemplo completo: base de calculo, calculo do IBS/CBS e grupos XML de uma NF-e em 2026.
 *
 * Execute com: php exemplos/nfe-2026.php
 */

declare(strict_types=1);

use DuNf\ReformaTributaria\BaseCalculo\CalculadoraBaseCalculo;
use DuNf\ReformaTributaria\BaseCalculo\ComponentesBaseCalculo;
use DuNf\ReformaTributaria\BaseCalculo\ModeloDocumento;
use DuNf\ReformaTributaria\BaseCalculo\Natureza;
use DuNf\ReformaTributaria\Calculo\CalculadoraIbsCbs;
use DuNf\ReformaTributaria\Calculo\ItemIbsCbs;
use DuNf\ReformaTributaria\Xml\GeradorXmlNfe;

require __DIR__ . '/../vendor/autoload.php';

$dataFatoGerador = new DateTimeImmutable('2026-11-20');

$base = (new CalculadoraBaseCalculo())->calcular(
    new ComponentesBaseCalculo([
        'vProd' => '1000.00',
        'vDesc' => '50.00',
        'vPIS' => '15.68',
        'vCOFINS' => '72.20',
        'vICMS' => '171.00',
    ]),
    2026,
    Natureza::bem(),
    ModeloDocumento::nfe()
);

$documento = (new CalculadoraIbsCbs())->calcularDocumento([
    new ItemIbsCbs(1, '000', '000001', $base->baseRegimeRegular()),
    new ItemIbsCbs(2, '200', '200028', '1200.00'),
], $dataFatoGerador);

$gerador = new GeradorXmlNfe();

foreach ($documento->itens() as $item) {
    echo $gerador->grupoItemXml($item), PHP_EOL;
}

echo $gerador->grupoTotalXml($documento->totais()), PHP_EOL;
