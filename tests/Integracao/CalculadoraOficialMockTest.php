<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Integracao;

use DuNf\ReformaTributaria\CalculadoraOficial\Cliente;
use DuNf\ReformaTributaria\Calculo\CalculadoraIbsCbs;
use DuNf\ReformaTributaria\Calculo\ItemIbsCbs;
use DuNf\ReformaTributaria\Excecao\ErroCalculadoraOficialException;

final class CalculadoraOficialMockTest extends WireMockTestCase
{
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cliente = new Cliente($this->urlWireMock . '/api/calculadora');
    }

    public function testRegimeGeralBateComOCalculoLocal(): void
    {
        $resposta = $this->cliente->calcularRegimeGeral($this->operacao('84713012'));

        $local = (new CalculadoraIbsCbs())->calcularDocumento([
            new ItemIbsCbs(1, '000', '000001', '1000.00'),
            new ItemIbsCbs(2, '200', '200028', '1200.00'),
        ], new \DateTimeImmutable('2026-11-20'));

        $total = $resposta['total']['tribCalc']['IBSCBSTot'];
        self::assertSame($local->totais()->baseCalculo(), $total['vBCIBSCBS']);
        self::assertSame($local->totais()->ibs(), $total['gIBS']['vIBS']);
        self::assertSame($local->totais()->cbs(), $total['gCBS']['vCBS']);
        self::assertCount(2, $resposta['objetos']);
    }

    public function testRegimeGeralComNcmInvalidaRetorna422(): void
    {
        try {
            $this->cliente->calcularRegimeGeral($this->operacao('00000000'));
            self::fail('Deveria lancar excecao.');
        } catch (ErroCalculadoraOficialException $excecao) {
            self::assertSame(422, $excecao->statusHttp());
            self::assertStringContainsString('REG-011', $excecao->corpoResposta());
        }
    }

    public function testObservabilidadeTrazErroPorItem(): void
    {
        $resposta = $this->cliente->calcularComObservabilidade($this->operacao('84713012'));

        self::assertSame('CALCULADO', $resposta['objetos'][0]['estadoItem']);
        self::assertSame('INCONSISTENCIA_ENTRADA', $resposta['objetos'][2]['estadoItem']);
    }

    public function testBaseCalculoUnificada(): void
    {
        $resposta = $this->cliente->baseCalculoUnificada(['anoFatoGerador' => 2027, 'tipoDocumento' => 55]);

        self::assertEquals(870, $resposta['baseRG']);
        self::assertCount(2, $resposta['avisos']);
    }

    public function testGerarEValidarXml(): void
    {
        $xml = $this->cliente->gerarXml(['objetos' => []], 'nfe');

        self::assertStringContainsString('<IBSCBSTot>', $xml);
        self::assertTrue($this->cliente->validarXml($xml));
    }

    public function testValidarXmlInvalidoRetorna422(): void
    {
        $this->expectException(ErroCalculadoraOficialException::class);
        $this->cliente->validarXml('<IBSCBS><CST>999</CST></IBSCBS>');
    }

    public function testConsultaClassificacao(): void
    {
        $resposta = $this->cliente->consultarClassificacaoTributaria('200028', new \DateTimeImmutable('2027-01-01'));

        self::assertSame('200028', $resposta['codigo']);
        self::assertEquals(60, $resposta['percentualReducaoCbs']);
    }

    public function testClassificacaoInexistenteRetorna404(): void
    {
        $this->expectException(ErroCalculadoraOficialException::class);
        $this->cliente->consultarClassificacaoTributaria('999999', new \DateTimeImmutable('2027-01-01'));
    }

    /**
     * @return array<string, mixed>
     */
    private function operacao(string $ncm): array
    {
        return [
            'id' => 'teste-wiremock',
            'versao' => '1.0.0',
            'dhFatoGerador' => '2026-11-20T10:00:00-03:00',
            'municipio' => 3550308,
            'uf' => 'SP',
            'itens' => [
                ['numero' => 1, 'ncm' => $ncm, 'cst' => '000', 'cClassTrib' => '000001', 'baseCalculo' => 1000.00],
                [
                    'numero' => 2,
                    'nbs' => '122011200',
                    'cst' => '200',
                    'cClassTrib' => '200028',
                    'baseCalculo' => 1200.00,
                ],
            ],
        ];
    }
}
