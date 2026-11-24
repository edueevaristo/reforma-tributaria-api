<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Suporte;

use DuNf\ReformaTributaria\Excecao\DadoInvalidoException;

/**
 * Numero decimal exato e imutavel, com bcmath.
 *
 * Segue a mesma politica numerica da Calculadora de Tributos oficial (ADR003):
 * contas internas sem arredondamento e arredondamento HALF_EVEN (ABNT NBR 5891)
 * apenas no numero que sera publicado no documento fiscal.
 */
final class Decimal
{
    /** Casas decimais usadas nas contas internas. Produtos de valores fiscais nunca passam disso. */
    private const ESCALA_INTERNA = 20;

    /** Casas de um valor monetario publicado no documento fiscal. */
    private const CASAS_VALOR = 2;

    /** Casas de uma aliquota ou percentual publicado no documento fiscal. */
    private const CASAS_ALIQUOTA = 4;

    /** Minimo de casas de um percentual no XML (TDec_0302_04RTC). */
    private const CASAS_MINIMAS_PERCENTUAL = 2;

    private const FORMATO = '/^-?\d+(\.\d+)?$/';

    private string $valor;

    private function __construct(string $valor)
    {
        $this->valor = $valor;
    }

    /**
     * @param int|float|string|self $valor
     */
    public static function de($valor): self
    {
        if ($valor instanceof self) {
            return $valor;
        }

        return new self(self::normalizar($valor));
    }

    /**
     * @param int|float|string|self|null $valor
     */
    public static function deOpcional($valor): ?self
    {
        return $valor === null ? null : self::de($valor);
    }

    public static function zero(): self
    {
        return new self('0');
    }

    /**
     * @param int|float|string|self $outro
     */
    public function somar($outro): self
    {
        return self::semZerosDireita(bcadd($this->valor, self::de($outro)->valor, self::ESCALA_INTERNA));
    }

    /**
     * @param int|float|string|self $outro
     */
    public function subtrair($outro): self
    {
        return self::semZerosDireita(bcsub($this->valor, self::de($outro)->valor, self::ESCALA_INTERNA));
    }

    /**
     * @param int|float|string|self $outro
     */
    public function multiplicar($outro): self
    {
        return self::semZerosDireita(bcmul($this->valor, self::de($outro)->valor, self::ESCALA_INTERNA));
    }

    /**
     * Aplica um percentual sem arredondar: valor x percentual / 100.
     *
     * @param int|float|string|self $percentual
     */
    public function percentual($percentual): self
    {
        $produto = bcmul($this->valor, self::de($percentual)->valor, self::ESCALA_INTERNA);

        return self::semZerosDireita(bcdiv($produto, '100', self::ESCALA_INTERNA));
    }

    /**
     * Fator que sobra depois de uma reducao percentual: 1 - percentual / 100.
     */
    public function fatorRestante(): self
    {
        return self::de('1')->subtrair(self::de('1')->percentual($this));
    }

    /**
     * Compara com outro valor. Retorna -1, 0 ou 1.
     *
     * @param int|float|string|self $outro
     */
    public function comparar($outro): int
    {
        return bccomp($this->valor, self::de($outro)->valor, self::ESCALA_INTERNA);
    }

    public function ehZero(): bool
    {
        return $this->comparar(self::zero()) === 0;
    }

    /**
     * Arredonda com HALF_EVEN: no empate exato, fica o algarismo par. O resultado tem exatamente $casas casas.
     */
    public function arredondar(int $casas): self
    {
        $absoluto = ltrim($this->valor, '-');
        $arredondado = self::arredondarAbsoluto($absoluto, $casas);

        if ($this->ehNegativo() && bccomp($arredondado, '0', $casas) !== 0) {
            return new self('-' . $arredondado);
        }

        return new self($arredondado);
    }

    /**
     * Valor monetario publicado no documento fiscal: 2 casas.
     */
    public function valorFiscal(): self
    {
        return $this->arredondar(self::CASAS_VALOR);
    }

    /**
     * Aliquota publicada no documento fiscal: 4 casas.
     */
    public function aliquotaFiscal(): self
    {
        return $this->arredondar(self::CASAS_ALIQUOTA);
    }

    /**
     * Percentual no formato do XML: minimo 2 e maximo 4 casas (TDec_0302_04RTC).
     */
    public function percentualXml(): string
    {
        $comQuatroCasas = $this->aliquotaFiscal()->valor;
        $semZeros = rtrim($comQuatroCasas, '0');
        $casas = strlen($semZeros) - (int) strpos($semZeros, '.') - 1;

        if ($casas < self::CASAS_MINIMAS_PERCENTUAL) {
            return bcadd($comQuatroCasas, '0', self::CASAS_MINIMAS_PERCENTUAL);
        }

        return $semZeros;
    }

    public function __toString(): string
    {
        return $this->valor;
    }

    private function ehNegativo(): bool
    {
        return strpos($this->valor, '-') === 0;
    }

    private static function arredondarAbsoluto(string $absoluto, int $casas): string
    {
        $truncado = bcadd($absoluto, '0', $casas);
        $resto = bcsub($absoluto, $truncado, self::ESCALA_INTERNA);
        $unidade = $casas === 0 ? '1' : '0.' . str_repeat('0', $casas - 1) . '1';
        $metade = bcdiv($unidade, '2', self::ESCALA_INTERNA);
        $comparacao = bccomp($resto, $metade, self::ESCALA_INTERNA);
        $ultimoDigitoImpar = (int) substr($truncado, -1) % 2 === 1;

        if ($comparacao > 0 || ($comparacao === 0 && $ultimoDigitoImpar)) {
            return bcadd($truncado, $unidade, $casas);
        }

        return $truncado;
    }

    /**
     * @param int|float|string $valor
     */
    private static function normalizar($valor): string
    {
        if (is_int($valor)) {
            return (string) $valor;
        }

        if (is_float($valor)) {
            return (string) self::semZerosDireita(number_format($valor, 10, '.', ''));
        }

        if (is_string($valor) && preg_match(self::FORMATO, $valor) === 1) {
            return $valor;
        }

        throw new DadoInvalidoException('Valor numerico invalido: ' . var_export($valor, true));
    }

    private static function semZerosDireita(string $valor): self
    {
        if (strpos($valor, '.') === false) {
            return new self($valor);
        }

        $semZeros = rtrim(rtrim($valor, '0'), '.');

        return new self($semZeros === '-0' ? '0' : $semZeros);
    }
}
