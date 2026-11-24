<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\BaseCalculo;

use DuNf\ReformaTributaria\Excecao\DadoInvalidoException;

/**
 * Natureza da operacao do item: fornecimento de bem ou de servico.
 */
final class Natureza
{
    private const BEM = 'bem';
    private const SERVICO = 'servico';

    private string $valor;

    private function __construct(string $valor)
    {
        $this->valor = $valor;
    }

    public static function bem(): self
    {
        return new self(self::BEM);
    }

    public static function servico(): self
    {
        return new self(self::SERVICO);
    }

    /**
     * @param string $valor "bem" ou "servico"
     */
    public static function de(string $valor): self
    {
        if (!in_array($valor, [self::BEM, self::SERVICO], true)) {
            throw new DadoInvalidoException('Natureza deve ser "bem" ou "servico".');
        }

        return new self($valor);
    }

    public function equals(self $outra): bool
    {
        return $this->valor === $outra->valor;
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
