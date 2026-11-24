<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Calculo;

use DuNf\ReformaTributaria\Excecao\DadoInvalidoException;
use DuNf\ReformaTributaria\Excecao\OperacaoNaoSuportadaException;
use DuNf\ReformaTributaria\Suporte\Decimal;
use DuNf\ReformaTributaria\Tabelas\TabelaClassificacaoTributaria;
use DuNf\ReformaTributaria\Tabelas\TabelaSituacaoTributaria;
use DuNf\ReformaTributaria\Transicao\AnoTransicao;
use DuNf\ReformaTributaria\Transicao\Cronograma;
use DuNf\ReformaTributaria\Tributo;

/**
 * Calculo local do IBS e da CBS por item, no regime regular.
 *
 * Segue a politica numerica da Calculadora de Tributos oficial (ADR003); veja ResultadoTributo::calcular().
 * A base de calculo (vBC) e arredondada a 2 casas antes de multiplicar.
 *
 * Casos calculados localmente: CST 000, 200, 510 e 515 com aliquota do tipo "Padrao",
 * e CST sem grupo gIBSCBS (400, 410 e 820). Os demais lancam OperacaoNaoSuportadaException
 * e devem ser calculados pela Calculadora de Tributos oficial.
 */
final class CalculadoraIbsCbs
{
    /** CST com grupo gIBSCBS calculados localmente. */
    public const CST_SUPORTADOS = Enquadramento::CST_SUPORTADOS;

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
        $enquadramento = $this->enquadrar($item, $dataFatoGerador);

        if (!$enquadramento->possuiGrupoIbsCbs()) {
            return ResultadoItem::semGrupoIbsCbs($item);
        }

        $this->garantirCalculoLocal($item, $enquadramento->motivoCalculoNaoLocal());
        $this->garantirDiferimentoInformado($item, $enquadramento);

        return $this->calcularGrupoIbsCbs($item, $enquadramento, $this->cronograma->data($dataFatoGerador));
    }

    /**
     * Calcula todos os itens e os totais do documento.
     *
     * @param ItemIbsCbs[] $itens
     */
    public function calcularDocumento(array $itens, \DateTimeInterface $dataFatoGerador): ResultadoDocumento
    {
        $resultados = array_map(
            fn (ItemIbsCbs $item): ResultadoItem => $this->calcularItem($item, $dataFatoGerador),
            array_values($itens)
        );

        return new ResultadoDocumento($resultados, TotaisIbsCbs::somar($resultados));
    }

    private function enquadrar(ItemIbsCbs $item, \DateTimeInterface $dataFatoGerador): Enquadramento
    {
        $enquadramento = new Enquadramento(
            $this->tabelaCst->buscar($item->cst(), $dataFatoGerador),
            $this->tabelaClassificacao->buscar($item->classificacaoTributaria(), $dataFatoGerador)
        );

        $grupoSubstituto = $enquadramento->grupoSubstituto();
        $motivo = $grupoSubstituto === null ? null : 'o CST exige o grupo ' . $grupoSubstituto;
        $this->garantirCalculoLocal($item, $motivo);

        return $enquadramento;
    }

    private function calcularGrupoIbsCbs(
        ItemIbsCbs $item,
        Enquadramento $enquadramento,
        AnoTransicao $ano
    ): ResultadoItem {
        $baseCalculo = $item->baseCalculoFiscal();
        $tributos = [];

        foreach (Tributo::todos() as $tributo) {
            $tributos[$tributo->codigo()] = ResultadoTributo::calcular(
                $baseCalculo,
                $this->aliquota($item, $tributo, $ano),
                $enquadramento->percentualReducao($tributo),
                $enquadramento->exigeDiferimento() ? $item->percentualDiferimento($tributo) : null
            );
        }

        return ResultadoItem::comGrupoIbsCbs($item, $baseCalculo, $tributos);
    }

    /**
     * A aliquota informada no item tem prioridade; sem ela, vale a fixada em lei para o ano.
     */
    private function aliquota(ItemIbsCbs $item, Tributo $tributo, AnoTransicao $ano): Decimal
    {
        $aliquota = $item->aliquotaInformada($tributo) ?? $ano->aliquota($tributo);

        if ($aliquota === null) {
            throw new DadoInvalidoException(sprintf(
                'Informe a aliquota de %s para %d: ela nao e fixada em lei para este ano. %s',
                $tributo->nome(),
                $ano->ano(),
                $ano->descricao()
            ));
        }

        return $aliquota;
    }

    private function garantirCalculoLocal(ItemIbsCbs $item, ?string $motivo): void
    {
        if ($motivo === null) {
            return;
        }

        throw new OperacaoNaoSuportadaException(sprintf(
            'Item %d (CST %s, cClassTrib %s): %s. Use a Calculadora de Tributos oficial (CalculadoraOficial\Cliente).',
            $item->numero(),
            $item->cst(),
            $item->classificacaoTributaria(),
            $motivo
        ));
    }

    private function garantirDiferimentoInformado(ItemIbsCbs $item, Enquadramento $enquadramento): void
    {
        if (!$enquadramento->exigeDiferimento() || $item->informouDiferimento()) {
            return;
        }

        throw new DadoInvalidoException(sprintf(
            'Item %d: o CST %s exige o percentual de diferimento (informe um objeto Diferimento).',
            $item->numero(),
            $item->cst()
        ));
    }
}
