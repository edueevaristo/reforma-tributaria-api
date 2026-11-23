<?php

declare(strict_types=1);

namespace EduardoEvaristo\ReformaTributaria\CalculadoraOficial;

use EduardoEvaristo\ReformaTributaria\Excecao\ErroCalculadoraOficialException;

/**
 * Transporte HTTP padrao, com ext-curl.
 */
final class TransporteCurl implements TransporteHttp
{
    private int $timeoutSegundos;

    public function __construct(int $timeoutSegundos = 30)
    {
        $this->timeoutSegundos = $timeoutSegundos;
    }

    public function enviar(string $metodo, string $url, array $cabecalhos = [], ?string $corpo = null): RespostaHttp
    {
        $linhasCabecalho = [];
        foreach ($cabecalhos as $nome => $valor) {
            $linhasCabecalho[] = $nome . ': ' . $valor;
        }

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $metodo);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $linhasCabecalho);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeoutSegundos);

        if ($corpo !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $corpo);
        }

        $resposta = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $erro = curl_error($curl);
        unset($curl);

        if ($resposta === false) {
            throw new ErroCalculadoraOficialException('Falha ao acessar a Calculadora de Tributos: ' . $erro);
        }

        return new RespostaHttp($status, (string) $resposta);
    }
}
