<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Apoio;

use DuNf\ReformaTributaria\CalculadoraOficial\RespostaHttp;
use DuNf\ReformaTributaria\CalculadoraOficial\TransporteHttp;

/**
 * Transporte HTTP que guarda a requisicao e devolve uma resposta fixa.
 */
final class TransporteFalso implements TransporteHttp
{
    public string $metodo = '';

    public string $url = '';

    public ?string $corpo = null;

    private RespostaHttp $resposta;

    public function __construct(RespostaHttp $resposta)
    {
        $this->resposta = $resposta;
    }

    public function enviar(string $metodo, string $url, array $cabecalhos = [], ?string $corpo = null): RespostaHttp
    {
        $this->metodo = $metodo;
        $this->url = $url;
        $this->corpo = $corpo;

        return $this->resposta;
    }
}
