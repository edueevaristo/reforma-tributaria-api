<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\BaseCalculo;

use DuNf\ReformaTributaria\Suporte\Decimal;

/**
 * Regra de um componente da base de calculo unificada (uma entrada de resources/regras/base-calculo.php).
 *
 * @internal
 */
final class RegraComponente
{
    private const OPERACAO_SOMA = 'soma';

    private string $tag;

    /** @var array<string, mixed> */
    private array $regra;

    /**
     * @param array<string, mixed> $regra
     */
    public function __construct(string $tag, array $regra)
    {
        $this->tag = $tag;
        $this->regra = $regra;
    }

    public function tag(): string
    {
        return $this->tag;
    }

    /**
     * Motivo pelo qual o componente nao entra nas bases, ou null quando entra.
     */
    public function motivoExclusao(int $ano, Natureza $natureza, ModeloDocumento $modelo): ?string
    {
        return $this->motivoVigencia($ano)
            ?? $this->motivoNatureza($natureza)
            ?? $this->motivoModelo($modelo);
    }

    public function aplicar(Bases $bases, Decimal $valor): Bases
    {
        $valorComSinal = $this->regra['operacao'] === self::OPERACAO_SOMA ? $valor : Decimal::zero()->subtrair($valor);

        foreach ($this->regra['bases'] as $base) {
            $bases = $bases->acumular($base, $valorComSinal);
        }

        return $bases;
    }

    private function motivoVigencia(int $ano): ?string
    {
        [$inicio, $fim] = $this->regra['vigencia'];

        if ($ano >= $inicio && $ano <= $fim) {
            return null;
        }

        return sprintf(
            'Campo %s nao compoe as bases para o ano %d (vigencia %d-%d) e foi ignorado no calculo',
            $this->tag,
            $ano,
            $inicio,
            $fim
        );
    }

    private function motivoNatureza(Natureza $natureza): ?string
    {
        $naturezaDaRegra = $this->regra['natureza'];

        if ($naturezaDaRegra === null || $natureza->equals(Natureza::de($naturezaDaRegra))) {
            return null;
        }

        return sprintf(
            'Campo %s nao compoe as bases para a natureza %s (aplicavel apenas a %s) e foi ignorado no calculo',
            $this->tag,
            $natureza,
            $naturezaDaRegra
        );
    }

    private function motivoModelo(ModeloDocumento $modelo): ?string
    {
        if ($modelo->estaEntre($this->regra['modelos'])) {
            return null;
        }

        return sprintf(
            'Campo %s nao compoe as bases para o documento modelo %d e foi ignorado no calculo',
            $this->tag,
            $modelo->numero()
        );
    }
}
