<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Calculo;

use DuNf\ReformaTributaria\Suporte\Decimal;
use DuNf\ReformaTributaria\Tributo;

/**
 * Percentuais de diferimento (pDif) por tributo, usados nos CST 510 e 515.
 */
final class Diferimento
{
    /** @var array<string, Decimal> */
    private array $porTributo;

    /**
     * @param int|float|string $cbs
     * @param int|float|string $ibsUf
     * @param int|float|string $ibsMun
     */
    public function __construct($cbs, $ibsUf, $ibsMun)
    {
        $this->porTributo = [
            Tributo::cbs()->codigo() => Decimal::de($cbs),
            Tributo::ibsUf()->codigo() => Decimal::de($ibsUf),
            Tributo::ibsMun()->codigo() => Decimal::de($ibsMun),
        ];
    }

    public function de(Tributo $tributo): Decimal
    {
        return $this->porTributo[$tributo->codigo()];
    }
}
