<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tabelas;

use DuNf\ReformaTributaria\Suporte\Decimal;
use DuNf\ReformaTributaria\Tributo;

/**
 * Classificacao tributaria (cClassTrib) do IBS e da CBS (ou do Imposto Seletivo).
 */
final class ClassificacaoTributaria implements RegistroVigente
{
    /** Tipo de aliquota calculado localmente. Os demais exigem tabela propria. */
    public const TIPO_ALIQUOTA_PADRAO = 'Padrão';

    private string $codigo;

    private string $cst;

    private string $descricao;

    private string $tipoAliquota;

    private ?string $nomenclatura;

    private ?string $anexo;

    private Vigencia $vigencia;

    /** @var array<string, Decimal> */
    private array $percentuaisReducao;

    /** @var array<string, bool> */
    private array $creditoAdquirente;

    /**
     * @param array<string, mixed> $registro Registro de resources/dados/cclasstrib-*.json
     */
    private function __construct(array $registro)
    {
        $reducao = $registro['percentualReducao'] ?? [];
        $credito = $registro['apropriacaoCreditoAdquirente'] ?? [];

        $this->codigo = (string) $registro['codigo'];
        $this->cst = (string) $registro['cst'];
        $this->descricao = (string) $registro['descricao'];
        $this->tipoAliquota = (string) $registro['tipoAliquota'];
        $this->nomenclatura = $registro['nomenclatura'] ?? null;
        $this->anexo = $registro['anexo'] ?? null;
        $this->vigencia = Vigencia::deRegistro($registro);
        $this->percentuaisReducao = [
            Tributo::cbs()->codigo() => Decimal::de($reducao['cbs'] ?? 0),
            Tributo::ibsUf()->codigo() => Decimal::de($reducao['ibsUf'] ?? 0),
            Tributo::ibsMun()->codigo() => Decimal::de($reducao['ibsMun'] ?? 0),
        ];
        $this->creditoAdquirente = [
            'cbs' => (bool) ($credito['cbs'] ?? false),
            'ibs' => (bool) ($credito['ibs'] ?? false),
        ];
    }

    /**
     * @param array<string, mixed> $registro Registro de resources/dados/cclasstrib-*.json
     */
    public static function deRegistro(array $registro): self
    {
        return new self($registro);
    }

    public function codigo(): string
    {
        return $this->codigo;
    }

    public function cst(): string
    {
        return $this->cst;
    }

    public function pertenceAoCst(string $cst): bool
    {
        return $this->cst === $cst;
    }

    public function descricao(): string
    {
        return $this->descricao;
    }

    /**
     * Padrao, Fixa, Sem aliquota, Uniforme nacional (referencia), Uniforme setorial
     * ou Aliquotas Combinadas (Ad Valorem e Ad Rem).
     */
    public function tipoAliquota(): string
    {
        return $this->tipoAliquota;
    }

    public function usaAliquotaPadrao(): bool
    {
        return $this->tipoAliquota === self::TIPO_ALIQUOTA_PADRAO;
    }

    /**
     * NCM, NBS, "NBS ou NCM" ou null.
     */
    public function nomenclatura(): ?string
    {
        return $this->nomenclatura;
    }

    /**
     * Anexo da LC 214/2025 que lista os produtos ou servicos da classificacao, quando houver.
     */
    public function anexo(): ?string
    {
        return $this->anexo;
    }

    public function inicioVigencia(): string
    {
        return $this->vigencia->inicio();
    }

    public function fimVigencia(): ?string
    {
        return $this->vigencia->fim();
    }

    public function vigenteEm(\DateTimeInterface $data): bool
    {
        return $this->vigencia->abrange($data);
    }

    /**
     * Percentual de reducao (pRedAliq) do tributo. Sempre 0 nas classificacoes do Imposto Seletivo.
     */
    public function percentualReducao(Tributo $tributo): Decimal
    {
        return $this->percentuaisReducao[$tributo->codigo()];
    }

    public function percentualReducaoCbs(): string
    {
        return (string) $this->percentualReducao(Tributo::cbs());
    }

    public function percentualReducaoIbsUf(): string
    {
        return (string) $this->percentualReducao(Tributo::ibsUf());
    }

    public function percentualReducaoIbsMun(): string
    {
        return (string) $this->percentualReducao(Tributo::ibsMun());
    }

    /**
     * Sempre false nas classificacoes do Imposto Seletivo.
     */
    public function permiteCreditoCbsAdquirente(): bool
    {
        return $this->creditoAdquirente['cbs'];
    }

    /**
     * Sempre false nas classificacoes do Imposto Seletivo.
     */
    public function permiteCreditoIbsAdquirente(): bool
    {
        return $this->creditoAdquirente['ibs'];
    }
}
