<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Apoio;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Cliente PSR-18 que devolve uma resposta fixa (ou falha) e guarda a ultima requisicao.
 */
final class ClientePsr18Falso implements ClientInterface
{
    private ?ResponseInterface $resposta;

    private ?RequestInterface $ultimaRequisicao = null;

    private ?string $falha;

    public function __construct(?ResponseInterface $resposta, ?string $falha = null)
    {
        $this->resposta = $resposta;
        $this->falha = $falha;
    }

    public static function comFalha(string $mensagem): self
    {
        return new self(null, $mensagem);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->ultimaRequisicao = $request;

        if ($this->resposta === null) {
            throw new class ((string) $this->falha) extends \RuntimeException implements ClientExceptionInterface {
            };
        }

        return $this->resposta;
    }

    public function ultimaRequisicao(): RequestInterface
    {
        if ($this->ultimaRequisicao === null) {
            throw new \LogicException('Nenhuma requisicao foi enviada.');
        }

        return $this->ultimaRequisicao;
    }
}
