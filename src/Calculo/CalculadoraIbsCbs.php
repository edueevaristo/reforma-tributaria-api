<?php

declare(strict_types=1);

namespace EduardoEvaristo\ReformaTributaria\Calculo;

use EduardoEvaristo\ReformaTributaria\Excecao\DadoInvalidoException;
use EduardoEvaristo\ReformaTributaria\Excecao\OperacaoNaoSuportadaException;
use EduardoEvaristo\ReformaTributaria\Suporte\Numero;
use EduardoEvaristo\ReformaTributaria\Tabelas\ClassificacaoTributaria;
use EduardoEvaristo\ReformaTributaria\Tabelas\SituacaoTributaria;
use EduardoEvaristo\ReformaTributaria\Tabelas\TabelaClassificacaoTributaria;
use EduardoEvaristo\ReformaTributaria\Tabelas\TabelaSituacaoTributaria;
use EduardoEvaristo\ReformaTributaria\Transicao\AnoTransicao;
use EduardoEvaristo\ReformaTributaria\Transicao\Cronograma;

/**
 * Calculo local do IBS e da CBS por item, no regime regular.
 *
 * Segue a politica numerica da Calculadora de Tributos oficial (ADR003):
 * - vBC arredondado a 2 casas antes de multiplicar;
 * - pAliqEfet = aliquota x (1 - pRedAliq / 100), arredondada a 4 casas;
 * - valor = arred2(vBC x aliquota publicada / 100);
 * - vDif = arred2(vBC x aliquota publicada / 100 x pDif / 100) e valor devido = valor - vDif;
 * - arredondamento HALF_EVEN.
 *
 * Casos calculados localmente: CST 000, 200, 510 e 515 com aliquota do tipo "Padrao",
 * e CST sem grupo gIBSCBS (400, 410 e 820). Os demais lancam OperacaoNaoSuportadaException
 * e devem ser calculados pela Calculadora de Tributos oficial.
 */
final class CalculadoraIbsCbs
{
    /** CST com grupo gIBSCBS calculados localmente. */
    public const CST_SUPORTADOS = ['000', '200', '510', '515'];

    private const TIPO_ALIQUOTA_PADRAO = 'Padrão';

    /** Grupos que substituem o gIBSCBS e ainda nao sao gerados localmente. */
    private const GRUPOS_NAO_SUPORTADOS = ['gIBSCBSMono', 'gTransfCred', 'gCredPresIBSZFM', 'gAjusteCompet'];

    private Cronograma $cronograma;

    private TabelaSituacaoTributaria $tabelaCst;

    private TabelaClassificacaoTributaria $tabelaClassificacao;

    public function __construct(
        ?Cronograma $cronograma = null,
        ?TabelaSituacaoTributaria $tabelaCst = null,
        ?TabelaClassificacaoTributaria $tabelaClassificacao = null
    ) {
        $this->cronograma = $cronograma ?? new Cronograma();
        $this->tabelaCst = $tabelaCst ?? new TabelaSituacaoTributaria();
        $this->tabelaClassificacao = $tabelaClassificacao ?? new TabelaClassificacaoTributaria();
    }

