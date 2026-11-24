<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Calculo;

use DuNf\ReformaTributaria\Suporte\Decimal;

/**
 * Resultado de um tributo do item (IBS UF, IBS municipal ou CBS), com os valores ja publicados.
 */
final class ResultadoTributo
{
    private Decimal $aliquota;

    private ?Decimal $percentualReducao;

    private ?Decimal $aliquotaEfetiva;

    private ?Decimal $percentualDiferimento;

    private ?Decimal $valorDiferimento;

    private Decimal $valor;

    private function __construct(
        Decimal $aliquota,
        ?Decimal $percentualReducao,
        ?Decimal $aliquotaEfetiva,
        ?Decimal $percentualDiferimento,
        ?Decimal $valorDiferimento,
        Decimal $valor
    ) {
        $this->aliquota = $aliquota;
        $this->percentualReducao = $percentualReducao;
        $this->aliquotaEfetiva = $aliquotaEfetiva;
        $this->percentualDiferimento = $percentualDiferimento;
        $this->valorDiferimento = $valorDiferimento;
        $this->valor = $valor;
    }

    /**
     * Calcula o tributo com a politica numerica da Calculadora de Tributos oficial (ADR003):
     * - pAliqEfet = arred4(aliquota publicada x (1 - pRedAliq / 100));
     * - valor = arred2(vBC x aliquota aplicada / 100);
     * - vDif = arred2(vBC x aliquota aplicada / 100 x pDif / 100) e valor devido = valor - vDif.
     */
    public static function calcular(
        Decimal $baseCalculo,
        Decimal $aliquota,
        ?Decimal $percentualReducao,
        ?Decimal $percentualDiferimento
    ): self {
        $aliquotaPublicada = $aliquota->aliquotaFiscal();
        $aliquotaEfetiva = self::calcularAliquotaEfetiva($aliquotaPublicada, $percentualReducao);
        $valorBruto = $baseCalculo->percentual($aliquotaEfetiva ?? $aliquotaPublicada);
        $valorDiferimento = self::calcularValorDiferimento($valorBruto, $percentualDiferimento);
        $valor = $valorBruto->valorFiscal()->subtrair($valorDiferimento ?? Decimal::zero())->valorFiscal();

        return new self(
            $aliquotaPublicada,
            self::publicarPercentual($percentualReducao),
            $aliquotaEfetiva,
            self::publicarPercentual($percentualDiferimento),
            $valorDiferimento,
            $valor
        );
    }

    /**
     * Aliquota nominal (pIBSUF, pIBSMun ou pCBS).
     */
    public function aliquota(): string
    {
        return (string) $this->aliquota;
    }

    /**
     * pRedAliq do grupo gRed. Null quando o CST nao tem reducao.
     */
    public function percentualReducao(): ?string
    {
        return self::texto($this->percentualReducao);
    }

    /**
     * pAliqEfet do grupo gRed. Null quando o CST nao tem reducao.
     */
    public function aliquotaEfetiva(): ?string
    {
        return self::texto($this->aliquotaEfetiva);
    }

    public function possuiReducao(): bool
    {
        return $this->percentualReducao !== null;
    }

    /**
     * pDif do grupo gDif. Null quando o CST nao tem diferimento.
     */
    public function percentualDiferimento(): ?string
    {
        return self::texto($this->percentualDiferimento);
    }

    /**
     * vDif do grupo gDif. Null quando o CST nao tem diferimento.
     */
    public function valorDiferimento(): ?string
    {
        return self::texto($this->valorDiferimento);
    }

    public function possuiDiferimento(): bool
    {
        return $this->percentualDiferimento !== null;
    }

    /**
     * Valor devido (vIBSUF, vIBSMun ou vCBS), ja descontado o diferimento.
     */
    public function valor(): string
    {
        return (string) $this->valor;
    }

    private static function calcularAliquotaEfetiva(Decimal $aliquotaPublicada, ?Decimal $percentualReducao): ?Decimal
    {
        if ($percentualReducao === null) {
            return null;
        }

        return $aliquotaPublicada->multiplicar($percentualReducao->fatorRestante())->aliquotaFiscal();
    }

    private static function calcularValorDiferimento(Decimal $valorBruto, ?Decimal $percentualDiferimento): ?Decimal
    {
        if ($percentualDiferimento === null) {
            return null;
        }

        return $valorBruto->percentual($percentualDiferimento)->valorFiscal();
    }

    private static function publicarPercentual(?Decimal $percentual): ?Decimal
    {
        return $percentual === null ? null : $percentual->aliquotaFiscal();
    }

    private static function texto(?Decimal $valor): ?string
    {
        return $valor === null ? null : (string) $valor;
    }
}
