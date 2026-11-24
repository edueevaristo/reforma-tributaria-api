<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Calculo;

use DuNf\ReformaTributaria\Suporte\Numero;

/**
 * Dados de entrada de um item do documento fiscal.
 */
final class ItemIbsCbs
{
    private int $numero;

    private string $cst;

    private string $classificacaoTributaria;

    private string $baseCalculo;

    private Aliquotas $aliquotas;

    private ?Diferimento $diferimento;

    /**
     * @param int|float|string $baseCalculo Base do IBS/CBS (veja CalculadoraBaseCalculo).
     */
    public function __construct(
        int $numero,
        string $cst,
        string $classificacaoTributaria,
        $baseCalculo,
        ?Aliquotas $aliquotas = null,
        ?Diferimento $diferimento = null
    ) {
        $this->numero = $numero;
        $this->cst = $cst;
        $this->classificacaoTributaria = $classificacaoTributaria;
        $this->baseCalculo = Numero::normalizar($baseCalculo);
        $this->aliquotas = $aliquotas ?? new Aliquotas();
        $this->diferimento = $diferimento;
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

    public function baseCalculo(): string
    {
        return $this->baseCalculo;
    }

    public function aliquotas(): Aliquotas
    {
        return $this->aliquotas;
    }

    public function diferimento(): ?Diferimento
    {
        return $this->diferimento;
    }
}
