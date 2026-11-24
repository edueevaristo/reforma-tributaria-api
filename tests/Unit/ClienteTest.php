<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Unit;

use DuNf\ReformaTributaria\CalculadoraOficial\Cliente;
use DuNf\ReformaTributaria\CalculadoraOficial\RespostaHttp;
use DuNf\ReformaTributaria\Excecao\ErroCalculadoraOficialException;
use DuNf\ReformaTributaria\Tests\Apoio\TransporteFalso;
use PHPUnit\Framework\TestCase;

final class ClienteTest extends TestCase
{
    public function testEnviaOperacaoParaRegimeGeral(): void
    {
        $transporte = new TransporteFalso(new RespostaHttp(200, '{"objetos":[],"total":{}}'));
        $cliente = new Cliente('http://calculadora.local/api/calculadora/', $transporte);

        $resposta = $cliente->calcularRegimeGeral(['uf' => 'RS', 'itens' => []]);

        self::assertSame(['objetos' => [], 'total' => []], $resposta);
        self::assertSame('POST', $transporte->metodo);
        self::assertSame('http://calculadora.local/api/calculadora/regime-geral', $transporte->url);
        self::assertSame('{"uf":"RS","itens":[]}', $transporte->corpo);
    }

    public function testConsultaClassificacaoComData(): void
    {
        $transporte = new TransporteFalso(new RespostaHttp(200, '{"codigo":"200028"}'));
        $cliente = new Cliente(Cliente::URL_PADRAO, $transporte);

        $cliente->consultarClassificacaoTributaria('200028', new \DateTimeImmutable('2027-01-01'));

        self::assertSame(
            'http://localhost:8080/api/calculadora/dados-abertos/classificacoes-tributarias/cbs-ibs/class-trib/200028'
            . '?data=2027-01-01',
            $transporte->url
        );
    }

    public function testErroHttpLancaExcecaoComCorpo(): void
    {
        $transporte = new TransporteFalso(new RespostaHttp(422, '{"code":"REG-011"}'));
        $cliente = new Cliente(Cliente::URL_PADRAO, $transporte);

        try {
            $cliente->calcularRegimeGeral([]);
            self::fail('Deveria lancar excecao.');
        } catch (ErroCalculadoraOficialException $excecao) {
            self::assertSame(422, $excecao->statusHttp());
            self::assertSame('{"code":"REG-011"}', $excecao->corpoResposta());
        }
    }

    public function testValidarXmlRetornaVerdadeiro(): void
    {
        $transporte = new TransporteFalso(new RespostaHttp(200, 'true'));
        $cliente = new Cliente(Cliente::URL_PADRAO, $transporte);

        self::assertTrue($cliente->validarXml('<IBSCBS/>'));
        self::assertStringEndsWith('/xml/validate?tipo=nfe&subtipo=grupo', $transporte->url);
    }

    public function testConsultaNcmComParametrosCodificados(): void
    {
        $transporte = new TransporteFalso(new RespostaHttp(200, '{}'));
        $cliente = new Cliente(Cliente::URL_PADRAO, $transporte);

        $cliente->consultarNcm('2203.00 00', new \DateTimeImmutable('2027-01-01'));

        self::assertStringEndsWith('/dados-abertos/ncm?ncm=2203.00%2000&data=2027-01-01', $transporte->url);
        self::assertSame('GET', $transporte->metodo);
        self::assertNull($transporte->corpo);
    }

    public function testRespostaQueNaoEJsonLancaExcecao(): void
    {
        $cliente = new Cliente(Cliente::URL_PADRAO, new TransporteFalso(new RespostaHttp(200, '<html>')));

        $this->expectException(ErroCalculadoraOficialException::class);
        $cliente->consultar('dados-abertos/versao');
    }
}
