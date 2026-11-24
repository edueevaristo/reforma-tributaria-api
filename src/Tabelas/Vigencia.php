<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tabelas;

/**
 * Periodo de vigencia de um registro, com inicio e fim inclusivos. Fim null significa sem data de fim.
 */
final class Vigencia
{
    private const FORMATO_DATA = 'Y-m-d';

    private string $inicio;

    private ?string $fim;

    public function __construct(string $inicio, ?string $fim)
    {
        $this->inicio = $inicio;
        $this->fim = $fim;
    }

    /**
     * @param array<string, mixed> $registro Registro com as chaves inicioVigencia e fimVigencia.
     */
    public static function deRegistro(array $registro): self
    {
        return new self((string) $registro['inicioVigencia'], $registro['fimVigencia'] ?? null);
    }

    public function abrange(\DateTimeInterface $data): bool
    {
        $dia = $data->format(self::FORMATO_DATA);

        return $this->inicio <= $dia && ($this->fim === null || $this->fim >= $dia);
    }

    public function inicio(): string
    {
        return $this->inicio;
    }

    public function fim(): ?string
    {
        return $this->fim;
    }
}
