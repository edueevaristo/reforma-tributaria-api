<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\CalculadoraOficial;

/**
 * Contrato de transporte HTTP. Implemente para usar Guzzle, Symfony HttpClient ou o cliente HTTP do seu projeto.
 */
interface TransporteHttp
{
    /**
     * @param array<string, string> $cabecalhos
     */
    public function enviar(string $metodo, string $url, array $cabecalhos = [], ?string $corpo = null): RespostaHttp;
}
