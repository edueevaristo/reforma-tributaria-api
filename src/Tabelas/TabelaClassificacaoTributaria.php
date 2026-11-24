<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tabelas;

use DuNf\ReformaTributaria\Excecao\RegraNaoEncontradaException;

/**
 * Tabela de classificacoes tributarias (cClassTrib) do IBS e da CBS (resources/dados/cclasstrib-ibs-cbs.json)
 * ou do Imposto Seletivo (cclasstrib-is.json).
 */
final class TabelaClassificacaoTributaria
{
    private const ARQUIVO_IBS_CBS = 'cclasstrib-ibs-cbs';
    private const ARQUIVO_IMPOSTO_SELETIVO = 'cclasstrib-is';

    /** @var RegistrosVigentes<ClassificacaoTributaria> */
    private RegistrosVigentes $registros;

    /** @var array<string, string> */
    private array $meta;

    /**
     * @param array{meta: array<string, string>, dados: array<int, array<string, mixed>>}|null $arquivo
     *        Use null para carregar a tabela oficial de cClassTrib do IBS e da CBS.
     */
    public function __construct(?array $arquivo = null)
    {
        $arquivo ??= ArquivoDados::ler(self::ARQUIVO_IBS_CBS);
        $this->registros = new RegistrosVigentes(
            array_map([ClassificacaoTributaria::class, 'deRegistro'], $arquivo['dados'])
        );
        $this->meta = $arquivo['meta'];
    }

    /**
     * Tabela de cClassTrib do Imposto Seletivo.
     */
    public static function impostoSeletivo(): self
    {
        return new self(ArquivoDados::ler(self::ARQUIVO_IMPOSTO_SELETIVO));
    }

    public function buscar(string $codigo, \DateTimeInterface $data): ClassificacaoTributaria
    {
        $classificacao = $this->registros->buscar($codigo, $data);

        if ($classificacao === null) {
            throw new RegraNaoEncontradaException(
                sprintf('cClassTrib %s nao encontrado ou fora de vigencia em %s.', $codigo, $data->format('Y-m-d'))
            );
        }

        return $classificacao;
    }

    /**
     * Lista as classificacoes vigentes na data. Informe o CST para filtrar.
     *
     * @return ClassificacaoTributaria[]
     */
    public function listar(\DateTimeInterface $data, ?string $cst = null): array
    {
        $vigentes = $this->registros->vigentes($data);

        if ($cst === null) {
            return $vigentes;
        }

        return array_values(array_filter(
            $vigentes,
            static fn (ClassificacaoTributaria $classificacao): bool => $classificacao->pertenceAoCst($cst)
        ));
    }

    /**
     * Origem e versao da base de dados usada para gerar a tabela.
     *
     * @return array<string, string>
     */
    public function versao(): array
    {
        return $this->meta;
    }
}
