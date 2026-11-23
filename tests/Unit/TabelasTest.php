<?php

declare(strict_types=1);

namespace EduardoEvaristo\ReformaTributaria\Tests\Unit;

use EduardoEvaristo\ReformaTributaria\Excecao\RegraNaoEncontradaException;
use EduardoEvaristo\ReformaTributaria\Tabelas\TabelaClassificacaoTributaria;
use EduardoEvaristo\ReformaTributaria\Tabelas\TabelaSituacaoTributaria;
use PHPUnit\Framework\TestCase;

final class TabelasTest extends TestCase
{
    public function testBuscaCst(): void
    {
        $cst = (new TabelaSituacaoTributaria())->buscar('200', new \DateTimeImmutable('2026-06-01'));

        self::assertSame('Alíquota reduzida', $cst->descricao());
        self::assertTrue($cst->exigeGrupo('gIBSCBS'));
        self::assertTrue($cst->exigeGrupo('gRed'));
        self::assertFalse($cst->exigeGrupo('gDif'));
    }

    public function testCstDeIsencaoNaoTemGrupoIbsCbs(): void
    {
        $cst = (new TabelaSituacaoTributaria())->buscar('410', new \DateTimeImmutable('2026-06-01'));

        self::assertFalse($cst->exigeGrupo('gIBSCBS'));
    }

    public function testBuscaClassificacaoComReducao(): void
    {
        $classificacao = (new TabelaClassificacaoTributaria())->buscar('200028', new \DateTimeImmutable('2027-01-01'));

        self::assertSame('200', $classificacao->cst());
        self::assertSame('60', $classificacao->percentualReducaoCbs());
        self::assertSame('60', $classificacao->percentualReducaoIbsUf());
        self::assertSame('NBS', $classificacao->nomenclatura());
    }

    public function testListaClassificacoesPorCst(): void
    {
        $lista = (new TabelaClassificacaoTributaria())->listar(new \DateTimeImmutable('2026-06-01'), '000');

        self::assertNotEmpty($lista);
        foreach ($lista as $classificacao) {
            self::assertSame('000', $classificacao->cst());
        }
    }

    public function testInformaVersaoDaBase(): void
    {
        $versao = (new TabelaClassificacaoTributaria())->versao();

        self::assertArrayHasKey('versaoBaseDados', $versao);
        self::assertArrayHasKey('dataBaseDados', $versao);
    }

    public function testCodigoInexistenteLancaExcecao(): void
    {
        $this->expectException(RegraNaoEncontradaException::class);
        (new TabelaClassificacaoTributaria())->buscar('999999', new \DateTimeImmutable('2026-06-01'));
    }

    public function testCstAntesDaVigenciaLancaExcecao(): void
    {
        $this->expectException(RegraNaoEncontradaException::class);
        (new TabelaSituacaoTributaria())->buscar('000', new \DateTimeImmutable('2025-12-31'));
    }
}
