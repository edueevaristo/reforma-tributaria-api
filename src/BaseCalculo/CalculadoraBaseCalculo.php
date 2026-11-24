<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\BaseCalculo;

use DuNf\ReformaTributaria\Excecao\DadoInvalidoException;
use DuNf\ReformaTributaria\Suporte\Decimal;

/**
 * Base de calculo unificada da NF-e e da NFC-e (regime regular, Imposto Seletivo e Simples Nacional).
 *
 * Reproduz a regra do endpoint /base-calculo/base-calculo-unificada da Calculadora de Tributos oficial:
 * cada componente so entra na base dentro da sua vigencia, natureza e modelo de documento.
 */
final class CalculadoraBaseCalculo
{
    /** @var array<string, RegraComponente> */
    private array $regras = [];

    /**
     * @param array<string, array<string, mixed>>|null $regras Use null para carregar as regras oficiais do pacote.
     */
    public function __construct(?array $regras = null)
    {
        $regras ??= require __DIR__ . '/../../resources/regras/base-calculo.php';

        foreach ($regras as $tag => $regra) {
            $this->regras[$tag] = new RegraComponente($tag, $regra);
        }
    }

    public function calcular(
        ComponentesBaseCalculo $componentes,
        int $ano,
        Natureza $natureza,
        ModeloDocumento $modelo
    ): ResultadoBaseCalculo {
        $this->garantirComponentesConhecidos($componentes);

        $informados = $this->valoresInformados($componentes);
        $motivosExclusao = $this->motivosExclusao($informados, $ano, $natureza, $modelo);
        $bases = Bases::zeradas();

        foreach (array_diff_key($informados, $motivosExclusao) as $tag => $valor) {
            $bases = $this->regras[$tag]->aplicar($bases, $valor);
        }

        return $bases->publicar(array_values($motivosExclusao));
    }

    /**
     * Valores diferentes de zero, na ordem das regras.
     *
     * @return array<string, Decimal>
     */
    private function valoresInformados(ComponentesBaseCalculo $componentes): array
    {
        $valores = array_map(
            static fn (RegraComponente $regra): Decimal => Decimal::de($componentes->valor($regra->tag())),
            $this->regras
        );

        return array_filter($valores, static fn (Decimal $valor): bool => !$valor->ehZero());
    }

    /**
     * Motivo de exclusao de cada componente que nao entra nas bases, indexado pela tag.
     *
     * @param array<string, Decimal> $informados
     * @return array<string, string>
     */
    private function motivosExclusao(array $informados, int $ano, Natureza $natureza, ModeloDocumento $modelo): array
    {
        $motivos = [];
        foreach (array_keys($informados) as $tag) {
            $motivos[$tag] = $this->regras[$tag]->motivoExclusao($ano, $natureza, $modelo);
        }

        return array_filter($motivos, static fn (?string $motivo): bool => $motivo !== null);
    }

    private function garantirComponentesConhecidos(ComponentesBaseCalculo $componentes): void
    {
        $desconhecidos = array_diff($componentes->tags(), array_keys($this->regras));

        if ($desconhecidos !== []) {
            throw new DadoInvalidoException(
                sprintf('Componente de base de calculo desconhecido: %s.', reset($desconhecidos))
            );
        }
    }
}
