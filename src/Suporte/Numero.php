<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Suporte;

use DuNf\ReformaTributaria\Excecao\DadoInvalidoException;

/**
 * Aritmetica decimal exata com bcmath.
 *
 * Segue a mesma politica numerica da Calculadora de Tributos oficial (ADR003):
 * contas internas sem arredondamento e arredondamento HALF_EVEN (ABNT NBR 5891)
 * apenas no numero que sera publicado no documento fiscal.
 */
final class Numero
{
    /** Casas decimais usadas nas contas internas. Produtos de valores fiscais nunca passam disso. */
    private const ESCALA_INTERNA = 20;

    private function __construct()
    {
    }

    /**
     * Converte int, float ou string numerica em string decimal.
     *
     * @param int|float|string $valor
     */
    public static function normalizar($valor): string
    {
        if (is_int($valor)) {
            return (string) $valor;
        }

        if (is_float($valor)) {
            return self::removerZerosDireita(number_format($valor, 10, '.', ''));
        }

        if (is_string($valor) && preg_match('/^-?\d+(\.\d+)?$/', $valor) === 1) {
            return $valor;
        }

        throw new DadoInvalidoException('Valor numerico invalido: ' . var_export($valor, true));
    }

    /**
     * @param int|float|string $a
     * @param int|float|string $b
     */
    public static function somar($a, $b): string
    {
        return self::removerZerosDireita(bcadd(self::normalizar($a), self::normalizar($b), self::ESCALA_INTERNA));
    }

    /**
     * @param int|float|string $a
     * @param int|float|string $b
     */
    public static function subtrair($a, $b): string
    {
        return self::removerZerosDireita(bcsub(self::normalizar($a), self::normalizar($b), self::ESCALA_INTERNA));
    }

    /**
     * @param int|float|string $a
     * @param int|float|string $b
     */
    public static function multiplicar($a, $b): string
    {
        return self::removerZerosDireita(bcmul(self::normalizar($a), self::normalizar($b), self::ESCALA_INTERNA));
    }

    /**
     * Aplica um percentual: valor x percentual / 100.
     *
     * @param int|float|string $valor
     * @param int|float|string $percentual
     */
    public static function aplicarPercentual($valor, $percentual): string
    {
        $produto = bcmul(self::normalizar($valor), self::normalizar($percentual), self::ESCALA_INTERNA);

        return self::removerZerosDireita(bcdiv($produto, '100', self::ESCALA_INTERNA));
    }

    /**
     * Compara dois valores. Retorna -1, 0 ou 1.
     *
     * @param int|float|string $a
     * @param int|float|string $b
     */
    public static function comparar($a, $b): int
    {
        return bccomp(self::normalizar($a), self::normalizar($b), self::ESCALA_INTERNA);
    }

    /**
     * Arredonda com HALF_EVEN: no empate exato, fica o algarismo par.
     *
     * @param int|float|string $valor
     */
    public static function arredondar($valor, int $casas): string
    {
        $valor = self::normalizar($valor);
        $negativo = strpos($valor, '-') === 0;
        $absoluto = ltrim($valor, '-');

        $truncado = bcadd($absoluto, '0', $casas);
        $resto = bcsub($absoluto, $truncado, self::ESCALA_INTERNA);
        $unidade = $casas === 0 ? '1' : '0.' . str_repeat('0', $casas - 1) . '1';
        $metade = bcdiv($unidade, '2', self::ESCALA_INTERNA);

        $comparacao = bccomp($resto, $metade, self::ESCALA_INTERNA);
        $ultimoDigito = (int) substr($truncado, -1);

        if ($comparacao > 0 || ($comparacao === 0 && $ultimoDigito % 2 === 1)) {
            $truncado = bcadd($truncado, $unidade, $casas);
        }

        if ($negativo && bccomp($truncado, '0', $casas) !== 0) {
            return '-' . $truncado;
        }

        return $truncado;
    }

    /**
     * Valor monetario publicado no documento fiscal: 2 casas.
     *
     * @param int|float|string $valor
     */
    public static function valorFiscal($valor): string
    {
        return self::arredondar($valor, 2);
    }

    /**
     * Aliquota publicada no documento fiscal: ate 4 casas.
     *
     * @param int|float|string $valor
     */
    public static function aliquotaFiscal($valor): string
    {
        return self::arredondar($valor, 4);
    }

    /**
     * Formata percentual para o XML: minimo 2 e maximo 4 casas (TDec_0302_04RTC).
     *
     * @param int|float|string $valor
     */
    public static function formatarPercentual($valor): string
    {
        $comQuatroCasas = self::arredondar($valor, 4);
        $semZeros = rtrim($comQuatroCasas, '0');
        $casas = strlen($semZeros) - strpos($semZeros, '.') - 1;

        if ($casas < 2) {
            return bcadd($comQuatroCasas, '0', 2);
        }

        return $semZeros;
    }

    private static function removerZerosDireita(string $valor): string
    {
        if (strpos($valor, '.') === false) {
            return $valor;
        }

        $valor = rtrim(rtrim($valor, '0'), '.');

        return $valor === '-0' ? '0' : $valor;
    }
}
