<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Unit;

use DuNf\ReformaTributaria\Excecao\RegraNaoEncontradaException;
use DuNf\ReformaTributaria\Transicao\Cronograma;
use PHPUnit\Framework\TestCase;

final class CronogramaTest extends TestCase
{
    public function testAnoDeTesteDe2026(): void
    {
        $ano = (new Cronograma())->ano(2026);

        self::assertSame('0.9', $ano->aliquotaCbs());
        self::assertSame('0.1', $ano->aliquotaIbsUf());
        self::assertSame('0', $ano->aliquotaIbsMun());
        self::assertTrue($ano->pisCofinsVigentes());
        self::assertFalse($ano->impostoSeletivoVigente());
        self::assertSame('100', $ano->percentualIcmsIss());
    }

    public function testCbsDe2027DependeDaAliquotaDeReferencia(): void
    {
        $ano = (new Cronograma())->ano(2027);

        self::assertNull($ano->aliquotaCbs());
        self::assertSame('-0.1', $ano->ajusteCbsPontosPercentuais());
        self::assertSame('0.05', $ano->aliquotaIbsUf());
        self::assertSame('0.05', $ano->aliquotaIbsMun());
        self::assertFalse($ano->pisCofinsVigentes());
        self::assertTrue($ano->impostoSeletivoVigente());
    }

    public function testReducaoGradualDoIcmsEIss(): void
    {
        $cronograma = new Cronograma();

        self::assertSame('90', $cronograma->ano(2029)->percentualIcmsIss());
        self::assertSame('80', $cronograma->ano(2030)->percentualIcmsIss());
        self::assertSame('70', $cronograma->ano(2031)->percentualIcmsIss());
        self::assertSame('60', $cronograma->ano(2032)->percentualIcmsIss());
        self::assertFalse($cronograma->ano(2033)->icmsIssVigentes());
    }

    public function testAnosAposOCronogramaUsamOSistemaPleno(): void
    {
        $ano = (new Cronograma())->ano(2040);

        self::assertSame(2040, $ano->ano());
        self::assertFalse($ano->icmsIssVigentes());
    }

    public function testBuscaPorData(): void
    {
        $ano = (new Cronograma())->data(new \DateTimeImmutable('2028-05-10'));

        self::assertSame(2028, $ano->ano());
    }

    public function testListaTodosOsAnos(): void
    {
        self::assertCount(8, (new Cronograma())->todos());
    }

    public function testAnoAnteriorAReformaLancaExcecao(): void
    {
        $this->expectException(RegraNaoEncontradaException::class);
        (new Cronograma())->ano(2025);
    }
}
