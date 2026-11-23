<?php

declare(strict_types=1);

namespace EduardoEvaristo\ReformaTributaria\Tabelas;

/**
 * Classificacao tributaria (cClassTrib) do IBS e da CBS.
 */
final class ClassificacaoTributaria
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

    public function cst(): string
    {
        return $this->registro['cst'];
    }

    public function descricao(): string
    {
        return $this->registro['descricao'];
    }

    /**
     * Padrao, Fixa, Sem aliquota, Uniforme nacional (referencia), Uniforme setorial
     * ou Aliquotas Combinadas (Ad Valorem e Ad Rem).
     */
    public function tipoAliquota(): string
    {
        return $this->registro['tipoAliquota'];
    }

    /**
     * NCM, NBS, "NBS ou NCM" ou null.
     */
    public function nomenclatura(): ?string
    {
        return $this->registro['nomenclatura'];
    }

    /**
     * Anexo da LC 214/2025 que lista os produtos ou servicos da classificacao, quando houver.
     */
    public function anexo(): ?string
    {
        return $this->registro['anexo'];
    }

    public function inicioVigencia(): string
    {
        return $this->registro['inicioVigencia'];
    }

    public function fimVigencia(): ?string
    {
        return $this->registro['fimVigencia'];
    }

    public function percentualReducaoCbs(): string
    {
        return (string) $this->registro['percentualReducao']['cbs'];
    }

    public function percentualReducaoIbsUf(): string
    {
        return (string) $this->registro['percentualReducao']['ibsUf'];
    }

    public function percentualReducaoIbsMun(): string
    {
        return (string) $this->registro['percentualReducao']['ibsMun'];
    }

    public function permiteCreditoCbsAdquirente(): bool
    {
        return (bool) $this->registro['apropriacaoCreditoAdquirente']['cbs'];
    }

    public function permiteCreditoIbsAdquirente(): bool
    {
        return (bool) $this->registro['apropriacaoCreditoAdquirente']['ibs'];
    }
}
