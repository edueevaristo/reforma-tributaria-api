<?php

declare(strict_types=1);

namespace EduardoEvaristo\ReformaTributaria\Calculo;

use EduardoEvaristo\ReformaTributaria\Suporte\Numero;

/**
 * Aliquotas nominais em percentual (ex.: '8.8' para 8,8%).
 *
 * Deixe null para usar a aliquota fixada em lei no cronograma de transicao.
 * A partir de 2027 a CBS depende da aliquota de referencia do Senado e precisa ser informada.
 */
final class Aliquotas
{
    private ?string $cbs;

    private ?string $ibsUf;

    private ?string $ibsMun;

    /**
     * @param int|float|string|null $cbs
     * @param int|float|string|null $ibsUf
     * @param int|float|string|null $ibsMun
     */
    public function __construct($cbs = null, $ibsUf = null, $ibsMun = null)
    {
        $this->cbs = $cbs === null ? null : Numero::normalizar($cbs);
        $this->ibsUf = $ibsUf === null ? null : Numero::normalizar($ibsUf);
        $this->ibsMun = $ibsMun === null ? null : Numero::normalizar($ibsMun);
    }

    public function cbs(): ?string
    {
        return $this->cbs;
    }

    public function ibsUf(): ?string
    {
        return $this->ibsUf;
    }

    public function ibsMun(): ?string
    {
        return $this->ibsMun;
    }
}
