<?php

declare(strict_types=1);

namespace EduardoEvaristo\ReformaTributaria\Calculo;

/**
 * Totais do grupo IBSCBSTot (W03). Cada total e a soma dos valores publicados nos itens,
 * como exige a validacao da SEFAZ (rejeicao 1091 quando o total difere da soma dos itens).
 */
final class TotaisIbsCbs
{
    /** @var array<string, string> */
    private array $valores;

    private bool $possuiValores;

    /**
     * @param array<string, string> $valores
     */
    private function __construct(array $valores, bool $possuiValores)
    {
        $this->valores = $valores;
        $this->possuiValores = $possuiValores;
    }

    /**
     * @param ResultadoItem[] $itens
     */
    public static function somar(array $itens): self
    {
        $campos = [
            'vBCIBSCBS', 'vDifIBSUF', 'vIBSUF', 'vDifIBSMun', 'vIBSMun', 'vIBS', 'vDifCBS', 'vCBS',
        ];
        $valores = array_fill_keys($campos, '0.00');
        $possuiGrupo = false;

        foreach ($itens as $item) {
            if (!$item->possuiGrupoIbsCbs()) {
                continue;
            }

            $possuiGrupo = true;
            $valores['vBCIBSCBS'] = bcadd($valores['vBCIBSCBS'], (string) $item->baseCalculo(), 2);
            $valores['vDifIBSUF'] = bcadd($valores['vDifIBSUF'], $item->ibsUf()->valorDiferimento() ?? '0', 2);
            $valores['vIBSUF'] = bcadd($valores['vIBSUF'], $item->ibsUf()->valor(), 2);
            $valores['vDifIBSMun'] = bcadd($valores['vDifIBSMun'], $item->ibsMun()->valorDiferimento() ?? '0', 2);
            $valores['vIBSMun'] = bcadd($valores['vIBSMun'], $item->ibsMun()->valor(), 2);
            $valores['vIBS'] = bcadd($valores['vIBS'], (string) $item->valorIbs(), 2);
            $valores['vDifCBS'] = bcadd($valores['vDifCBS'], $item->cbs()->valorDiferimento() ?? '0', 2);
            $valores['vCBS'] = bcadd($valores['vCBS'], $item->cbs()->valor(), 2);
        }

        return new self($valores, $possuiGrupo);
    }

    /**
     * Indica se algum item tem o grupo gIBSCBS. Sem nenhum, o total leva apenas vBCIBSCBS = 0.
     */
    public function possuiValores(): bool
    {
        return $this->possuiValores;
    }

    public function baseCalculo(): string
    {
        return $this->valores['vBCIBSCBS'];
    }

    public function diferimentoIbsUf(): string
    {
        return $this->valores['vDifIBSUF'];
    }

    public function ibsUf(): string
    {
        return $this->valores['vIBSUF'];
    }

    public function diferimentoIbsMun(): string
    {
        return $this->valores['vDifIBSMun'];
    }

    public function ibsMun(): string
    {
        return $this->valores['vIBSMun'];
    }

    public function ibs(): string
    {
        return $this->valores['vIBS'];
    }

    public function diferimentoCbs(): string
    {
        return $this->valores['vDifCBS'];
    }

    public function cbs(): string
    {
        return $this->valores['vCBS'];
    }
}
