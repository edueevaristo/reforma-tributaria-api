<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Calculo;

use DuNf\ReformaTributaria\Suporte\Decimal;
use DuNf\ReformaTributaria\Tributo;

/**
 * Aliquotas nominais em percentual (ex.: '8.8' para 8,8%).
 *
 * Deixe null para usar a aliquota fixada em lei no cronograma de transicao.
 * A partir de 2027 a CBS depende da aliquota de referencia do Senado e precisa ser informada.
 */
final class Aliquotas
{
    /** @var array<string, Decimal|null> */
    private array $porTributo;

    /**
     * @param int|float|string|null $cbs
     * @param int|float|string|null $ibsUf
     * @param int|float|string|null $ibsMun
     */
    public function __construct($cbs = null, $ibsUf = null, $ibsMun = null)
    {
        $this->porTributo = [
            Tributo::cbs()->codigo() => Decimal::deOpcional($cbs),
            Tributo::ibsUf()->codigo() => Decimal::deOpcional($ibsUf),
            Tributo::ibsMun()->codigo() => Decimal::deOpcional($ibsMun),
        ];
    }

    /**
     * Aliquota informada para o tributo, ou null para usar a fixada em lei.
     */
    public function de(Tributo $tributo): ?Decimal
    {
        return $this->porTributo[$tributo->codigo()];
    }
}
