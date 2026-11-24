<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Integracao;

use DOMDocument;
use DOMXPath;
use DuNf\ReformaTributaria\CalculadoraOficial\RespostaHttp;
use DuNf\ReformaTributaria\CalculadoraOficial\TransporteCurl;

/**
 * Baixa os XML de exemplo servidos pelo WireMock e envia cada um para a SEFAZ simulada.
 */
final class ExemplosXmlMockTest extends WireMockTestCase
{
    public function testIndiceListaOsExemplos(): void
    {
        $resposta = $this->requisitar('GET', '/exemplos/nfe');
        $indice = json_decode($resposta->corpo(), true);

        self::assertSame(200, $resposta->status());
        self::assertGreaterThanOrEqual(8, count($indice['exemplos']));
    }

    /**
     * @dataProvider notas
     */
    public function testExemploEAutorizadoNaSefazSimulada(string $nome): void
    {
        $xml = $this->requisitar('GET', '/exemplos/nfe/xml/' . $nome . '.xml');
        self::assertSame(200, $xml->status());
        $chave = substr($this->valor($xml, '//*[local-name()="infNFe"]/@Id'), 3);

        $envelope = $this->requisitar('GET', '/exemplos/nfe/soap/' . $nome . '.xml');
        $retorno = $this->requisitar('POST', '/sefaz/autorizado/NFeAutorizacao4', $envelope->corpo());

        self::assertSame('100', $this->valor($retorno, '//*[local-name()="infProt"]/*[local-name()="cStat"]'));
        self::assertSame($chave, $this->valor($retorno, '//*[local-name()="chNFe"]'));
        self::assertSame(
            $this->valor($xml, '//*[local-name()="DigestValue"]'),
            $this->valor($retorno, '//*[local-name()="digVal"]')
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public function notas(): array
    {
        return [
            'venda' => ['venda'],
            'venda-com-reducao' => ['venda-com-reducao'],
            'venda-nfce' => ['venda-nfce'],
            'devolucao' => ['devolucao'],
            'bonificacao' => ['bonificacao'],
            'transferencia' => ['transferencia'],
        ];
    }

    /**
     * @dataProvider eventos
     */
    public function testEventoERegistrado(string $nome, string $tipo, string $descricao): void
    {
        $envelope = $this->requisitar('GET', '/exemplos/nfe/soap/' . $nome . '.xml');
        $retorno = $this->requisitar('POST', '/sefaz/autorizado/NFeRecepcaoEvento4', $envelope->corpo());

        self::assertSame('128', $this->valor($retorno, '//*[local-name()="retEnvEvento"]/*[local-name()="cStat"]'));
        self::assertSame('135', $this->valor($retorno, '//*[local-name()="infEvento"]/*[local-name()="cStat"]'));
        self::assertSame($tipo, $this->valor($retorno, '//*[local-name()="tpEvento"]'));
        self::assertSame($descricao, $this->valor($retorno, '//*[local-name()="xEvento"]'));
        self::assertMatchesRegularExpression('/^135\d{13}$/', $this->valor($retorno, '//*[local-name()="nProt"]'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public function eventos(): array
    {
        return [
            'cancelamento' => ['evento-cancelamento', '110111', 'Cancelamento registrado'],
            'carta de correcao' => ['evento-carta-correcao', '110110', 'Carta de Correcao registrada'],
        ];
    }

    public function testRejeicaoComXmlDeExemplo(): void
    {
        $envelope = $this->requisitar('GET', '/exemplos/nfe/soap/venda.xml');
        $retorno = $this->requisitar('POST', '/sefaz/rejeicao-1091/NFeAutorizacao4', $envelope->corpo());

        self::assertSame('1091', $this->valor($retorno, '//*[local-name()="infProt"]/*[local-name()="cStat"]'));
    }

    public function testForaDoArTambemNosEventos(): void
    {
        $envelope = $this->requisitar('GET', '/exemplos/nfe/soap/evento-cancelamento.xml');
        $retorno = $this->requisitar('POST', '/sefaz/fora-do-ar/NFeRecepcaoEvento4', $envelope->corpo());

        self::assertSame(503, $retorno->status());
    }

    private function requisitar(string $metodo, string $caminho, ?string $corpo = null): RespostaHttp
    {
        return (new TransporteCurl())->enviar(
            $metodo,
            $this->urlWireMock . $caminho,
            ['Content-Type' => 'application/soap+xml; charset=utf-8'],
            $corpo
        );
    }

    private function valor(RespostaHttp $resposta, string $consulta): string
    {
        $dom = new DOMDocument();
        $dom->loadXML($resposta->corpo());
        $nos = (new DOMXPath($dom))->query($consulta);

        return $nos !== false && $nos->length > 0 ? trim((string) $nos->item(0)->textContent) : '';
    }
}
