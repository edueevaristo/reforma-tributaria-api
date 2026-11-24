<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tabelas;

use DuNf\ReformaTributaria\Excecao\RegraNaoEncontradaException;

/**
 * Tabela de CST do IBS e da CBS (resources/dados/cst-ibs-cbs.json) ou do Imposto Seletivo (cst-is.json).
 */
final class TabelaSituacaoTributaria
{
    private const ARQUIVO_IBS_CBS = 'cst-ibs-cbs';
    private const ARQUIVO_IMPOSTO_SELETIVO = 'cst-is';

    /** @var RegistrosVigentes<SituacaoTributaria> */
    private RegistrosVigentes $registros;

    /** @var array<string, string> */
    private array $meta;

    /**
     * @param array{meta: array<string, string>, dados: array<int, array<string, mixed>>}|null $arquivo
     *        Use null para carregar a tabela oficial de CST do IBS e da CBS.
     */
    public function __construct(?array $arquivo = null)
    {
        $arquivo ??= ArquivoDados::ler(self::ARQUIVO_IBS_CBS);
        $this->registros = new RegistrosVigentes(
            array_map([SituacaoTributaria::class, 'deRegistro'], $arquivo['dados'])
        );
        $this->meta = $arquivo['meta'];
    }

    /**
     * Tabela de CST do Imposto Seletivo.
     */
    public static function impostoSeletivo(): self
    {
        return new self(ArquivoDados::ler(self::ARQUIVO_IMPOSTO_SELETIVO));
    }

    public function buscar(string $codigo, \DateTimeInterface $data): SituacaoTributaria
    {
        $situacao = $this->registros->buscar($codigo, $data);

        if ($situacao === null) {
            throw new RegraNaoEncontradaException(
                sprintf('CST %s nao encontrado ou fora de vigencia em %s.', $codigo, $data->format('Y-m-d'))
            );
        }

        return $situacao;
    }

    /**
     * @return SituacaoTributaria[]
     */
    public function listar(\DateTimeInterface $data): array
    {
        return $this->registros->vigentes($data);
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
