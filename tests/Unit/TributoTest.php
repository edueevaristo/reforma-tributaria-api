<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Unit;

use DuNf\ReformaTributaria\Excecao\DadoInvalidoException;
use DuNf\ReformaTributaria\Transicao\Cronograma;
use DuNf\ReformaTributaria\Tributo;
use PHPUnit\Framework\TestCase;

final class TributoTest extends TestCase
{
    public function testTodosNaOrdemDoXml(): void
    {
        $codigos = array_map(static fn (Tributo $tributo): string => $tributo->codigo(), Tributo::todos());

        self::assertSame(['ibsUf', 'ibsMun', 'cbs'], $codigos);
    }

    public function testIgualdadePorValor(): void
    {
        self::assertTrue(Tributo::deCodigo('cbs')->equals(Tributo::cbs()));
        self::assertFalse(Tributo::ibsUf()->equals(Tributo::ibsMun()));
        self::assertSame('IBS municipal', Tributo::ibsMun()->nome());
    }

    public function testCodigoDesconhecidoLancaExcecao(): void
    {
        $this->expectException(DadoInvalidoException::class);
        Tributo::deCodigo('icms');
    }

    public function testAliquotaDoAnoPorTributo(): void
    {
        $ano = (new Cronograma())->ano(2026);

        self::assertSame('0.9', (string) $ano->aliquota(Tributo::cbs()));
        self::assertSame('0.1', (string) $ano->aliquota(Tributo::ibsUf()));
        self::assertNull((new Cronograma())->ano(2027)->aliquota(Tributo::cbs()));
    }
}
