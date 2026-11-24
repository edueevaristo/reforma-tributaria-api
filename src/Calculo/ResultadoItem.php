<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Calculo;

use DuNf\ReformaTributaria\Suporte\Decimal;
use DuNf\ReformaTributaria\Tributo;

/**
 * Resultado do calculo de um item. Os tributos sao null quando o CST nao tem o grupo gIBSCBS
 * (ex.: 400 isencao e 410 imunidade), caso em que o XML leva apenas CST e cClassTrib.
 */
final class ResultadoItem
{
    private int $numero;

    private string $cst;

    private string $classificacaoTributaria;

    private ?Decimal $baseCalculo;

    /** @var array<string, ResultadoTributo> */
    private array $tributos;

    /**
     * @param array<string, ResultadoTributo> $tributos
     */
    private function __construct(
        int $numero,
        string $cst,
        string $classificacaoTributaria,
        ?Decimal $baseCalculo,
        array $tributos
    ) {
        $this->numero = $numero;
        $this->cst = $cst;
        $this->classificacaoTributaria = $classificacaoTributaria;
        $this->baseCalculo = $baseCalculo;
        $this->tributos = $tributos;
    }

    /**
     * Item cujo CST nao tem o grupo gIBSCBS.
     */
    public static function semGrupoIbsCbs(ItemIbsCbs $item): self
    {
        return new self($item->numero(), $item->cst(), $item->classificacaoTributaria(), null, []);
    }

    /**
     * Item com o grupo gIBSCBS.
     *
     * @param ResultadoTributo[] $tributos Indexado pelo codigo do Tributo.
     */
    public static function comGrupoIbsCbs(ItemIbsCbs $item, Decimal $baseCalculo, array $tributos): self
    {
        foreach (Tributo::todos() as $tributo) {
            self::garantirTributo($tributos, $tributo);
        }

        return new self($item->numero(), $item->cst(), $item->classificacaoTributaria(), $baseCalculo, $tributos);
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
        return $this->baseCalculo === null ? null : (string) $this->baseCalculo;
    }

    /**
     * Resultado do tributo, ou null quando o item nao tem o grupo gIBSCBS.
     */
    public function tributo(Tributo $tributo): ?ResultadoTributo
    {
        return $this->tributos[$tributo->codigo()] ?? null;
    }

    public function ibsUf(): ?ResultadoTributo
    {
        return $this->tributo(Tributo::ibsUf());
    }

    public function ibsMun(): ?ResultadoTributo
    {
        return $this->tributo(Tributo::ibsMun());
    }

    public function cbs(): ?ResultadoTributo
    {
        return $this->tributo(Tributo::cbs());
    }

    /**
     * vIBS = vIBSUF + vIBSMun.
     */
    public function valorIbs(): ?string
    {
        $ibsUf = $this->ibsUf();
        $ibsMun = $this->ibsMun();

        if ($ibsUf === null || $ibsMun === null) {
            return null;
        }

        return (string) Decimal::de($ibsUf->valor())->somar($ibsMun->valor())->valorFiscal();
    }

    /**
     * @param ResultadoTributo[] $tributos
     */
    private static function garantirTributo(array $tributos, Tributo $tributo): void
    {
        if (!isset($tributos[$tributo->codigo()])) {
            throw new \LogicException('Item com o grupo gIBSCBS sem o resultado de ' . $tributo->nome() . '.');
        }
    }
}
