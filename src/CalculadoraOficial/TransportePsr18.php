<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\CalculadoraOficial;

use DuNf\ReformaTributaria\Excecao\ErroCalculadoraOficialException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Transporte HTTP sobre qualquer cliente PSR-18 (Guzzle, Symfony HttpClient, etc.), com fabricas PSR-17.
 *
 * Requer os pacotes psr/http-client e psr/http-factory, e uma implementacao deles no seu projeto.
 *
 * Exemplo com Guzzle 7:
 *     $fabrica = new \GuzzleHttp\Psr7\HttpFactory();
 *     new TransportePsr18(new \GuzzleHttp\Client(), $fabrica, $fabrica);
 */
final class TransportePsr18 implements TransporteHttp
{
    private ClientInterface $cliente;

    private RequestFactoryInterface $fabricaRequisicao;

    private StreamFactoryInterface $fabricaCorpo;

    public function __construct(
        ClientInterface $cliente,
        RequestFactoryInterface $fabricaRequisicao,
        StreamFactoryInterface $fabricaCorpo
    ) {
        $this->cliente = $cliente;
        $this->fabricaRequisicao = $fabricaRequisicao;
        $this->fabricaCorpo = $fabricaCorpo;
    }

    public function enviar(string $metodo, string $url, array $cabecalhos = [], ?string $corpo = null): RespostaHttp
    {
        try {
            $resposta = $this->cliente->sendRequest($this->requisicao($metodo, $url, $cabecalhos, $corpo));
        } catch (ClientExceptionInterface $erro) {
            throw new ErroCalculadoraOficialException(
                'Falha ao acessar a Calculadora de Tributos: ' . $erro->getMessage(),
                0,
                '',
                $erro
            );
        }

        return new RespostaHttp($resposta->getStatusCode(), (string) $resposta->getBody());
    }

    /**
     * @param array<string, string> $cabecalhos
     */
    private function requisicao(string $metodo, string $url, array $cabecalhos, ?string $corpo): RequestInterface
    {
        $requisicao = $this->fabricaRequisicao->createRequest($metodo, $url);

        foreach ($cabecalhos as $nome => $valor) {
            $requisicao = $requisicao->withHeader($nome, $valor);
        }

        if ($corpo === null) {
            return $requisicao;
        }

        return $requisicao->withBody($this->fabricaCorpo->createStream($corpo));
    }
}
