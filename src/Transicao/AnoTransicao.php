<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Transicao;

use DuNf\ReformaTributaria\Suporte\Decimal;
use DuNf\ReformaTributaria\Tributo;

/**
 * Regras de um ano do cronograma de transicao.
 */
final class AnoTransicao
{
    private int $ano;

    private string $descricao;

    private ?string $aliquotaCbs;

    private string $ajusteCbsPontosPercentuais;

    private ?string $aliquotaIbsUf;

    private ?string $aliquotaIbsMun;

    private string $percentualIcmsIss;

    private bool $pisCofinsVigentes;

    private bool $impostoSeletivoVigente;

    /** @var string[] */
    private array $fundamentoLegal;

    /**
     * @param array<string, mixed> $regra Entrada de resources/regras/transicao.php
     */
    public function __construct(int $ano, array $regra)
    {
        $this->ano = $ano;
        $this->descricao = $regra['descricao'];
        $this->aliquotaCbs = $regra['aliquotaCbs'];
        $this->ajusteCbsPontosPercentuais = $regra['ajusteCbsPontosPercentuais'];
        $this->aliquotaIbsUf = $regra['aliquotaIbsUf'];
        $this->aliquotaIbsMun = $regra['aliquotaIbsMun'];
        $this->percentualIcmsIss = $regra['percentualIcmsIss'];
        $this->pisCofinsVigentes = $regra['pisCofinsVigentes'];
        $this->impostoSeletivoVigente = $regra['impostoSeletivoVigente'];
        $this->fundamentoLegal = $regra['fundamentoLegal'];
    }

    public function ano(): int
    {
        return $this->ano;
    }

    public function descricao(): string
    {
        return $this->descricao;
    }

    /**
     * Aliquota da CBS fixada em lei para o ano. Null quando depende da aliquota de referencia
     * definida pelo Senado Federal.
     */
    public function aliquotaCbs(): ?string
    {
        return $this->aliquotaCbs;
    }

    /**
     * Ajuste legal sobre a aliquota de referencia da CBS, em pontos percentuais
     * (ex.: -0.1 em 2027 e 2028).
     */
    public function ajusteCbsPontosPercentuais(): string
    {
        return $this->ajusteCbsPontosPercentuais;
    }

    /**
     * Aliquota do tributo fixada em lei para o ano, ou null quando precisa ser informada
     * (aliquota de referencia do Senado ou lei do ente).
     */
    public function aliquota(Tributo $tributo): ?Decimal
    {
        $aliquotas = [
            Tributo::cbs()->codigo() => $this->aliquotaCbs,
            Tributo::ibsUf()->codigo() => $this->aliquotaIbsUf,
            Tributo::ibsMun()->codigo() => $this->aliquotaIbsMun,
        ];

        return Decimal::deOpcional($aliquotas[$tributo->codigo()]);
    }

    public function aliquotaIbsUf(): ?string
    {
        return $this->aliquotaIbsUf;
    }

    public function aliquotaIbsMun(): ?string
    {
        return $this->aliquotaIbsMun;
    }

    /**
     * Percentual das aliquotas atuais de ICMS e ISS que continua sendo cobrado no ano.
     */
    public function percentualIcmsIss(): string
    {
        return $this->percentualIcmsIss;
    }

    public function pisCofinsVigentes(): bool
    {
        return $this->pisCofinsVigentes;
    }

    public function impostoSeletivoVigente(): bool
    {
        return $this->impostoSeletivoVigente;
    }

    public function icmsIssVigentes(): bool
    {
        return !Decimal::de($this->percentualIcmsIss)->ehZero();
    }

    /**
     * @return string[]
     */
    public function fundamentoLegal(): array
    {
        return $this->fundamentoLegal;
    }
}
