<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Unit;

use DuNf\ReformaTributaria\BaseCalculo\CalculadoraBaseCalculo;
use DuNf\ReformaTributaria\BaseCalculo\ComponentesBaseCalculo;
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

        $resultado = (new CalculadoraBaseCalculo())->calcular($componentes, 2027, 'bem', 55);

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

        $resultado = (new CalculadoraBaseCalculo())->calcular($componentes, 2026, 'bem', 55);

        self::assertSame('727.50', $resultado->baseRegimeRegular());
        self::assertSame([], $resultado->avisos());
    }

    public function testIcmsNaoEntraNaBaseDeServico(): void
    {
        $componentes = new ComponentesBaseCalculo(['vProd' => '500.00', 'vICMS' => '90.00', 'vISSQN' => '25.00']);

        $resultado = (new CalculadoraBaseCalculo())->calcular($componentes, 2026, 'servico', 55);

        self::assertSame('475.00', $resultado->baseRegimeRegular());
        self::assertCount(1, $resultado->avisos());
    }

    public function testComponenteDesconhecidoLancaExcecao(): void
    {
        $this->expectException(DadoInvalidoException::class);
        (new CalculadoraBaseCalculo())->calcular(new ComponentesBaseCalculo(['vXYZ' => '1']), 2026, 'bem', 55);
    }

    public function testModeloInvalidoLancaExcecao(): void
    {
        $this->expectException(DadoInvalidoException::class);
        (new CalculadoraBaseCalculo())->calcular(new ComponentesBaseCalculo(['vProd' => '1']), 2026, 'bem', 57);
    }
}
