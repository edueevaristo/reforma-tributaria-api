<?php

declare(strict_types=1);

namespace EduardoEvaristo\ReformaTributaria\Tests\Unit;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

/**
 * Confere os XML de exemplo de wiremock/__files/nfe (gerados por bin/gerar-xml-exemplos.php).
 */
final class XmlExemplosTest extends TestCase
{
    private const PASTA = __DIR__ . '/../../wiremock/__files/nfe';
    private const SCHEMA = __DIR__ . '/../Fixtures/xsd/nfe/nota.xsd';

    /**
     * @dataProvider exemplos
     */
    public function testNfePassaNoSchemaCompletoDaNfe(string $arquivo): void
    {
        $soap = new DOMDocument();
        $soap->load(self::PASTA . '/soap/' . basename($arquivo));
        $enviNfe = $soap->getElementsByTagNameNS('http://www.portalfiscal.inf.br/nfe', 'enviNFe')->item(0);

        $lote = new DOMDocument();
        $lote->appendChild($lote->importNode($enviNfe, true));
        $lote->loadXML((string) $lote->saveXML());

        $usarErrosInternos = libxml_use_internal_errors(true);
        $valido = $lote->schemaValidate(self::SCHEMA);
        $erros = array_map(static function (\LibXMLError $erro): string {
            return trim($erro->message);
        }, libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors($usarErrosInternos);

        self::assertSame([], $erros);
        self::assertTrue($valido);
    }

    /**
     * @dataProvider exemplos
     */
    public function testChaveTemDigitoVerificadorValido(string $arquivo): void
    {
        $chave = $this->valor($arquivo, '//*[local-name()="infNFe"]/@Id');
        $chave = substr($chave, 3);

        self::assertSame(44, strlen($chave));

        $soma = 0;
        $peso = 2;
        for ($i = 42; $i >= 0; $i--) {
            $soma += (int) $chave[$i] * $peso;
            $peso = $peso === 9 ? 2 : $peso + 1;
        }
        $resto = $soma % 11;

        self::assertSame((string) ($resto < 2 ? 0 : 11 - $resto), substr($chave, -1));
    }

    /**
     * @dataProvider exemplos
     */
    public function testDigestValueCorrespondeAoInfNfe(string $arquivo): void
    {
        $dom = new DOMDocument();
        $dom->load($arquivo);
        $infNfe = $dom->getElementsByTagNameNS('http://www.portalfiscal.inf.br/nfe', 'infNFe')->item(0);

        $esperado = base64_encode(sha1($infNfe->C14N(false, false), true));

        self::assertSame($esperado, $this->valor($arquivo, '//*[local-name()="DigestValue"]'));
    }

    /**
     * @dataProvider exemplos
     */
    public function testTotalDaCbsIgualASomaDosItens(string $arquivo): void
    {
        $dom = new DOMDocument();
        $dom->load($arquivo);
        $xpath = new DOMXPath($dom);

        $soma = '0.00';
        foreach ($xpath->query('//*[local-name()="det"]//*[local-name()="vCBS"]') as $no) {
            $soma = bcadd($soma, $no->textContent, 2);
        }

        $total = $xpath->query('//*[local-name()="IBSCBSTot"]//*[local-name()="vCBS"]');
        $totalCbs = $total !== false && $total->length > 0 ? $total->item(0)->textContent : '0.00';

        self::assertSame($soma, $totalCbs);
    }

    public function testIndiceListaTodosOsExemplos(): void
    {
        $indice = json_decode((string) file_get_contents(self::PASTA . '/indice.json'), true);

        foreach ($indice['exemplos'] as $exemplo) {
            self::assertFileExists(self::PASTA . '/soap/' . $exemplo['nome'] . '.xml');
        }

        self::assertCount(count($this->exemplos()) + 2, $indice['exemplos']);
    }

    /**
     * @return array<string, array{string}>
     */
    public function exemplos(): array
    {
        $lista = [];
        foreach (glob(self::PASTA . '/xml/*.xml') ?: [] as $arquivo) {
            $lista[basename($arquivo, '.xml')] = [$arquivo];
        }

        return $lista;
    }

    private function valor(string $arquivo, string $consulta): string
    {
        $dom = new DOMDocument();
        $dom->load($arquivo);
        $nos = (new DOMXPath($dom))->query($consulta);

        return $nos !== false && $nos->length > 0 ? (string) $nos->item(0)->textContent : '';
    }
}
