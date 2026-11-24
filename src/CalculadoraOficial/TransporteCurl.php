<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\CalculadoraOficial;

use DuNf\ReformaTributaria\Excecao\ErroCalculadoraOficialException;

/**
 * Transporte HTTP padrao, com ext-curl.
 */
final class TransporteCurl implements TransporteHttp
{
    private const TIMEOUT_PADRAO_SEGUNDOS = 30;

    private int $timeoutSegundos;

    public function __construct(int $timeoutSegundos = self::TIMEOUT_PADRAO_SEGUNDOS)
    {
        $this->timeoutSegundos = $timeoutSegundos;
    }

    public function enviar(string $metodo, string $url, array $cabecalhos = [], ?string $corpo = null): RespostaHttp
    {
        $curl = curl_init($url);

        if ($curl === false) {
            throw new ErroCalculadoraOficialException('Nao foi possivel iniciar o cURL para ' . $url);
        }

        curl_setopt_array($curl, $this->opcoes($metodo, $cabecalhos, $corpo));
        $resposta = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $erro = curl_error($curl);
        unset($curl);

        if (!is_string($resposta)) {
            throw new ErroCalculadoraOficialException('Falha ao acessar a Calculadora de Tributos: ' . $erro);
        }

        return new RespostaHttp($status, $resposta);
    }

    /**
     * @param array<string, string> $cabecalhos
     * @return array<int, mixed>
     */
    private function opcoes(string $metodo, array $cabecalhos, ?string $corpo): array
    {
        $opcoes = [
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_map(
                static fn (string $nome, string $valor): string => $nome . ': ' . $valor,
                array_keys($cabecalhos),
                array_values($cabecalhos)
            ),
            CURLOPT_TIMEOUT => $this->timeoutSegundos,
        ];

        if ($corpo !== null) {
            $opcoes[CURLOPT_POSTFIELDS] = $corpo;
        }

        return $opcoes;
    }
}
