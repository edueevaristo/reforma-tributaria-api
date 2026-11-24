<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Unit;

use DuNf\ReformaTributaria\Excecao\RegraNaoEncontradaException;
use DuNf\ReformaTributaria\Tabelas\TabelaClassificacaoTributaria;
use DuNf\ReformaTributaria\Tabelas\TabelaSituacaoTributaria;
use DuNf\ReformaTributaria\Tributo;
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

    public function testCstComGrupoSubstituto(): void
    {
        $cst = (new TabelaSituacaoTributaria())->buscar('620', new \DateTimeImmutable('2027-01-01'));

        self::assertFalse($cst->possuiGrupoIbsCbs());
        self::assertSame('gIBSCBSMono', $cst->grupoSubstituto());
    }

    public function testReducaoPorTributo(): void
    {
        $classificacao = (new TabelaClassificacaoTributaria())->buscar('200028', new \DateTimeImmutable('2027-01-01'));

        self::assertSame('60', (string) $classificacao->percentualReducao(Tributo::ibsMun()));
        self::assertTrue($classificacao->usaAliquotaPadrao());
        self::assertTrue($classificacao->pertenceAoCst('200'));
    }

    public function testTabelasDoImpostoSeletivo(): void
    {
        $data = new \DateTimeImmutable('2027-01-01');

        $cst = TabelaSituacaoTributaria::impostoSeletivo()->buscar('000', $data);
        $classificacao = TabelaClassificacaoTributaria::impostoSeletivo()->buscar('000001', $data);

        self::assertSame('Tributado com Imposto Seletivo', $cst->descricao());
        self::assertFalse($cst->possuiGrupoIbsCbs());
        self::assertSame('000', $classificacao->cst());
        self::assertSame('0', $classificacao->percentualReducaoCbs());
        self::assertFalse($classificacao->permiteCreditoIbsAdquirente());
    }

    public function testImpostoSeletivoSoComecaEm2027(): void
    {
        $this->expectException(RegraNaoEncontradaException::class);
        TabelaSituacaoTributaria::impostoSeletivo()->buscar('000', new \DateTimeImmutable('2026-12-31'));
    }

    public function testAceitaDadosInjetadosComVigencia(): void
    {
        $tabela = new TabelaSituacaoTributaria([
            'meta' => ['versaoBaseDados' => 'teste'],
            'dados' => [
                [
                    'codigo' => '000',
                    'descricao' => 'Antiga',
                    'inicioVigencia' => '2026-01-01',
                    'fimVigencia' => '2026-12-31',
                ],
                ['codigo' => '000', 'descricao' => 'Nova', 'inicioVigencia' => '2027-01-01', 'fimVigencia' => null],
            ],
        ]);

        self::assertSame('Antiga', $tabela->buscar('000', new \DateTimeImmutable('2026-12-31'))->descricao());
        self::assertSame('Nova', $tabela->buscar('000', new \DateTimeImmutable('2027-01-01'))->descricao());
        self::assertCount(1, $tabela->listar(new \DateTimeImmutable('2030-01-01')));
        self::assertSame(['versaoBaseDados' => 'teste'], $tabela->versao());
    }
}
