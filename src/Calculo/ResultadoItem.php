<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Calculo;

/**
 * Resultado do calculo de um item. Os tributos sao null quando o CST nao tem o grupo gIBSCBS
 * (ex.: 400 isencao e 410 imunidade), caso em que o XML leva apenas CST e cClassTrib.
 */
final class ResultadoItem
{
    private int $numero;

    private string $cst;

    private string $classificacaoTributaria;

    private ?string $baseCalculo;

    private ?ResultadoTributo $ibsUf;

    private ?ResultadoTributo $ibsMun;

    private ?ResultadoTributo $cbs;

    public function __construct(
        int $numero,
        string $cst,
        string $classificacaoTributaria,
        ?string $baseCalculo = null,
        ?ResultadoTributo $ibsUf = null,
        ?ResultadoTributo $ibsMun = null,
        ?ResultadoTributo $cbs = null
    ) {
        $this->numero = $numero;
        $this->cst = $cst;
        $this->classificacaoTributaria = $classificacaoTributaria;
        $this->baseCalculo = $baseCalculo;
        $this->ibsUf = $ibsUf;
        $this->ibsMun = $ibsMun;
        $this->cbs = $cbs;
    }

    public function numero(): int
    {
        return $this->numero;
    }

    public function cst(): string
    {
        return $this->cst;
    }

    public function classificacaoTributaria(): string
    {
        return $this->classificacaoTributaria;
    }

    public function possuiGrupoIbsCbs(): bool
    {
        return $this->baseCalculo !== null;
    }

    /**
     * vBC do grupo gIBSCBS.
     */
    public function baseCalculo(): ?string
    {
        return $this->baseCalculo;
    }

    public function ibsUf(): ?ResultadoTributo
    {
        return $this->ibsUf;
    }

    public function ibsMun(): ?ResultadoTributo
    {
        return $this->ibsMun;
    }

    public function cbs(): ?ResultadoTributo
    {
        return $this->cbs;
    }

    /**
     * vIBS = vIBSUF + vIBSMun.
     */
    public function valorIbs(): ?string
    {
        if ($this->ibsUf === null || $this->ibsMun === null) {
            return null;
        }

        return bcadd($this->ibsUf->valor(), $this->ibsMun->valor(), 2);
    }
}
