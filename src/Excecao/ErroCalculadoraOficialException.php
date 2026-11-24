<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Excecao;

/**
 * A Calculadora de Tributos oficial respondeu com erro ou nao pode ser acessada.
 */
class ErroCalculadoraOficialException extends ReformaTributariaException
{
    private int $statusHttp;

    private string $corpoResposta;

    public function __construct(string $mensagem, int $statusHttp = 0, string $corpoResposta = '')
    {
        parent::__construct($mensagem, $statusHttp);
        $this->statusHttp = $statusHttp;
        $this->corpoResposta = $corpoResposta;
    }

    public function statusHttp(): int
    {
        return $this->statusHttp;
    }

    public function corpoResposta(): string
    {
        return $this->corpoResposta;
    }
}
