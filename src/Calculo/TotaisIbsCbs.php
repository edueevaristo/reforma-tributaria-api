<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Calculo;

use DuNf\ReformaTributaria\Suporte\Decimal;
use DuNf\ReformaTributaria\Tributo;

/**
 * Totais do grupo IBSCBSTot (W03). Cada total e a soma dos valores publicados nos itens,
 * como exige a validacao da SEFAZ (rejeicao 1091 quando o total difere da soma dos itens).
 */
final class TotaisIbsCbs
{
    private Decimal $baseCalculo;

    /** @var array<string, TotalTributo> */
    private array $tributos;

    private bool $possuiValores;

    /**
     * @param array<string, TotalTributo> $tributos
     */
    private function __construct(Decimal $baseCalculo, array $tributos, bool $possuiValores)
    {
        $this->baseCalculo = $baseCalculo;
        $this->tributos = $tributos;
        $this->possuiValores = $possuiValores;
    }

    /**
     * @param ResultadoItem[] $itens
     */
    public static function somar(array $itens): self
    {
        $comGrupo = array_filter($itens, static fn (ResultadoItem $item): bool => $item->possuiGrupoIbsCbs());

        return array_reduce(
            $comGrupo,
            static fn (self $totais, ResultadoItem $item): self => $totais->acumular($item),
            self::zerados()
        );
    }

    /**
     * Indica se algum item tem o grupo gIBSCBS. Sem nenhum, o total leva apenas vBCIBSCBS = 0.
     */
    public function possuiValores(): bool
    {
        return $this->possuiValores;
    }

    public function baseCalculo(): string
    {
        return (string) $this->baseCalculo->valorFiscal();
    }

    /**
     * Soma do valor devido do tributo nos itens (vIBSUF, vIBSMun ou vCBS).
     */
    public function valor(Tributo $tributo): string
    {
        return (string) $this->tributos[$tributo->codigo()]->valor();
    }

    /**
     * Soma do valor diferido do tributo nos itens (vDif).
     */
    public function diferimento(Tributo $tributo): string
    {
        return (string) $this->tributos[$tributo->codigo()]->diferimento();
    }

    public function diferimentoIbsUf(): string
    {
        return $this->diferimento(Tributo::ibsUf());
    }

    public function ibsUf(): string
    {
        return $this->valor(Tributo::ibsUf());
    }

    public function diferimentoIbsMun(): string
    {
        return $this->diferimento(Tributo::ibsMun());
    }

    public function ibsMun(): string
    {
        return $this->valor(Tributo::ibsMun());
    }

    /**
     * vIBS = vIBSUF + vIBSMun.
     */
    public function ibs(): string
    {
        return (string) Decimal::de($this->ibsUf())->somar($this->ibsMun())->valorFiscal();
    }

    public function diferimentoCbs(): string
    {
        return $this->diferimento(Tributo::cbs());
    }

    public function cbs(): string
    {
        return $this->valor(Tributo::cbs());
    }

    private static function zerados(): self
    {
        $tributos = [];
        foreach (Tributo::todos() as $tributo) {
            $tributos[$tributo->codigo()] = TotalTributo::zerado();
        }

        return new self(Decimal::zero(), $tributos, false);
    }

    private function acumular(ResultadoItem $item): self
    {
        $tributos = [];
        foreach ($this->tributos as $codigo => $total) {
            $tributos[$codigo] = $total->acumular($item->tributo(Tributo::deCodigo($codigo)));
        }

        return new self($this->baseCalculo->somar((string) $item->baseCalculo()), $tributos, true);
    }
}
