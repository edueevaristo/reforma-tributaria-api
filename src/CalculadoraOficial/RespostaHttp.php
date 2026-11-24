<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\CalculadoraOficial;

/**
 * Resposta HTTP devolvida por um TransporteHttp.
 */
final class RespostaHttp
{
    private const SUCESSO_INICIO = 200;

    private const SUCESSO_FIM = 299;

    private int $status;

    private string $corpo;

    public function __construct(int $status, string $corpo)
    {
        $this->status = $status;
        $this->corpo = $corpo;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * Status 2xx.
     */
    public function sucesso(): bool
    {
        return $this->status >= self::SUCESSO_INICIO && $this->status <= self::SUCESSO_FIM;
    }

    public function corpo(): string
    {
        return $this->corpo;
    }
}
