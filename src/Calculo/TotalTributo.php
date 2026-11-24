<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Calculo;

use DuNf\ReformaTributaria\Suporte\Decimal;

/**
 * Total de um tributo no documento: soma dos valores devidos e dos diferimentos publicados nos itens.
 *
 * @internal
 */
final class TotalTributo
{
    private Decimal $valor;

    private Decimal $diferimento;

    private function __construct(Decimal $valor, Decimal $diferimento)
    {
        $this->valor = $valor;
        $this->diferimento = $diferimento;
    }

    public static function zerado(): self
    {
        return new self(Decimal::zero(), Decimal::zero());
    }

    public function acumular(?ResultadoTributo $tributo): self
    {
        if ($tributo === null) {
            return $this;
        }

        return new self(
            $this->valor->somar($tributo->valor()),
            $this->diferimento->somar($tributo->valorDiferimento() ?? '0')
        );
    }

    public function valor(): Decimal
    {
        return $this->valor->valorFiscal();
    }

    public function diferimento(): Decimal
    {
        return $this->diferimento->valorFiscal();
    }
}
