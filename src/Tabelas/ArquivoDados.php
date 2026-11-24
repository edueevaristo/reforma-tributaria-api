<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tabelas;

use DuNf\ReformaTributaria\Excecao\DadoInvalidoException;

/**
 * Le os arquivos JSON de resources/dados.
 *
 * @internal
 */
final class ArquivoDados
{
    private const DIRETORIO = __DIR__ . '/../../resources/dados/';

    private function __construct()
    {
    }

    /**
     * @return array{meta: array<string, string>, dados: array<int, array<string, mixed>>}
     */
    public static function ler(string $nome): array
    {
        $caminho = self::DIRETORIO . $nome . '.json';

        if (!is_file($caminho)) {
            throw new DadoInvalidoException('Arquivo de dados nao encontrado: ' . $caminho);
        }

        $json = json_decode((string) file_get_contents($caminho), true);

        if (!is_array($json) || !isset($json['meta'], $json['dados'])) {
            throw new DadoInvalidoException('Arquivo de dados invalido: ' . $caminho);
        }

        return $json;
    }
}
