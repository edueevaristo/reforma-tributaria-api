<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria;

use DuNf\ReformaTributaria\Excecao\DadoInvalidoException;

/**
 * Tributo calculado por item: IBS estadual, IBS municipal ou CBS.
 *
 * Cada tributo tem a mesma regra de calculo; o que muda sao as aliquotas, a reducao e o diferimento
 * de cada um. Use as instancias nomeadas: Tributo::ibsUf(), Tributo::ibsMun() e Tributo::cbs().
 */
final class Tributo
{
    private const IBS_UF = 'ibsUf';
    private const IBS_MUN = 'ibsMun';
    private const CBS = 'cbs';

    private const NOMES = [
        self::IBS_UF => 'IBS estadual',
        self::IBS_MUN => 'IBS municipal',
        self::CBS => 'CBS',
    ];

    private string $codigo;

    private function __construct(string $codigo)
    {
        $this->codigo = $codigo;
    }

    public static function ibsUf(): self
    {
        return new self(self::IBS_UF);
    }

    public static function ibsMun(): self
    {
        return new self(self::IBS_MUN);
    }

    public static function cbs(): self
    {
        return new self(self::CBS);
    }

    /**
     * @param string $codigo "ibsUf", "ibsMun" ou "cbs" (veja codigo()).
     */
    public static function deCodigo(string $codigo): self
    {
        if (!isset(self::NOMES[$codigo])) {
            throw new DadoInvalidoException(sprintf('Tributo desconhecido: %s.', $codigo));
        }

        return new self($codigo);
    }

    /**
     * Todos os tributos, na ordem em que aparecem no XML.
     *
     * @return self[]
     */
    public static function todos(): array
    {
        return [self::ibsUf(), self::ibsMun(), self::cbs()];
    }

    /**
     * Identificador estavel, usado como chave: "ibsUf", "ibsMun" ou "cbs".
     */
    public function codigo(): string
    {
        return $this->codigo;
    }

    public function nome(): string
    {
        return self::NOMES[$this->codigo];
    }

    public function equals(self $outro): bool
    {
        return $this->codigo === $outro->codigo;
    }
}
