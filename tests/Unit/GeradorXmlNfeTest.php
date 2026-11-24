<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Unit;

use DOMDocument;
use DuNf\ReformaTributaria\Calculo\Aliquotas;
use DuNf\ReformaTributaria\Calculo\CalculadoraIbsCbs;
use DuNf\ReformaTributaria\Calculo\Diferimento;
use DuNf\ReformaTributaria\Calculo\ItemIbsCbs;
use DuNf\ReformaTributaria\Calculo\ResultadoDocumento;
use DuNf\ReformaTributaria\Xml\GeradorXmlNfe;
use PHPUnit\Framework\TestCase;

/**
 * Valida o XML gerado contra o schema de grupos da NF-e distribuido com a
 * Calculadora de Tributos oficial (tests/Fixtures/xsd/nfe/grupo.xsd).
 */
final class GeradorXmlNfeTest extends TestCase
{
    private const SCHEMA = __DIR__ . '/../Fixtures/xsd/nfe/grupo.xsd';

    public function testGrupoDoItemComReducao(): void
    {
        $resultado = (new CalculadoraIbsCbs())->calcularItem(
            new ItemIbsCbs(1, '200', '200028', '1200.00', new Aliquotas('8.8')),
            new \DateTimeImmutable('2027-03-10')
        );

        $xml = (new GeradorXmlNfe())->grupoItemXml($resultado);

        self::assertStringContainsString('<CST>200</CST><cClassTrib>200028</cClassTrib>', $xml);
        self::assertStringContainsString(
            '<gIBSUF><pIBSUF>0.05</pIBSUF><gRed><pRedAliq>60.00</pRedAliq><pAliqEfet>0.02</pAliqEfet></gRed>'
            . '<vIBSUF>0.24</vIBSUF></gIBSUF>',
            $xml
        );
        self::assertStringContainsString('<vIBS>0.48</vIBS>', $xml);
        self::assertStringContainsString('<pCBS>8.80</pCBS>', $xml);
        self::assertStringContainsString('<vCBS>42.24</vCBS>', $xml);
    }

    public function testDocumentoCompletoPassaNoSchemaOficial(): void
    {
        $documento = (new CalculadoraIbsCbs())->calcularDocumento([
            new ItemIbsCbs(1, '000', '000001', '1000.00'),
            new ItemIbsCbs(2, '200', '200028', '1200.00'),
            new ItemIbsCbs(3, '410', '410001', '50.00'),
            new ItemIbsCbs(4, '510', '510001', '300.00', null, new Diferimento('100', '100', '100')),
        ], new \DateTimeImmutable('2026-08-03'));

        $xml = $this->montarInfNfe($documento);

        self::assertTrue($this->validar($xml));
    }

    public function testTotalSemItensTributadosPassaNoSchemaOficial(): void
    {
        $documento = (new CalculadoraIbsCbs())->calcularDocumento(
            [new ItemIbsCbs(1, '410', '410001', '50.00')],
            new \DateTimeImmutable('2026-08-03')
        );

        $xml = $this->montarInfNfe($documento);

        self::assertStringContainsString('<IBSCBSTot><vBCIBSCBS>0.00</vBCIBSCBS></IBSCBSTot>', $xml);
        self::assertTrue($this->validar($xml));
    }

    private function montarInfNfe(ResultadoDocumento $documento): string
    {
        $gerador = new GeradorXmlNfe();
        $dom = new DOMDocument('1.0', 'UTF-8');
        $infNfe = $dom->createElementNS(GeradorXmlNfe::NAMESPACE_NFE, 'infNFe');
        $dom->appendChild($infNfe);

        foreach ($documento->itens() as $item) {
            $det = $dom->createElementNS(GeradorXmlNfe::NAMESPACE_NFE, 'det');
            $det->setAttribute('nItem', (string) $item->numero());
            $imposto = $dom->createElementNS(GeradorXmlNfe::NAMESPACE_NFE, 'imposto');
            $imposto->appendChild($gerador->grupoItem($item, $dom));
            $det->appendChild($imposto);
            $infNfe->appendChild($det);
        }

        $total = $dom->createElementNS(GeradorXmlNfe::NAMESPACE_NFE, 'total');
        $total->appendChild($gerador->grupoTotal($documento->totais(), $dom));
        $infNfe->appendChild($total);

        return (string) $dom->saveXML();
    }

    private function validar(string $xml): bool
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $usarErrosInternos = libxml_use_internal_errors(true);
        $valido = $dom->schemaValidate(self::SCHEMA);
        $erros = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($usarErrosInternos);

        $mensagens = array_map(static function (\LibXMLError $erro): string {
            return trim($erro->message);
        }, $erros);

        self::assertSame([], $mensagens, $xml);

        return $valido;
    }
}
