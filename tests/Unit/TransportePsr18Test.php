<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Unit;

use DuNf\ReformaTributaria\CalculadoraOficial\Cliente;
use DuNf\ReformaTributaria\CalculadoraOficial\TransportePsr18;
use DuNf\ReformaTributaria\Excecao\ErroCalculadoraOficialException;
use DuNf\ReformaTributaria\Tests\Apoio\ClientePsr18Falso;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class TransportePsr18Test extends TestCase
{
    public function testEnviaRequisicaoPeloClientePsr18(): void
    {
        $clientePsr18 = new ClientePsr18Falso(new Response(200, [], '{"objetos":[]}'));
        $fabrica = new Psr17Factory();
        $transporte = new TransportePsr18($clientePsr18, $fabrica, $fabrica);
        $cliente = new Cliente('http://calculadora.local/api/calculadora', $transporte);

        $resposta = $cliente->calcularRegimeGeral(['uf' => 'SP']);

        $requisicao = $clientePsr18->ultimaRequisicao();
        self::assertSame(['objetos' => []], $resposta);
        self::assertSame('POST', $requisicao->getMethod());
        self::assertSame('http://calculadora.local/api/calculadora/regime-geral', (string) $requisicao->getUri());
        self::assertSame('application/json', $requisicao->getHeaderLine('Content-Type'));
        self::assertSame('{"uf":"SP"}', (string) $requisicao->getBody());
    }

    public function testFalhaDeRedeViraErroDaCalculadora(): void
    {
        $fabrica = new Psr17Factory();
        $transporte = new TransportePsr18(ClientePsr18Falso::comFalha('conexao recusada'), $fabrica, $fabrica);

        try {
            $transporte->enviar('GET', 'http://calculadora.local/api/calculadora/dados-abertos');
            self::fail('Deveria lancar excecao.');
        } catch (ErroCalculadoraOficialException $excecao) {
            self::assertStringContainsString('conexao recusada', $excecao->getMessage());
            self::assertNotNull($excecao->getPrevious());
        }
    }
}
