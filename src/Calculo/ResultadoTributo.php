<?php

declare(strict_types=1);

namespace EduardoEvaristo\ReformaTributaria\Calculo;

/**
 * Resultado de um tributo do item (IBS UF, IBS municipal ou CBS), com os valores ja publicados.
 */
final class ResultadoTributo
{
    private string $aliquota;

    private ?string $percentualReducao;

    private ?string $aliquotaEfetiva;

    private ?string $percentualDiferimento;

    private ?string $valorDiferimento;

    private string $valor;

    public function __construct(
        string $aliquota,
        ?string $percentualReducao,
        ?string $aliquotaEfetiva,
        ?string $percentualDiferimento,
        ?string $valorDiferimento,
        string $valor
    ) {
        $this->aliquota = $aliquota;
        $this->percentualReducao = $percentualReducao;
        $this->aliquotaEfetiva = $aliquotaEfetiva;
        $this->percentualDiferimento = $percentualDiferimento;
        $this->valorDiferimento = $valorDiferimento;
        $this->valor = $valor;
    }

    /**
     * Aliquota nominal (pIBSUF, pIBSMun ou pCBS).
     */
    public function aliquota(): string
    {
        return $this->aliquota;
    }

    /**
     * pRedAliq do grupo gRed. Null quando o CST nao tem reducao.
     */
    public function percentualReducao(): ?string
    {
        return $this->percentualReducao;
    }

    /**
     * pAliqEfet do grupo gRed. Null quando o CST nao tem reducao.
     */
    public function aliquotaEfetiva(): ?string
    {
        return $this->aliquotaEfetiva;
    }

    /**
     * pDif do grupo gDif. Null quando o CST nao tem diferimento.
     */
    public function percentualDiferimento(): ?string
    {
        return $this->percentualDiferimento;
    }

    /**
     * vDif do grupo gDif. Null quando o CST nao tem diferimento.
     */
    public function valorDiferimento(): ?string
    {
        return $this->valorDiferimento;
    }

    /**
     * Valor devido (vIBSUF, vIBSMun ou vCBS), ja descontado o diferimento.
     */
    public function valor(): string
    {
        return $this->valor;
    }
}
