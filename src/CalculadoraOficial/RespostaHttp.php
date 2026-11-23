<?php

declare(strict_types=1);

namespace EduardoEvaristo\ReformaTributaria\CalculadoraOficial;

final class RespostaHttp
{
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

    public function corpo(): string
    {
        return $this->corpo;
    }
}
