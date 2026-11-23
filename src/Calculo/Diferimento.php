<?php

declare(strict_types=1);

namespace EduardoEvaristo\ReformaTributaria\Calculo;

use EduardoEvaristo\ReformaTributaria\Suporte\Numero;

/**
 * Percentuais de diferimento (pDif) por tributo, usados nos CST 510 e 515.
 */
final class Diferimento
{
    private string $cbs;

    private string $ibsUf;

    private string $ibsMun;

    /**
     * @param int|float|string $cbs
     * @param int|float|string $ibsUf
     * @param int|float|string $ibsMun
     */
    public function __construct($cbs, $ibsUf, $ibsMun)
    {
        $this->cbs = Numero::normalizar($cbs);
        $this->ibsUf = Numero::normalizar($ibsUf);
        $this->ibsMun = Numero::normalizar($ibsMun);
    }

    public function cbs(): string
    {
        return $this->cbs;
    }

    public function ibsUf(): string
    {
        return $this->ibsUf;
    }

    public function ibsMun(): string
    {
        return $this->ibsMun;
    }
}
