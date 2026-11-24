<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\BaseCalculo;

use DuNf\ReformaTributaria\Excecao\DadoInvalidoException;

/**
 * Modelo do documento fiscal: NF-e (55) ou NFC-e (65).
 */
final class ModeloDocumento
{
    private const NFE = 55;
    private const NFCE = 65;

    private int $numero;

    private function __construct(int $numero)
    {
        $this->numero = $numero;
    }

    public static function nfe(): self
    {
        return new self(self::NFE);
    }

    public static function nfce(): self
    {
        return new self(self::NFCE);
    }

    /**
     * @param int $numero 55 (NF-e) ou 65 (NFC-e)
     */
    public static function de(int $numero): self
    {
        if (!in_array($numero, [self::NFE, self::NFCE], true)) {
            throw new DadoInvalidoException('Modelo de documento deve ser 55 (NF-e) ou 65 (NFC-e).');
        }

        return new self($numero);
    }

    public function numero(): int
    {
        return $this->numero;
    }

    /**
     * @param int[] $numeros
     */
    public function estaEntre(array $numeros): bool
    {
        return in_array($this->numero, $numeros, true);
    }
}
