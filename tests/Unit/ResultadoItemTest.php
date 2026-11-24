<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Unit;

use DuNf\ReformaTributaria\Calculo\ItemIbsCbs;
use DuNf\ReformaTributaria\Calculo\ResultadoItem;
use DuNf\ReformaTributaria\Calculo\ResultadoTributo;
use DuNf\ReformaTributaria\Suporte\Decimal;
use PHPUnit\Framework\TestCase;

final class ResultadoItemTest extends TestCase
{
    public function testGrupoIbsCbsExigeOsTresTributos(): void
    {
        $tributo = ResultadoTributo::calcular(Decimal::de('100'), Decimal::de('1'), null, null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('sem o resultado de CBS');

        ResultadoItem::comGrupoIbsCbs(
            new ItemIbsCbs(1, '000', '000001', '100'),
            Decimal::de('100.00'),
            ['ibsUf' => $tributo, 'ibsMun' => $tributo]
        );
    }

    public function testItemSemGrupoNaoTemValores(): void
    {
        $resultado = ResultadoItem::semGrupoIbsCbs(new ItemIbsCbs(7, '410', '410001', '100'));

        self::assertSame(7, $resultado->numero());
        self::assertNull($resultado->baseCalculo());
        self::assertNull($resultado->valorIbs());
    }

    public function testTributoComReducaoEDiferimento(): void
    {
        $tributo = ResultadoTributo::calcular(
            Decimal::de('1000.00'),
            Decimal::de('8.8'),
            Decimal::de('60'),
            Decimal::de('50')
        );

        self::assertSame('8.8000', $tributo->aliquota());
        self::assertSame('60.0000', $tributo->percentualReducao());
        self::assertSame('3.5200', $tributo->aliquotaEfetiva());
        self::assertSame('50.0000', $tributo->percentualDiferimento());
        self::assertSame('17.60', $tributo->valorDiferimento());
        self::assertSame('17.60', $tributo->valor());
    }
}
