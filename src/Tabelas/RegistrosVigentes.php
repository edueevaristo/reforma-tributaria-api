<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tabelas;

/**
 * Colecao de registros de uma tabela, consultada por codigo e data de vigencia.
 *
 * @template T of RegistroVigente
 */
final class RegistrosVigentes
{
    /** @var T[] */
    private array $registros;

    /**
     * @param T[] $registros
     */
    public function __construct(array $registros)
    {
        $this->registros = $registros;
    }

    /**
     * @return T|null
     */
    public function buscar(string $codigo, \DateTimeInterface $data): ?RegistroVigente
    {
        $encontrados = array_filter(
            $this->vigentes($data),
            static fn (RegistroVigente $registro): bool => $registro->codigo() === $codigo
        );

        return $encontrados === [] ? null : reset($encontrados);
    }

    /**
     * @return T[]
     */
    public function vigentes(\DateTimeInterface $data): array
    {
        return array_values(array_filter(
            $this->registros,
            static fn (RegistroVigente $registro): bool => $registro->vigenteEm($data)
        ));
    }
}