    public function calcularItem(ItemIbsCbs $item, \DateTimeInterface $dataFatoGerador): ResultadoItem
    {
        $situacao = $this->tabelaCst->buscar($item->cst(), $dataFatoGerador);
        $classificacao = $this->tabelaClassificacao->buscar($item->classificacaoTributaria(), $dataFatoGerador);

        if ($classificacao->cst() !== $situacao->codigo()) {
            throw new DadoInvalidoException(sprintf(
                'cClassTrib %s pertence ao CST %s, mas foi informado o CST %s.',
                $classificacao->codigo(),
                $classificacao->cst(),
                $situacao->codigo()
            ));
        }

        foreach (self::GRUPOS_NAO_SUPORTADOS as $grupo) {
            if ($situacao->exigeGrupo($grupo)) {
                throw $this->naoSuportado($item, 'o CST exige o grupo ' . $grupo);
            }
        }

        if (!$situacao->exigeGrupo('gIBSCBS')) {
            return new ResultadoItem($item->numero(), $item->cst(), $item->classificacaoTributaria());
        }

        if (!in_array($situacao->codigo(), self::CST_SUPORTADOS, true)) {
            throw $this->naoSuportado($item, 'o CST ainda nao e calculado localmente');
        }

        if ($classificacao->tipoAliquota() !== self::TIPO_ALIQUOTA_PADRAO) {
            throw $this->naoSuportado(
                $item,
                'o tipo de aliquota "' . $classificacao->tipoAliquota() . '" exige tabela propria'
            );
        }

        if ($situacao->exigeGrupo('gDif') && $item->diferimento() === null) {
            throw new DadoInvalidoException(sprintf(
                'Item %d: o CST %s exige o percentual de diferimento (informe um objeto Diferimento).',
                $item->numero(),
                $item->cst()
            ));
        }

        $ano = $this->cronograma->data($dataFatoGerador);
        $baseCalculo = Numero::valorFiscal($item->baseCalculo());
        $diferimento = $item->diferimento();

        $ibsUf = $this->calcularTributo(
            $baseCalculo,
            $this->aliquota($item->aliquotas()->ibsUf(), $ano->aliquotaIbsUf(), 'IBS estadual', $ano),
            $situacao->exigeGrupo('gRed') ? $classificacao->percentualReducaoIbsUf() : null,
            $situacao->exigeGrupo('gDif') && $diferimento !== null ? $diferimento->ibsUf() : null
        );

        $ibsMun = $this->calcularTributo(
            $baseCalculo,
            $this->aliquota($item->aliquotas()->ibsMun(), $ano->aliquotaIbsMun(), 'IBS municipal', $ano),
            $situacao->exigeGrupo('gRed') ? $classificacao->percentualReducaoIbsMun() : null,
            $situacao->exigeGrupo('gDif') && $diferimento !== null ? $diferimento->ibsMun() : null
        );

        $cbs = $this->calcularTributo(
            $baseCalculo,
            $this->aliquota($item->aliquotas()->cbs(), $ano->aliquotaCbs(), 'CBS', $ano),
            $situacao->exigeGrupo('gRed') ? $classificacao->percentualReducaoCbs() : null,
            $situacao->exigeGrupo('gDif') && $diferimento !== null ? $diferimento->cbs() : null
        );

        return new ResultadoItem(
            $item->numero(),
            $item->cst(),
            $item->classificacaoTributaria(),
            $baseCalculo,
            $ibsUf,
            $ibsMun,
            $cbs
        );
    }

    /**
     * Calcula todos os itens e os totais do documento.
     *
     * @param ItemIbsCbs[] $itens
     */
    public function calcularDocumento(array $itens, \DateTimeInterface $dataFatoGerador): ResultadoDocumento
    {
        $resultados = [];
        foreach ($itens as $item) {
            $resultados[] = $this->calcularItem($item, $dataFatoGerador);
        }

        return new ResultadoDocumento($resultados, TotaisIbsCbs::somar($resultados));
    }

    private function calcularTributo(
        string $baseCalculo,
        string $aliquota,
        ?string $percentualReducao,
        ?string $percentualDiferimento
    ): ResultadoTributo {
        $aliquotaPublicada = Numero::aliquotaFiscal($aliquota);
        $aliquotaAplicada = $aliquotaPublicada;
        $aliquotaEfetiva = null;

        if ($percentualReducao !== null) {
            $fatorReducao = Numero::subtrair('1', Numero::aplicarPercentual('1', $percentualReducao));
            $aliquotaEfetiva = Numero::aliquotaFiscal(Numero::multiplicar($aliquotaPublicada, $fatorReducao));
            $aliquotaAplicada = $aliquotaEfetiva;
        }

        $valorBruto = Numero::aplicarPercentual($baseCalculo, $aliquotaAplicada);
        $valor = Numero::valorFiscal($valorBruto);
        $valorDiferimento = null;

        if ($percentualDiferimento !== null) {
            $valorDiferimento = Numero::valorFiscal(Numero::aplicarPercentual($valorBruto, $percentualDiferimento));
            $valor = bcsub($valor, $valorDiferimento, 2);
        }

        return new ResultadoTributo(
            $aliquotaPublicada,
            $percentualReducao === null ? null : Numero::aliquotaFiscal($percentualReducao),
            $aliquotaEfetiva,
            $percentualDiferimento === null ? null : Numero::aliquotaFiscal($percentualDiferimento),
            $valorDiferimento,
            $valor
        );
    }

    private function aliquota(?string $informada, ?string $fixadaEmLei, string $tributo, AnoTransicao $ano): string
    {
        $aliquota = $informada ?? $fixadaEmLei;

        if ($aliquota === null) {
            throw new DadoInvalidoException(sprintf(
                'Informe a aliquota de %s para %d: ela nao e fixada em lei para este ano. %s',
                $tributo,
                $ano->ano(),
                $ano->descricao()
            ));
        }

        return $aliquota;
    }

    private function naoSuportado(ItemIbsCbs $item, string $motivo): OperacaoNaoSuportadaException
    {
        return new OperacaoNaoSuportadaException(sprintf(
            'Item %d (CST %s, cClassTrib %s): %s. Use a Calculadora de Tributos oficial (CalculadoraOficial\Cliente).',
            $item->numero(),
            $item->cst(),
            $item->classificacaoTributaria(),
            $motivo
        ));
    }
}
