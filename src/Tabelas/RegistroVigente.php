<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tabelas;

/**
 * Registro de tabela identificado por codigo e valido dentro de uma vigencia.
 */
interface RegistroVigente
{
    public function codigo(): string;

    public function vigenteEm(\DateTimeInterface $data): bool;
}
