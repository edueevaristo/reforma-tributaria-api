<?php

declare(strict_types=1);

namespace EduardoEvaristo\ReformaTributaria\Tabelas;

use EduardoEvaristo\ReformaTributaria\Excecao\RegraNaoEncontradaException;

/**
 * Tabela de CST do IBS e da CBS (resources/dados/cst-ibs-cbs.json).
 */
final class TabelaSituacaoTributaria
{
    /** @var array<int, array<string, mixed>> */
    private array $registros;

    /** @var array<string, string> */
    private array $meta;

    public function __construct()
    {
        $arquivo = ArquivoDados::ler('cst-ibs-cbs');
        $this->registros = $arquivo['dados'];
        $this->meta = $arquivo['meta'];
    }

    public function buscar(string $codigo, \DateTimeInterface $data): SituacaoTributaria
    {
        foreach ($this->registros as $registro) {
            if ($registro['codigo'] === $codigo && ArquivoDados::vigente($registro, $data)) {
                return new SituacaoTributaria($registro);
            }
        }

        throw new RegraNaoEncontradaException(
            sprintf('CST %s nao encontrado ou fora de vigencia em %s.', $codigo, $data->format('Y-m-d'))
        );
    }

    /**
     * @return SituacaoTributaria[]
     */
    public function listar(\DateTimeInterface $data): array
    {
        $lista = [];
        foreach ($this->registros as $registro) {
            if (ArquivoDados::vigente($registro, $data)) {
                $lista[] = new SituacaoTributaria($registro);
            }
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
