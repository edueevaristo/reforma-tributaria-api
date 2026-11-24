<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Xml;

use DOMDocument;
use DOMElement;
use DuNf\ReformaTributaria\Calculo\ResultadoItem;
use DuNf\ReformaTributaria\Calculo\ResultadoTributo;
use DuNf\ReformaTributaria\Calculo\TotaisIbsCbs;
use DuNf\ReformaTributaria\Suporte\Decimal;
use DuNf\ReformaTributaria\Tributo;

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
     * Valor dos campos de total que ainda nao sao calculados localmente
     * (devolucao de tributos e credito presumido).
     */
    private const VALOR_NAO_CALCULADO = '0.00';

    /** Tags de cada tributo: grupo do item, aliquota e valor. */
    private const TAGS = [
        'ibsUf' => ['grupo' => 'gIBSUF', 'aliquota' => 'pIBSUF', 'valor' => 'vIBSUF'],
        'ibsMun' => ['grupo' => 'gIBSMun', 'aliquota' => 'pIBSMun', 'valor' => 'vIBSMun'],
        'cbs' => ['grupo' => 'gCBS', 'aliquota' => 'pCBS', 'valor' => 'vCBS'],
    ];

    /**
     * Grupo det/imposto/IBSCBS.
     */
    public function grupoItem(ResultadoItem $item, DOMDocument $documento): DOMElement
    {
        $construtor = new ConstrutorXml($documento, self::NAMESPACE_NFE);
        $ibscbs = $construtor->grupo('IBSCBS', [
            $construtor->campo('CST', $item->cst()),
            $construtor->campo('cClassTrib', $item->classificacaoTributaria()),
        ]);

        if (!$item->possuiGrupoIbsCbs()) {
            return $ibscbs;
        }

        $ibscbs->appendChild($construtor->grupo('gIBSCBS', [
            $construtor->campo('vBC', (string) $item->baseCalculo()),
            $this->grupoTributo($construtor, Tributo::ibsUf(), $item),
            $this->grupoTributo($construtor, Tributo::ibsMun(), $item),
            $construtor->campo('vIBS', (string) $item->valorIbs()),
            $this->grupoTributo($construtor, Tributo::cbs(), $item),
        ]));

        return $ibscbs;
    }

    /**
     * Grupo total/IBSCBSTot.
     */
    public function grupoTotal(TotaisIbsCbs $totais, DOMDocument $documento): DOMElement
    {
        $construtor = new ConstrutorXml($documento, self::NAMESPACE_NFE);
        $total = $construtor->grupo('IBSCBSTot', [$construtor->campo('vBCIBSCBS', $totais->baseCalculo())]);

        if (!$totais->possuiValores()) {
            return $total;
        }

        $total->appendChild($construtor->grupo('gIBS', [
            $this->totalTributo($construtor, Tributo::ibsUf(), $totais),
            $this->totalTributo($construtor, Tributo::ibsMun(), $totais),
            $construtor->campo('vIBS', $totais->ibs()),
            ...$this->creditoPresumido($construtor),
        ]));
        $total->appendChild($construtor->grupo('gCBS', [
            ...$this->camposTotalTributo($construtor, Tributo::cbs(), $totais),
            ...$this->creditoPresumido($construtor),
        ]));

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

    /**
     * Grupo gIBSUF, gIBSMun ou gCBS do item.
     */
    private function grupoTributo(ConstrutorXml $construtor, Tributo $tributo, ResultadoItem $item): DOMElement
    {
        $tags = self::TAGS[$tributo->codigo()];
        $resultado = $item->tributo($tributo);

        if ($resultado === null) {
            throw new \LogicException(sprintf('Item %d sem o resultado de %s.', $item->numero(), $tributo->nome()));
        }

        return $construtor->grupo($tags['grupo'], [
            $construtor->campo($tags['aliquota'], $this->percentual($resultado->aliquota())),
            $this->grupoDiferimento($construtor, $resultado),
            $this->grupoReducao($construtor, $resultado),
            $construtor->campo($tags['valor'], $resultado->valor()),
        ]);
    }

    private function grupoDiferimento(ConstrutorXml $construtor, ResultadoTributo $resultado): ?DOMElement
    {
        if (!$resultado->possuiDiferimento()) {
            return null;
        }

        return $construtor->grupo('gDif', [
            $construtor->campo('pDif', $this->percentual((string) $resultado->percentualDiferimento())),
            $construtor->campo('vDif', (string) $resultado->valorDiferimento()),
        ]);
    }

    private function grupoReducao(ConstrutorXml $construtor, ResultadoTributo $resultado): ?DOMElement
    {
        if (!$resultado->possuiReducao()) {
            return null;
        }

        return $construtor->grupo('gRed', [
            $construtor->campo('pRedAliq', $this->percentual((string) $resultado->percentualReducao())),
            $construtor->campo('pAliqEfet', $this->percentual((string) $resultado->aliquotaEfetiva())),
        ]);
    }

    /**
     * Grupo gIBSUF ou gIBSMun do total.
     */
    private function totalTributo(ConstrutorXml $construtor, Tributo $tributo, TotaisIbsCbs $totais): DOMElement
    {
        return $construtor->grupo(
            self::TAGS[$tributo->codigo()]['grupo'],
            $this->camposTotalTributo($construtor, $tributo, $totais)
        );
    }

    /**
     * @return DOMElement[]
     */
    private function camposTotalTributo(ConstrutorXml $construtor, Tributo $tributo, TotaisIbsCbs $totais): array
    {
        return [
            $construtor->campo('vDif', $totais->diferimento($tributo)),
            $construtor->campo('vDevTrib', self::VALOR_NAO_CALCULADO),
            $construtor->campo(self::TAGS[$tributo->codigo()]['valor'], $totais->valor($tributo)),
        ];
    }

    /**
     * @return DOMElement[]
     */
    private function creditoPresumido(ConstrutorXml $construtor): array
    {
        return [
            $construtor->campo('vCredPres', self::VALOR_NAO_CALCULADO),
            $construtor->campo('vCredPresCondSus', self::VALOR_NAO_CALCULADO),
        ];
    }

    private function percentual(string $valor): string
    {
        return Decimal::de($valor)->percentualXml();
    }
}
