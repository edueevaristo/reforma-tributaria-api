<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Transicao;

use DuNf\ReformaTributaria\Excecao\RegraNaoEncontradaException;

/**
 * Cronograma de transicao 2026-2033.
 *
 * Anos posteriores ao ultimo ano cadastrado usam as regras do ultimo ano (sistema pleno).
 */
final class Cronograma
{
    /** @var array<int, array<string, mixed>> */
    private array $regras;

    /**
     * @param array<int, array<string, mixed>>|null $regras Use null para carregar as regras oficiais do pacote.
     */
    public function __construct(?array $regras = null)
    {
        $this->regras = $regras ?? require __DIR__ . '/../../resources/regras/transicao.php';
        ksort($this->regras);
    }

    public function ano(int $ano): AnoTransicao
    {
        $primeiroAno = (int) array_key_first($this->regras);
        $ultimoAno = (int) array_key_last($this->regras);

        if ($ano < $primeiroAno) {
            throw new RegraNaoEncontradaException(
                sprintf('A Reforma Tributaria nao tem regras antes de %d (ano informado: %d).', $primeiroAno, $ano)
            );
        }

        $anoDaRegra = min($ano, $ultimoAno);

        return new AnoTransicao($ano, $this->regras[$anoDaRegra]);
    }

    public function data(\DateTimeInterface $data): AnoTransicao
    {
        return $this->ano((int) $data->format('Y'));
    }

    /**
     * @return AnoTransicao[]
     */
    public function todos(): array
    {
        $anos = [];
        foreach ($this->regras as $ano => $regra) {
            $anos[] = new AnoTransicao($ano, $regra);
        }

        return $anos;
    }
}
