<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Calculo;

/**
 * Resultado do calculo de todos os itens de um documento fiscal.
 */
final class ResultadoDocumento
{
    /** @var ResultadoItem[] */
    private array $itens;

    private TotaisIbsCbs $totais;

    /**
     * @param ResultadoItem[] $itens
     */
    public function __construct(array $itens, TotaisIbsCbs $totais)
    {
        $this->itens = $itens;
        $this->totais = $totais;
    }

    /**
     * @return ResultadoItem[]
     */
    public function itens(): array
    {
        return $this->itens;
    }

    public function totais(): TotaisIbsCbs
    {
        return $this->totais;
    }
}
