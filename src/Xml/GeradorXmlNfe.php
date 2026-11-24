<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Xml;

use DOMDocument;
use DOMElement;
use DuNf\ReformaTributaria\Calculo\ResultadoItem;
use DuNf\ReformaTributaria\Calculo\ResultadoTributo;
use DuNf\ReformaTributaria\Calculo\TotaisIbsCbs;
use DuNf\ReformaTributaria\Suporte\Numero;

/**
 * Gera os grupos IBSCBS (item, dentro de imposto) e IBSCBSTot (total) da NF-e e da NFC-e,
 * conforme a NT 2025.002 e o schema DFeTiposBasicos_v1.00.xsd.
 *
 * Os metodos que recebem um DOMDocument devolvem o elemento pronto para ser anexado
 * ao XML que o seu sistema ja monta (ex.: NFePHP). Os metodos *Xml devolvem string.
 */
final class GeradorXmlNfe
{
    public const NAMESPACE_NFE = 'http://www.portalfiscal.inf.br/nfe';

    /**
     * Grupo det/imposto/IBSCBS.
     */
    public function grupoItem(ResultadoItem $item, DOMDocument $documento): DOMElement
    {
        $ibscbs = $this->elemento($documento, 'IBSCBS');
        $ibscbs->appendChild($this->elemento($documento, 'CST', $item->cst()));
        $ibscbs->appendChild($this->elemento($documento, 'cClassTrib', $item->classificacaoTributaria()));

        if (!$item->possuiGrupoIbsCbs()) {
            return $ibscbs;
        }

        $grupo = $this->elemento($documento, 'gIBSCBS');
        $grupo->appendChild($this->elemento($documento, 'vBC', (string) $item->baseCalculo()));
        $grupo->appendChild($this->grupoTributo($documento, 'gIBSUF', 'pIBSUF', 'vIBSUF', $item->ibsUf()));
        $grupo->appendChild($this->grupoTributo($documento, 'gIBSMun', 'pIBSMun', 'vIBSMun', $item->ibsMun()));
        $grupo->appendChild($this->elemento($documento, 'vIBS', (string) $item->valorIbs()));
        $grupo->appendChild($this->grupoTributo($documento, 'gCBS', 'pCBS', 'vCBS', $item->cbs()));
        $ibscbs->appendChild($grupo);

        return $ibscbs;
    }

    /**
     * Grupo total/IBSCBSTot.
     */
    public function grupoTotal(TotaisIbsCbs $totais, DOMDocument $documento): DOMElement
    {
        $total = $this->elemento($documento, 'IBSCBSTot');
        $total->appendChild($this->elemento($documento, 'vBCIBSCBS', $totais->baseCalculo()));

        if (!$totais->possuiValores()) {
            return $total;
        }

        $ibsUf = $this->elemento($documento, 'gIBSUF');
        $ibsUf->appendChild($this->elemento($documento, 'vDif', $totais->diferimentoIbsUf()));
        $ibsUf->appendChild($this->elemento($documento, 'vDevTrib', '0.00'));
        $ibsUf->appendChild($this->elemento($documento, 'vIBSUF', $totais->ibsUf()));

        $ibsMun = $this->elemento($documento, 'gIBSMun');
        $ibsMun->appendChild($this->elemento($documento, 'vDif', $totais->diferimentoIbsMun()));
        $ibsMun->appendChild($this->elemento($documento, 'vDevTrib', '0.00'));
        $ibsMun->appendChild($this->elemento($documento, 'vIBSMun', $totais->ibsMun()));

        $ibs = $this->elemento($documento, 'gIBS');
        $ibs->appendChild($ibsUf);
        $ibs->appendChild($ibsMun);
        $ibs->appendChild($this->elemento($documento, 'vIBS', $totais->ibs()));
        $ibs->appendChild($this->elemento($documento, 'vCredPres', '0.00'));
        $ibs->appendChild($this->elemento($documento, 'vCredPresCondSus', '0.00'));
        $total->appendChild($ibs);

        $cbs = $this->elemento($documento, 'gCBS');
        $cbs->appendChild($this->elemento($documento, 'vDif', $totais->diferimentoCbs()));
        $cbs->appendChild($this->elemento($documento, 'vDevTrib', '0.00'));
        $cbs->appendChild($this->elemento($documento, 'vCBS', $totais->cbs()));
        $cbs->appendChild($this->elemento($documento, 'vCredPres', '0.00'));
        $cbs->appendChild($this->elemento($documento, 'vCredPresCondSus', '0.00'));
        $total->appendChild($cbs);

        return $total;
    }

    public function grupoItemXml(ResultadoItem $item): string
    {
        $documento = new DOMDocument('1.0', 'UTF-8');
        $documento->appendChild($this->grupoItem($item, $documento));

        return (string) $documento->saveXML($documento->documentElement);
    }

    public function grupoTotalXml(TotaisIbsCbs $totais): string
    {
        $documento = new DOMDocument('1.0', 'UTF-8');
        $documento->appendChild($this->grupoTotal($totais, $documento));

        return (string) $documento->saveXML($documento->documentElement);
    }

    private function grupoTributo(
        DOMDocument $documento,
        string $nomeGrupo,
        string $tagAliquota,
        string $tagValor,
        ResultadoTributo $tributo
    ): DOMElement {
        $grupo = $this->elemento($documento, $nomeGrupo);
        $aliquota = Numero::formatarPercentual($tributo->aliquota());
        $grupo->appendChild($this->elemento($documento, $tagAliquota, $aliquota));

        if ($tributo->percentualDiferimento() !== null) {
            $diferimento = $this->elemento($documento, 'gDif');
            $diferimento->appendChild(
                $this->elemento($documento, 'pDif', Numero::formatarPercentual($tributo->percentualDiferimento()))
            );
            $diferimento->appendChild($this->elemento($documento, 'vDif', (string) $tributo->valorDiferimento()));
            $grupo->appendChild($diferimento);
        }

        if ($tributo->percentualReducao() !== null) {
            $reducao = $this->elemento($documento, 'gRed');
            $reducao->appendChild(
                $this->elemento($documento, 'pRedAliq', Numero::formatarPercentual($tributo->percentualReducao()))
            );
            $aliquotaEfetiva = Numero::formatarPercentual((string) $tributo->aliquotaEfetiva());
            $reducao->appendChild($this->elemento($documento, 'pAliqEfet', $aliquotaEfetiva));
            $grupo->appendChild($reducao);
        }

        $grupo->appendChild($this->elemento($documento, $tagValor, $tributo->valor()));

        return $grupo;
    }

    private function elemento(DOMDocument $documento, string $nome, ?string $valor = null): DOMElement
    {
        $elemento = $documento->createElementNS(self::NAMESPACE_NFE, $nome);

        if ($valor !== null) {
            $elemento->appendChild($documento->createTextNode($valor));
        }

        return $elemento;
    }
}
