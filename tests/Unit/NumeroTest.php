<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Unit;

use DuNf\ReformaTributaria\Excecao\DadoInvalidoException;
use DuNf\ReformaTributaria\Suporte\Numero;
use PHPUnit\Framework\TestCase;

final class NumeroTest extends TestCase
{
    /**
     * @dataProvider casosArredondamento
     */
    public function testArredondaComHalfEven(string $valor, int $casas, string $esperado): void
    {
        self::assertSame($esperado, Numero::arredondar($valor, $casas));
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

    public function testFormataPercentualComDuasAQuatroCasas(): void
    {
        self::assertSame('0.90', Numero::formatarPercentual('0.9'));
        self::assertSame('0.00', Numero::formatarPercentual('0'));
        self::assertSame('3.52', Numero::formatarPercentual('3.5200'));
        self::assertSame('0.0123', Numero::formatarPercentual('0.0123'));
        self::assertSame('100.00', Numero::formatarPercentual('100'));
    }

    public function testAplicaPercentualSemArredondar(): void
    {
        self::assertSame('0.0108', Numero::aplicarPercentual('1.2', '0.9'));
    }

    public function testNormalizaTiposAceitos(): void
    {
        self::assertSame('10', Numero::normalizar(10));
        self::assertSame('0.1', Numero::normalizar(0.1));
        self::assertSame('1.50', Numero::normalizar('1.50'));
    }

    public function testRecusaTextoNaoNumerico(): void
    {
        $this->expectException(DadoInvalidoException::class);
        Numero::normalizar('1,50');
    }
}
