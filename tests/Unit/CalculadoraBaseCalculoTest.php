<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Unit;

use DuNf\ReformaTributaria\BaseCalculo\CalculadoraBaseCalculo;
use DuNf\ReformaTributaria\BaseCalculo\ComponentesBaseCalculo;
use DuNf\ReformaTributaria\BaseCalculo\ModeloDocumento;
use DuNf\ReformaTributaria\BaseCalculo\Natureza;
use DuNf\ReformaTributaria\BaseCalculo\ResultadoBaseCalculo;
use DuNf\ReformaTributaria\Excecao\DadoInvalidoException;
use PHPUnit\Framework\TestCase;

final class CalculadoraBaseCalculoTest extends TestCase
{
    /**
     * Mesmo exemplo de docs/exemplos.md da Calculadora de Tributos oficial.
     */
    public function testExemploOficialDe2027(): void
    {
        $componentes = new ComponentesBaseCalculo([
            'vProd' => '1000.00',
            'vFrete' => '50.00',
            'vSeg' => '20.00',
            'vOutro' => '10.00',
            'vDesc' => '50.00',
            'vIS' => '30.00',
            'vPIS' => '9.25',
            'vCOFINS' => '46.25',
            'vICMS' => '180.00',
            'vFCP' => '10.00',
        ]);

        $resultado = $this->calcular($componentes, 2027, Natureza::bem());

        self::assertSame('870.00', $resultado->baseRegimeRegular());
        self::assertSame('840.00', $resultado->baseImpostoSeletivo());
        self::assertSame('1060.00', $resultado->baseSimplesNacional());
        self::assertCount(2, $resultado->avisos());
        self::assertStringContainsString('vPIS', $resultado->avisos()[0]);
        self::assertStringContainsString('vCOFINS', $resultado->avisos()[1]);
    }

    public function testEm2026PisECofinsSaemDaBase(): void
    {
        $componentes = new ComponentesBaseCalculo([
            'vProd' => '1000.00',
            'vPIS' => '16.50',
            'vCOFINS' => '76.00',
            'vICMS' => '180.00',
        ]);

        $resultado = $this->calcular($componentes, 2026, Natureza::bem());

        self::assertSame('727.50', $resultado->baseRegimeRegular());
        self::assertSame([], $resultado->avisos());
    }

    public function testIcmsNaoEntraNaBaseDeServico(): void
    {
        $componentes = new ComponentesBaseCalculo(['vProd' => '500.00', 'vICMS' => '90.00', 'vISSQN' => '25.00']);

        $resultado = $this->calcular($componentes, 2026, Natureza::servico());

        self::assertSame('475.00', $resultado->baseRegimeRegular());
        self::assertCount(1, $resultado->avisos());
    }

    public function testComponenteDesconhecidoLancaExcecao(): void
    {
        $this->expectException(DadoInvalidoException::class);
        $this->expectExceptionMessage('Componente de base de calculo desconhecido: vXYZ.');

        $this->calcular(new ComponentesBaseCalculo(['vProd' => '1', 'vXYZ' => '1']), 2026, Natureza::bem());
    }

    public function testComponenteForaDoModeloGeraAviso(): void
    {
        $componentes = new ComponentesBaseCalculo(['vProd' => '100.00', 'vII' => '10.00']);

        $resultado = $this->calcular($componentes, 2026, Natureza::bem(), ModeloDocumento::nfce());

        self::assertSame('100.00', $resultado->baseRegimeRegular());
        self::assertSame(
            ['Campo vII nao compoe as bases para o documento modelo 65 e foi ignorado no calculo'],
            $resultado->avisos()
        );
    }

    public function testComponenteZeradoNaoGeraAviso(): void
    {
        $componentes = new ComponentesBaseCalculo(['vProd' => '100.00', 'vPIS' => '0.00']);

        $resultado = $this->calcular($componentes, 2027, Natureza::bem());

        self::assertSame([], $resultado->avisos());
    }

    public function testModeloInvalidoLancaExcecao(): void
    {
        $this->expectException(DadoInvalidoException::class);
        ModeloDocumento::de(57);
    }

    public function testNaturezaInvalidaLancaExcecao(): void
    {
        $this->expectException(DadoInvalidoException::class);
        Natureza::de('mercadoria');
    }

    public function testNaturezaEModeloPorValor(): void
    {
        self::assertTrue(Natureza::de('servico')->equals(Natureza::servico()));
        self::assertSame(65, ModeloDocumento::de(65)->numero());
    }

    private function calcular(
        ComponentesBaseCalculo $componentes,
        int $ano,
        Natureza $natureza,
        ?ModeloDocumento $modelo = null
    ): ResultadoBaseCalculo {
        $calculadora = new CalculadoraBaseCalculo();

        return $calculadora->calcular($componentes, $ano, $natureza, $modelo ?? ModeloDocumento::nfe());
    }
}
