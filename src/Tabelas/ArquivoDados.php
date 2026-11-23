<?php

declare(strict_types=1);

namespace EduardoEvaristo\ReformaTributaria\Tabelas;

use EduardoEvaristo\ReformaTributaria\Excecao\DadoInvalidoException;

/**
 * Le os arquivos JSON de resources/dados.
 */
final class ArquivoDados
{
    private function __construct()
    {
    }

    /**
     * @return array{meta: array<string, string>, dados: array<int, array<string, mixed>>}
     */
    public static function ler(string $nome): array
    {
        $caminho = __DIR__ . '/../../resources/dados/' . $nome . '.json';
        $conteudo = file_get_contents($caminho);

        if ($conteudo === false) {
            throw new DadoInvalidoException('Arquivo de dados nao encontrado: ' . $caminho);
        }

        $json = json_decode($conteudo, true);

        if (!is_array($json) || !isset($json['meta'], $json['dados'])) {
            throw new DadoInvalidoException('Arquivo de dados invalido: ' . $caminho);
        }

        return $json;
    }

    /**
     * Verifica se um registro esta vigente na data (inicio e fim inclusivos, fim null = sem fim).
     *
     * @param array<string, mixed> $registro
     */
    public static function vigente(array $registro, \DateTimeInterface $data): bool
    {
        $dia = $data->format('Y-m-d');

        return $registro['inicioVigencia'] <= $dia
            && ($registro['fimVigencia'] === null || $registro['fimVigencia'] >= $dia);
    }
}
