<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Unit;

use DuNf\ReformaTributaria\Excecao\DadoInvalidoException;
use DuNf\ReformaTributaria\Suporte\Decimal;
use PHPUnit\Framework\TestCase;

final class DecimalTest extends TestCase
{
    /**
     * @dataProvider casosArredondamento
     */
    public function testArredondaComHalfEven(string $valor, int $casas, string $esperado): void
    {
        self::assertSame($esperado, (string) Decimal::de($valor)->arredondar($casas));
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public function casosArredondamento(): array
    {
        return [
            'empate com algarismo par fica' => ['0.125', 2, '0.12'],
            'empate com algarismo impar sobe' => ['0.135', 2, '0.14'],
            'acima da metade sobe' => ['0.1251', 2, '0.13'],
            'abaixo da metade desce' => ['0.1249', 2, '0.12'],
            'inteiro' => ['10', 2, '10.00'],
            'negativo' => ['-0.135', 2, '-0.14'],
            'negativo que zera' => ['-0.004', 2, '0.00'],
            'quatro casas' => ['3.52005', 4, '3.5200'],
            'zero casas' => ['2.5', 0, '2'],
        ];
    }

    public function testValorFiscalTemDuasCasasEAliquotaFiscalQuatro(): void
    {
        self::assertSame('691.12', (string) Decimal::de('691.125')->valorFiscal());
        self::assertSame('0.9000', (string) Decimal::de('0.9')->aliquotaFiscal());
    }

    public function testFormataPercentualDoXmlComDuasAQuatroCasas(): void
    {
        self::assertSame('0.90', Decimal::de('0.9')->percentualXml());
        self::assertSame('0.00', Decimal::de('0')->percentualXml());
        self::assertSame('3.52', Decimal::de('3.5200')->percentualXml());
        self::assertSame('0.0123', Decimal::de('0.0123')->percentualXml());
        self::assertSame('100.00', Decimal::de('100')->percentualXml());
    }

    public function testAplicaPercentualSemArredondar(): void
    {
        self::assertSame('0.0108', (string) Decimal::de('1.2')->percentual('0.9'));
    }

    public function testFatorRestanteDeUmaReducao(): void
    {
        self::assertSame('0.4', (string) Decimal::de('60')->fatorRestante());
        self::assertSame('0', (string) Decimal::de('100')->fatorRestante());
    }

    public function testOperacoesSaoExatasEImutaveis(): void
    {
        $valor = Decimal::de('0.1');

        self::assertSame('0.3', (string) $valor->somar('0.2'));
        self::assertSame('-0.1', (string) $valor->subtrair('0.2'));
        self::assertSame('0.02', (string) $valor->multiplicar('0.2'));
        self::assertSame('0.1', (string) $valor);
    }

    public function testComparaEReconheceZero(): void
    {
        self::assertSame(-1, Decimal::de('1.5')->comparar('1.50001'));
        self::assertSame(0, Decimal::de('1.50')->comparar(1.5));
        self::assertTrue(Decimal::de('0.000')->ehZero());
        self::assertTrue(Decimal::zero()->ehZero());
        self::assertFalse(Decimal::de('-0.01')->ehZero());
    }

    public function testNormalizaTiposAceitos(): void
    {
        self::assertSame('10', (string) Decimal::de(10));
        self::assertSame('0.1', (string) Decimal::de(0.1));
        self::assertSame('1.50', (string) Decimal::de('1.50'));
        self::assertNull(Decimal::deOpcional(null));
    }

    public function testRecusaTextoNaoNumerico(): void
    {
        $this->expectException(DadoInvalidoException::class);
        Decimal::de('1,50');
    }
}
