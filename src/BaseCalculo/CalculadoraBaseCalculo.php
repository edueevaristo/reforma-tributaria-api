<?php

declare(strict_types=1);

namespace EduardoEvaristo\ReformaTributaria\BaseCalculo;

use EduardoEvaristo\ReformaTributaria\Excecao\DadoInvalidoException;
use EduardoEvaristo\ReformaTributaria\Suporte\Numero;

/**
 * Base de calculo unificada da NF-e e da NFC-e (regime regular, Imposto Seletivo e Simples Nacional).
 *
 * Reproduz a regra do endpoint /base-calculo/base-calculo-unificada da Calculadora de Tributos oficial:
 * cada componente so entra na base dentro da sua vigencia, natureza e modelo de documento.
 */
final class CalculadoraBaseCalculo
{
    public const NATUREZA_BEM = 'bem';
    public const NATUREZA_SERVICO = 'servico';

    public const MODELO_NFE = 55;
    public const MODELO_NFCE = 65;

    /** @var array<string, array<string, mixed>> */
    private array $regras;

    /**
     * @param array<string, array<string, mixed>>|null $regras Use null para carregar as regras oficiais do pacote.
     */
    public function __construct(?array $regras = null)
    {
        $this->regras = $regras ?? require __DIR__ . '/../../resources/regras/base-calculo.php';
    }

    public function calcular(
        ComponentesBaseCalculo $componentes,
        int $ano,
        string $natureza,
        int $modelo
    ): ResultadoBaseCalculo {
        if (!in_array($natureza, [self::NATUREZA_BEM, self::NATUREZA_SERVICO], true)) {
            throw new DadoInvalidoException('Natureza deve ser "bem" ou "servico".');
        }

        if (!in_array($modelo, [self::MODELO_NFE, self::MODELO_NFCE], true)) {
            throw new DadoInvalidoException('Modelo de documento deve ser 55 (NF-e) ou 65 (NFC-e).');
        }

        foreach ($componentes->tags() as $tag) {
            if (!isset($this->regras[$tag])) {
                throw new DadoInvalidoException(sprintf('Componente de base de calculo desconhecido: %s.', $tag));
            }
        }

        $bases = ['RG' => '0', 'IS' => '0', 'SN' => '0'];
        $avisos = [];

        foreach ($this->regras as $tag => $regra) {
            $valor = $componentes->valor($tag);
            if (Numero::comparar($valor, '0') === 0) {
                continue;
            }

            $motivo = $this->motivoNaoComposicao($tag, $regra, $ano, $natureza, $modelo);
            if ($motivo !== null) {
                $avisos[] = $motivo;
                continue;
            }

            foreach ($regra['bases'] as $base) {
                $bases[$base] = $regra['operacao'] === 'soma'
                    ? Numero::somar($bases[$base], $valor)
                    : Numero::subtrair($bases[$base], $valor);
            }
        }

        return new ResultadoBaseCalculo(
            Numero::valorFiscal($bases['RG']),
            Numero::valorFiscal($bases['IS']),
            Numero::valorFiscal($bases['SN']),
            $avisos
        );
    }

    /**
     * @param array<string, mixed> $regra
     */
    private function motivoNaoComposicao(string $tag, array $regra, int $ano, string $natureza, int $modelo): ?string
    {
        list($inicio, $fim) = $regra['vigencia'];
        if ($ano < $inicio || $ano > $fim) {
            return sprintf(
                'Campo %s nao compoe as bases para o ano %d (vigencia %d-%d) e foi ignorado no calculo',
                $tag,
                $ano,
                $inicio,
                $fim
            );
        }

        if ($regra['natureza'] !== null && $regra['natureza'] !== $natureza) {
            return sprintf(
                'Campo %s nao compoe as bases para a natureza %s (aplicavel apenas a %s) e foi ignorado no calculo',
                $tag,
                $natureza,
                $regra['natureza']
            );
        }

        if (!in_array($modelo, $regra['modelos'], true)) {
            return sprintf(
                'Campo %s nao compoe as bases para o documento modelo %d e foi ignorado no calculo',
                $tag,
                $modelo
            );
        }

        return null;
    }
}
