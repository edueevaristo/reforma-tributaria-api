<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Calculo;

use DuNf\ReformaTributaria\Excecao\DadoInvalidoException;
use DuNf\ReformaTributaria\Suporte\Decimal;
use DuNf\ReformaTributaria\Tabelas\ClassificacaoTributaria;
use DuNf\ReformaTributaria\Tabelas\SituacaoTributaria;
use DuNf\ReformaTributaria\Tributo;

/**
 * Enquadramento tributario do item: o CST e a cClassTrib, que precisam ser compativeis entre si.
 *
 * @internal
 */
final class Enquadramento
{
    /** CST com grupo gIBSCBS calculados localmente. */
    public const CST_SUPORTADOS = ['000', '200', '510', '515'];

    private SituacaoTributaria $situacao;

    private ClassificacaoTributaria $classificacao;

    public function __construct(SituacaoTributaria $situacao, ClassificacaoTributaria $classificacao)
    {
        if (!$classificacao->pertenceAoCst($situacao->codigo())) {
            throw new DadoInvalidoException(sprintf(
                'cClassTrib %s pertence ao CST %s, mas foi informado o CST %s.',
                $classificacao->codigo(),
                $classificacao->cst(),
                $situacao->codigo()
            ));
        }

        $this->situacao = $situacao;
        $this->classificacao = $classificacao;
    }

    public function possuiGrupoIbsCbs(): bool
    {
        return $this->situacao->possuiGrupoIbsCbs();
    }

    /**
     * Grupo que substitui o gIBSCBS e ainda nao e gerado localmente, ou null.
     */
    public function grupoSubstituto(): ?string
    {
        return $this->situacao->grupoSubstituto();
    }

    /**
     * Motivo pelo qual o gIBSCBS nao e calculado localmente, ou null quando e.
     */
    public function motivoCalculoNaoLocal(): ?string
    {
        if (!in_array($this->situacao->codigo(), self::CST_SUPORTADOS, true)) {
            return 'o CST ainda nao e calculado localmente';
        }

        if (!$this->classificacao->usaAliquotaPadrao()) {
            return sprintf('o tipo de aliquota "%s" exige tabela propria', $this->classificacao->tipoAliquota());
        }

        return null;
    }

    public function exigeDiferimento(): bool
    {
        return $this->situacao->exigeDiferimento();
    }

    /**
     * Percentual de reducao do tributo (gRed), ou null quando o CST nao tem reducao.
     */
    public function percentualReducao(Tributo $tributo): ?Decimal
    {
        return $this->situacao->exigeReducao() ? $this->classificacao->percentualReducao($tributo) : null;
    }
}
