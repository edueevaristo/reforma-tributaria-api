<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Integracao;

use PHPUnit\Framework\TestCase;

/**
 * Base dos testes de integracao com o WireMock (wiremock/docker-compose.yml).
 *
 * Rode com: WIREMOCK_URL=http://localhost:8089 vendor/bin/phpunit --testsuite integracao
 */
abstract class WireMockTestCase extends TestCase
{
    protected string $urlWireMock = '';

    protected function setUp(): void
    {
        $url = (string) getenv('WIREMOCK_URL');

        if ($url === '') {
            self::markTestSkipped('Defina WIREMOCK_URL para rodar os testes de integracao (veja docs/wiremock.md).');
        }

        $this->urlWireMock = rtrim($url, '/');
    }
}
