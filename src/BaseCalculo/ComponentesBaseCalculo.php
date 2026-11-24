<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\BaseCalculo;

use DuNf\ReformaTributaria\Suporte\Numero;

/**
 * Valores do item usados na base de calculo, com o nome das tags da NF-e.
 *
 * Exemplo: new ComponentesBaseCalculo(['vProd' => '1000.00', 'vDesc' => '50.00', 'vICMS' => '180.00'])
 */
final class ComponentesBaseCalculo
{
    /** @var array<string, string> */
    private array $valores = [];

    /**
     * @param array<string, int|float|string> $valores
     */
    public function __construct(array $valores)
    {
        foreach ($valores as $tag => $valor) {
            $this->valores[$tag] = Numero::normalizar($valor);
        }
    }

    public function valor(string $tag): string
    {
        return $this->valores[$tag] ?? '0';
    }

    /**
     * @return string[]
     */
    public function tags(): array
    {
        return array_keys($this->valores);
    }
}
