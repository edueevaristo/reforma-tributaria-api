<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\BaseCalculo;

/**
 * Bases de calculo publicadas (2 casas, HALF_EVEN) e avisos de componentes ignorados.
 */
final class ResultadoBaseCalculo
{
    private string $baseRegimeRegular;

    private string $baseImpostoSeletivo;

    private string $baseSimplesNacional;

    /** @var string[] */
    private array $avisos;

    /**
     * @param string[] $avisos
     */
    public function __construct(
        string $baseRegimeRegular,
        string $baseImpostoSeletivo,
        string $baseSimplesNacional,
        array $avisos
    ) {
        $this->baseRegimeRegular = $baseRegimeRegular;
        $this->baseImpostoSeletivo = $baseImpostoSeletivo;
        $this->baseSimplesNacional = $baseSimplesNacional;
        $this->avisos = $avisos;
    }

    /**
     * Base do IBS e da CBS no regime regular (campo vBC do grupo gIBSCBS).
     */
    public function baseRegimeRegular(): string
    {
        return $this->baseRegimeRegular;
    }

    /**
     * Base do Imposto Seletivo (campo vBCIS).
     */
    public function baseImpostoSeletivo(): string
    {
        return $this->baseImpostoSeletivo;
    }

    public function baseSimplesNacional(): string
    {
        return $this->baseSimplesNacional;
    }

    /**
     * @return string[]
     */
    public function avisos(): array
    {
        return $this->avisos;
    }
}
