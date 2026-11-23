<?php

declare(strict_types=1);

namespace EduardoEvaristo\ReformaTributaria\Tabelas;

/**
 * CST do IBS e da CBS.
 */
final class SituacaoTributaria
{
    /** @var array<string, mixed> */
    private array $registro;

    /**
     * @param array<string, mixed> $registro
     */
    public function __construct(array $registro)
    {
        $this->registro = $registro;
    }

    public function codigo(): string
    {
        return $this->registro['codigo'];
    }

    public function descricao(): string
    {
        return $this->registro['descricao'];
    }

    public function inicioVigencia(): string
    {
        return $this->registro['inicioVigencia'];
    }

    public function fimVigencia(): ?string
    {
        return $this->registro['fimVigencia'];
    }

    /**
     * Indica se o grupo informado (gIBSCBS, gRed, gDif, gIBSCBSMono, gTransfCred,
     * gCredPresIBSZFM, gAjusteCompet) deve ser preenchido para este CST.
     */
    public function exigeGrupo(string $grupo): bool
    {
        return !empty($this->registro['grupos'][$grupo]);
    }
}
