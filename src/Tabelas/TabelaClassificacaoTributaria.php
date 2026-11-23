<?php

declare(strict_types=1);

namespace EduardoEvaristo\ReformaTributaria\Tabelas;

use EduardoEvaristo\ReformaTributaria\Excecao\RegraNaoEncontradaException;

/**
 * Tabela de classificacoes tributarias (cClassTrib) do IBS e da CBS
 * (resources/dados/cclasstrib-ibs-cbs.json).
 */
final class TabelaClassificacaoTributaria
{
    /** @var array<int, array<string, mixed>> */
    private array $registros;

    /** @var array<string, string> */
    private array $meta;

    public function __construct()
    {
        $arquivo = ArquivoDados::ler('cclasstrib-ibs-cbs');
        $this->registros = $arquivo['dados'];
        $this->meta = $arquivo['meta'];
    }

    public function buscar(string $codigo, \DateTimeInterface $data): ClassificacaoTributaria
    {
        foreach ($this->registros as $registro) {
            if ($registro['codigo'] === $codigo && ArquivoDados::vigente($registro, $data)) {
                return new ClassificacaoTributaria($registro);
            }
        }

        throw new RegraNaoEncontradaException(
            sprintf('cClassTrib %s nao encontrado ou fora de vigencia em %s.', $codigo, $data->format('Y-m-d'))
        );
    }

    /**
     * Lista as classificacoes vigentes na data. Informe o CST para filtrar.
     *
     * @return ClassificacaoTributaria[]
     */
    public function listar(\DateTimeInterface $data, ?string $cst = null): array
    {
        $lista = [];
        foreach ($this->registros as $registro) {
            if (!ArquivoDados::vigente($registro, $data)) {
                continue;
            }

            if ($cst !== null && $registro['cst'] !== $cst) {
                continue;
            }

            $lista[] = new ClassificacaoTributaria($registro);
        }

        return $lista;
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
