<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Calculo;

use DuNf\ReformaTributaria\Suporte\Decimal;
use DuNf\ReformaTributaria\Tributo;

/**
 * Dados de entrada de um item do documento fiscal.
 */
final class ItemIbsCbs
{
    private int $numero;

    private string $cst;

    private string $classificacaoTributaria;

    private Decimal $baseCalculo;

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
        $this->baseCalculo = Decimal::de($baseCalculo);
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
        return (string) $this->baseCalculo;
    }

    /**
     * Base de calculo publicada (vBC): 2 casas, HALF_EVEN.
     */
    public function baseCalculoFiscal(): Decimal
    {
        return $this->baseCalculo->valorFiscal();
    }

    /**
     * Aliquota informada para o tributo, ou null para usar a fixada em lei.
     */
    public function aliquotaInformada(Tributo $tributo): ?Decimal
    {
        return $this->aliquotas->de($tributo);
    }

    public function informouDiferimento(): bool
    {
        return $this->diferimento !== null;
    }

    /**
     * Percentual de diferimento do tributo, ou null quando o item nao tem diferimento.
     */
    public function percentualDiferimento(Tributo $tributo): ?Decimal
    {
        return $this->diferimento === null ? null : $this->diferimento->de($tributo);
    }
}
