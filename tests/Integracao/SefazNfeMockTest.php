<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Integracao;

use DOMDocument;
use DOMXPath;
use DuNf\ReformaTributaria\CalculadoraOficial\RespostaHttp;
use DuNf\ReformaTributaria\CalculadoraOficial\TransporteCurl;
use DuNf\ReformaTributaria\Calculo\CalculadoraIbsCbs;
use DuNf\ReformaTributaria\Calculo\ItemIbsCbs;
use DuNf\ReformaTributaria\Xml\GeradorXmlNfe;

final class SefazNfeMockTest extends WireMockTestCase
{
    private const CHAVE = '35261112345678000195550010000001231000001234';
    private const DIGEST = 'q7Wd0bZ1n0r6Ck2xE5yH8u3pL9sTfA4mVjKcGiQoXzE=';

    public function testStatusDoServicoEmOperacao(): void
    {
        $resposta = $this->enviar('autorizado', 'NFeStatusServico4', $this->envelope(
            '<consStatServ versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">'
            . '<tpAmb>2</tpAmb><cUF>35</cUF><xServ>STATUS</xServ></consStatServ>',
            'NFeStatusServico4'
        ));

        self::assertSame(200, $resposta->status());
        self::assertSame('107', $this->valor($resposta, 'cStat'));
        self::assertSame('35', $this->valor($resposta, 'cUF'));
    }

    public function testNotaAutorizadaDevolveProtocoloComChaveEDigest(): void
    {
        $resposta = $this->enviar('autorizado', 'NFeAutorizacao4', $this->envelopeAutorizacao());

        self::assertSame('100', $this->valor($resposta, 'infProt/cStat'));
        self::assertSame(self::CHAVE, $this->valor($resposta, 'chNFe'));
        self::assertSame(self::DIGEST, $this->valor($resposta, 'digVal'));
        self::assertMatchesRegularExpression('/^135\d{13}$/', $this->valor($resposta, 'nProt'));
    }

    /**
     * @dataProvider rejeicoes
     */
    public function testRejeicoesDaReformaTributaria(string $codigo, string $trechoMotivo): void
    {
        $resposta = $this->enviar('rejeicao-' . $codigo, 'NFeAutorizacao4', $this->envelopeAutorizacao());

        self::assertSame('104', $this->valor($resposta, 'retEnviNFe/cStat'));
        self::assertSame($codigo, $this->valor($resposta, 'infProt/cStat'));
        self::assertStringContainsString($trechoMotivo, $this->valor($resposta, 'infProt/xMotivo'));
        self::assertSame('', $this->valor($resposta, 'nProt'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function rejeicoes(): array
    {
        return [
            '1023 cClassTrib inexistente' => ['1023', 'inexistente'],
            '1024 cClassTrib x CST' => ['1024', 'incompatível com o CST'],
            '1041 IBS UF' => ['1041', 'IBS da UF'],
            '1069 CBS do item' => ['1069', 'Valor da CBS'],
            '1076 total da BC' => ['1076', 'Total da BC'],
            '1085 total do IBS' => ['1085', 'Total do IBS'],
            '1091 total da CBS' => ['1091', 'Total de CBS'],
            '1104 base do item' => ['1104', 'Base de cálculo'],
            '1115 grupo ausente' => ['1115', 'Grupo IBSCBS'],
            '1150 vIBS do item' => ['1150', 'vIBS'],
        ];
    }

    public function testSefazForaDoAr(): void
    {
        $resposta = $this->enviar('fora-do-ar', 'NFeAutorizacao4', $this->envelopeAutorizacao());

        self::assertSame(503, $resposta->status());
    }

    private function envelopeAutorizacao(): string
    {
        $documento = (new CalculadoraIbsCbs())->calcularDocumento(
            [new ItemIbsCbs(1, '000', '000001', '1000.00')],
            new \DateTimeImmutable('2026-11-20')
        );
        $gerador = new GeradorXmlNfe();

        $nfe = '<enviNFe versao="4.00" xmlns="http://www.portalfiscal.inf.br/nfe">'
            . '<idLote>1</idLote><indSinc>1</indSinc>'
            . '<NFe><infNFe Id="NFe' . self::CHAVE . '" versao="4.00">'
            . '<det nItem="1"><imposto>' . $gerador->grupoItemXml($documento->itens()[0]) . '</imposto></det>'
            . '<total>' . $gerador->grupoTotalXml($documento->totais()) . '</total>'
            . '</infNFe><Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><Reference>'
            . '<DigestValue>' . self::DIGEST . '</DigestValue></Reference></SignedInfo></Signature></NFe></enviNFe>';

        return $this->envelope($nfe, 'NFeAutorizacao4');
    }

    private function envelope(string $conteudo, string $servico): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap12:Envelope xmlns:soap12="http://www.w3.org/2003/05/soap-envelope"><soap12:Body>'
            . '<nfeDadosMsg xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/' . $servico . '">'
            . $conteudo
            . '</nfeDadosMsg>'
            . '</soap12:Body></soap12:Envelope>';
    }

    private function enviar(string $cenario, string $servico, string $corpo): RespostaHttp
    {
        return (new TransporteCurl())->enviar(
            'POST',
            $this->urlWireMock . '/sefaz/' . $cenario . '/' . $servico,
            ['Content-Type' => 'application/soap+xml; charset=utf-8'],
            $corpo
        );
    }

    private function valor(RespostaHttp $resposta, string $caminho): string
    {
        $dom = new DOMDocument();
        $dom->loadXML($resposta->corpo());
        $xpath = new DOMXPath($dom);

        $partes = array_map(static function (string $parte): string {
            return '*[local-name()="' . $parte . '"]';
        }, explode('/', $caminho));

        $nos = $xpath->query('//' . implode('/', $partes));

        return $nos !== false && $nos->length > 0 ? trim((string) $nos->item(0)->textContent) : '';
    }
}
