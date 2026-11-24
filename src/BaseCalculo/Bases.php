<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\BaseCalculo;

use DuNf\ReformaTributaria\Suporte\Decimal;

/**
 * Acumulador imutavel das tres bases da base de calculo unificada.
 *
 * @internal
 */
final class Bases
{
    public const REGIME_REGULAR = 'RG';
    public const IMPOSTO_SELETIVO = 'IS';
    public const SIMPLES_NACIONAL = 'SN';

    /** @var array<string, Decimal> */
    private array $valores;

    /**
     * @param array<string, Decimal> $valores
     */
    private function __construct(array $valores)
    {
        $this->valores = $valores;
    }

    public static function zeradas(): self
    {
        return new self([
            self::REGIME_REGULAR => Decimal::zero(),
            self::IMPOSTO_SELETIVO => Decimal::zero(),
            self::SIMPLES_NACIONAL => Decimal::zero(),
        ]);
    }

    /**
     * Soma (valor positivo) ou subtrai (valor negativo) o valor na base informada.
     */
    public function acumular(string $base, Decimal $valor): self
    {
        $valores = $this->valores;
        $valores[$base] = $valores[$base]->somar($valor);

        return new self($valores);
    }

    /**
     * Bases publicadas (2 casas, HALF_EVEN) com os avisos dos componentes ignorados.
     *
     * @param string[] $avisos
     */
    public function publicar(array $avisos): ResultadoBaseCalculo
    {
        return new ResultadoBaseCalculo(
            (string) $this->valores[self::REGIME_REGULAR]->valorFiscal(),
            (string) $this->valores[self::IMPOSTO_SELETIVO]->valorFiscal(),
            (string) $this->valores[self::SIMPLES_NACIONAL]->valorFiscal(),
            $avisos
        );
    }
}
