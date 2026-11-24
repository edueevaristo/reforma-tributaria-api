<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\CalculadoraOficial;

use DuNf\ReformaTributaria\Excecao\ErroCalculadoraOficialException;

/**
 * Cliente da Calculadora de Tributos oficial da Receita Federal (regime regular).
 *
 * A calculadora e uma API REST open source (licenca MIT) que voce executa no seu ambiente:
 * https://github.com/ReceitaFederal/calculadora-rtc-regime-regular
 *
 * Os metodos recebem e devolvem arrays no mesmo formato JSON documentado no Swagger da calculadora.
 */
final class Cliente
{
    public const URL_PADRAO = 'http://localhost:8080/api/calculadora';

    private string $urlBase;

    private TransporteHttp $transporte;

    public function __construct(string $urlBase = self::URL_PADRAO, ?TransporteHttp $transporte = null)
    {
        $this->urlBase = rtrim($urlBase, '/');
        $this->transporte = $transporte ?? new TransporteCurl();
    }

    /**
     * POST /regime-geral. A operacao inteira falha no primeiro erro (HTTP 422).
     *
     * @param array<string, mixed> $operacao
     * @return array<string, mixed>
     */
    public function calcularRegimeGeral(array $operacao): array
    {
        return $this->json('POST', '/regime-geral', $operacao);
    }

    /**
     * POST /observabilidade/regime-geral. Devolve erros por item sem interromper os demais.
     * Confira o campo estadoItem de cada item.
     *
     * @param array<string, mixed> $operacao
     * @return array<string, mixed>
     */
    public function calcularComObservabilidade(array $operacao): array
    {
        return $this->json('POST', '/observabilidade/regime-geral', $operacao);
    }

    /**
     * POST /base-calculo/base-calculo-unificada (NF-e e NFC-e).
     *
     * @param array<string, mixed> $entrada
     * @return array<string, mixed>
     */
    public function baseCalculoUnificada(array $entrada): array
    {
        return $this->json('POST', '/base-calculo/base-calculo-unificada', $entrada);
    }

    /**
     * POST /xml/generate. Recebe a resposta de calcularRegimeGeral e devolve o XML dos grupos.
     *
     * @param array<string, mixed> $resultadoCalculo
     */
    public function gerarXml(array $resultadoCalculo, string $tipoDocumento = 'nfe'): string
    {
        $resposta = $this->enviar(
            'POST',
            '/xml/generate?tipo=' . rawurlencode($tipoDocumento),
            ['Content-Type' => 'application/json', 'Accept' => 'application/xml'],
            $this->codificar($resultadoCalculo)
        );

        return $resposta->corpo();
    }

    /**
     * POST /xml/validate. Retorna true quando o XML e valido; lanca excecao com linha e coluna quando nao e.
     *
     * @param string $subtipo "grupo" (apenas os grupos de tributacao) ou "nota" (documento completo)
     */
    public function validarXml(string $xml, string $tipoDocumento = 'nfe', string $subtipo = 'grupo'): bool
    {
        $this->enviar(
            'POST',
            '/xml/validate?tipo=' . rawurlencode($tipoDocumento) . '&subtipo=' . rawurlencode($subtipo),
            ['Content-Type' => 'application/xml'],
            $xml
        );

        return true;
    }

    /**
     * GET /dados-abertos/classificacoes-tributarias/cbs-ibs/class-trib/{codigo}?data=AAAA-MM-DD
     *
     * @return array<string, mixed>
     */
    public function consultarClassificacaoTributaria(string $codigo, \DateTimeInterface $data): array
    {
        return $this->json(
            'GET',
            '/dados-abertos/classificacoes-tributarias/cbs-ibs/class-trib/' . rawurlencode($codigo)
                . '?data=' . $data->format('Y-m-d')
        );
    }

    /**
     * GET /dados-abertos/ncm?ncm=...&data=AAAA-MM-DD
     *
     * @return array<string, mixed>
     */
    public function consultarNcm(string $ncm, \DateTimeInterface $data): array
    {
        return $this->json('GET', '/dados-abertos/ncm?ncm=' . rawurlencode($ncm) . '&data=' . $data->format('Y-m-d'));
    }

    /**
     * GET generico para os demais endpoints de /dados-abertos.
     *
     * @return array<string, mixed>
     */
    public function consultar(string $caminho): array
    {
        return $this->json('GET', '/' . ltrim($caminho, '/'));
    }

    /**
     * @param array<string, mixed>|null $corpo
     * @return array<string, mixed>
     */
    private function json(string $metodo, string $caminho, ?array $corpo = null): array
    {
        $resposta = $this->enviar(
            $metodo,
            $caminho,
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            $corpo === null ? null : $this->codificar($corpo)
        );

        $dados = json_decode($resposta->corpo(), true);

        if (!is_array($dados)) {
            throw new ErroCalculadoraOficialException(
                'Resposta da Calculadora de Tributos nao e um JSON valido.',
                $resposta->status(),
                $resposta->corpo()
            );
        }

        return $dados;
    }

    /**
     * @param array<string, string> $cabecalhos
     */
    private function enviar(string $metodo, string $caminho, array $cabecalhos, ?string $corpo = null): RespostaHttp
    {
        $resposta = $this->transporte->enviar($metodo, $this->urlBase . $caminho, $cabecalhos, $corpo);

        if ($resposta->status() < 200 || $resposta->status() >= 300) {
            throw new ErroCalculadoraOficialException(
                sprintf('Calculadora de Tributos respondeu HTTP %d em %s %s.', $resposta->status(), $metodo, $caminho),
                $resposta->status(),
                $resposta->corpo()
            );
        }

        return $resposta;
    }

    /**
     * @param array<string, mixed> $dados
     */
    private function codificar(array $dados): string
    {
        $json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        if ($json === false) {
            throw new ErroCalculadoraOficialException(
                'Nao foi possivel converter a requisicao para JSON: ' . json_last_error_msg()
            );
        }

        return $json;
    }
}
