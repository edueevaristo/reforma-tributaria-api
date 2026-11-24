<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tabelas;

/**
 * CST do IBS e da CBS (ou do Imposto Seletivo).
 */
final class SituacaoTributaria implements RegistroVigente
{
    public const GRUPO_IBSCBS = 'gIBSCBS';
    public const GRUPO_REDUCAO = 'gRed';
    public const GRUPO_DIFERIMENTO = 'gDif';

    /** Grupos que substituem o gIBSCBS no item (monofasia, transferencia de credito, ZFM e ajuste). */
    public const GRUPOS_SUBSTITUTOS = ['gIBSCBSMono', 'gTransfCred', 'gCredPresIBSZFM', 'gAjusteCompet'];

    private string $codigo;

    private string $descricao;

    private Vigencia $vigencia;

    /** @var array<string, bool> */
    private array $grupos;

    /**
     * @param array<string, bool> $grupos
     */
    public function __construct(string $codigo, string $descricao, Vigencia $vigencia, array $grupos)
    {
        $this->codigo = $codigo;
        $this->descricao = $descricao;
        $this->vigencia = $vigencia;
        $this->grupos = $grupos;
    }

    /**
     * @param array<string, mixed> $registro Registro de resources/dados/cst-*.json
     */
    public static function deRegistro(array $registro): self
    {
        return new self(
            (string) $registro['codigo'],
            (string) $registro['descricao'],
            Vigencia::deRegistro($registro),
            $registro['grupos'] ?? []
        );
    }

    public function codigo(): string
    {
        return $this->codigo;
    }

    public function descricao(): string
    {
        return $this->descricao;
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
     * Indica se o grupo informado (gIBSCBS, gRed, gDif, gIBSCBSMono, gTransfCred,
     * gCredPresIBSZFM, gAjusteCompet) deve ser preenchido para este CST.
     */
    public function exigeGrupo(string $grupo): bool
    {
        return $this->grupos[$grupo] ?? false;
    }

    public function possuiGrupoIbsCbs(): bool
    {
        return $this->exigeGrupo(self::GRUPO_IBSCBS);
    }

    public function exigeReducao(): bool
    {
        return $this->exigeGrupo(self::GRUPO_REDUCAO);
    }

    public function exigeDiferimento(): bool
    {
        return $this->exigeGrupo(self::GRUPO_DIFERIMENTO);
    }

    /**
     * Grupo que substitui o gIBSCBS neste CST, ou null quando o item usa o gIBSCBS.
     */
    public function grupoSubstituto(): ?string
    {
        $exigidos = array_filter(self::GRUPOS_SUBSTITUTOS, fn (string $grupo): bool => $this->exigeGrupo($grupo));

        return $exigidos === [] ? null : reset($exigidos);
    }
}
