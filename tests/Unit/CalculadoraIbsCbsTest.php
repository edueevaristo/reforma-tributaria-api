<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Tests\Unit;

use DuNf\ReformaTributaria\Calculo\Aliquotas;
use DuNf\ReformaTributaria\Calculo\CalculadoraIbsCbs;
use DuNf\ReformaTributaria\Calculo\Diferimento;
use DuNf\ReformaTributaria\Calculo\ItemIbsCbs;
use DuNf\ReformaTributaria\Excecao\DadoInvalidoException;
use DuNf\ReformaTributaria\Excecao\OperacaoNaoSuportadaException;
use DuNf\ReformaTributaria\Tabelas\TabelaClassificacaoTributaria;
use DuNf\ReformaTributaria\Tributo;
use PHPUnit\Framework\TestCase;

final class CalculadoraIbsCbsTest extends TestCase
{
    private CalculadoraIbsCbs $calculadora;

    protected function setUp(): void
    {
        $this->calculadora = new CalculadoraIbsCbs();
    }

    public function testTributacaoIntegralEm2026UsaAliquotasDeTeste(): void
    {
        $item = new ItemIbsCbs(1, '000', '000001', '1000.00');

        $resultado = $this->calculadora->calcularItem($item, new \DateTimeImmutable('2026-08-03'));

        self::assertSame('1000.00', $resultado->baseCalculo());
        self::assertSame('1.00', $resultado->ibsUf()->valor());
        self::assertSame('0.00', $resultado->ibsMun()->valor());
        self::assertSame('1.00', $resultado->valorIbs());
        self::assertSame('9.00', $resultado->cbs()->valor());
        self::assertNull($resultado->cbs()->percentualReducao());
    }

    /**
     * Item 3 do exemplo de docs/exemplos.md da Calculadora de Tributos oficial.
     */
    public function testReducaoDe60PorCentoIgualAoExemploOficial(): void
    {
        $item = new ItemIbsCbs(3, '200', '200028', '1200.00', new Aliquotas('8.8', '0.05', '0.05'));

        $resultado = $this->calculadora->calcularItem($item, new \DateTimeImmutable('2027-03-10'));

        self::assertSame('1200.00', $resultado->baseCalculo());
        self::assertSame('60.0000', $resultado->ibsUf()->percentualReducao());
        self::assertSame('0.0200', $resultado->ibsUf()->aliquotaEfetiva());
        self::assertSame('0.24', $resultado->ibsUf()->valor());
        self::assertSame('0.24', $resultado->ibsMun()->valor());
        self::assertSame('0.48', $resultado->valorIbs());
        self::assertSame('3.5200', $resultado->cbs()->aliquotaEfetiva());
        self::assertSame('42.24', $resultado->cbs()->valor());
    }

    public function testReducaoDe100PorCentoZeraOsValores(): void
    {
        $item = new ItemIbsCbs(1, '200', '200003', '100.00', new Aliquotas('8.8'));

        $resultado = $this->calculadora->calcularItem($item, new \DateTimeImmutable('2027-03-10'));

        self::assertSame('0.0000', $resultado->cbs()->aliquotaEfetiva());
        self::assertSame('0.00', $resultado->cbs()->valor());
        self::assertSame('0.00', $resultado->valorIbs());
    }

    public function testEm2027IbsUsaAliquotaDaLeiECbsPrecisaSerInformada(): void
    {
        $item = new ItemIbsCbs(1, '000', '000001', '1000.00', new Aliquotas('8.7'));

        $resultado = $this->calculadora->calcularItem($item, new \DateTimeImmutable('2027-01-15'));

        self::assertSame('0.50', $resultado->ibsUf()->valor());
        self::assertSame('0.50', $resultado->ibsMun()->valor());
        self::assertSame('87.00', $resultado->cbs()->valor());
    }

    public function testSemAliquotaDaCbsEm2027LancaExcecao(): void
    {
        $this->expectException(DadoInvalidoException::class);
        $this->expectExceptionMessage('Informe a aliquota de CBS para 2027');

        $this->calculadora->calcularItem(
            new ItemIbsCbs(1, '000', '000001', '1000.00'),
            new \DateTimeImmutable('2027-01-15')
        );
    }

    public function testDiferimentoDescontaDoValorDevido(): void
    {
        $item = new ItemIbsCbs(1, '510', '510001', '1000.00', null, new Diferimento('100', '100', '100'));

        $resultado = $this->calculadora->calcularItem($item, new \DateTimeImmutable('2026-08-03'));

        self::assertSame('9.00', $resultado->cbs()->valorDiferimento());
        self::assertSame('0.00', $resultado->cbs()->valor());
        self::assertSame('1.00', $resultado->ibsUf()->valorDiferimento());
        self::assertSame('0.00', $resultado->ibsUf()->valor());
    }

    public function testDiferimentoParcialComReducao(): void
    {
        $item = new ItemIbsCbs(1, '515', '515001', '1000.00', new Aliquotas('8.8'), new Diferimento('50', '50', '50'));

        $resultado = $this->calculadora->calcularItem($item, new \DateTimeImmutable('2027-01-15'));

        self::assertSame('3.5200', $resultado->cbs()->aliquotaEfetiva());
        self::assertSame('17.60', $resultado->cbs()->valorDiferimento());
        self::assertSame('17.60', $resultado->cbs()->valor());
    }

    public function testCstComDiferimentoSemPercentualLancaExcecao(): void
    {
        $this->expectException(DadoInvalidoException::class);
        $this->calculadora->calcularItem(
            new ItemIbsCbs(1, '510', '510001', '1000.00'),
            new \DateTimeImmutable('2026-08-03')
        );
    }

    public function testImunidadeNaoGeraGrupoIbsCbs(): void
    {
        $resultado = $this->calculadora->calcularItem(
            new ItemIbsCbs(1, '410', '410001', '1000.00'),
            new \DateTimeImmutable('2026-08-03')
        );

        self::assertFalse($resultado->possuiGrupoIbsCbs());
        self::assertNull($resultado->cbs());
    }

    public function testClassificacaoDeOutroCstLancaExcecao(): void
    {
        $this->expectException(DadoInvalidoException::class);
        $this->expectExceptionMessage('pertence ao CST 200');

        $this->calculadora->calcularItem(
            new ItemIbsCbs(1, '000', '200028', '1000.00'),
            new \DateTimeImmutable('2026-08-03')
        );
    }

    public function testMonofasiaAindaNaoESuportada(): void
    {
        $this->expectException(OperacaoNaoSuportadaException::class);
        $this->expectExceptionMessage('Item 1 (CST 620, cClassTrib 620001): o CST exige o grupo gIBSCBSMono.');
        $this->calculadora->calcularItem(
            new ItemIbsCbs(1, '620', '620001', '1000.00'),
            new \DateTimeImmutable('2027-01-15')
        );
    }

    public function testTotaisSomamOsValoresPublicadosDosItens(): void
    {
        $data = new \DateTimeImmutable('2027-03-10');
        $documento = $this->calculadora->calcularDocumento([
            new ItemIbsCbs(1, '200', '200003', '100.00', new Aliquotas('8.8')),
            new ItemIbsCbs(2, '200', '200028', '1200.00', new Aliquotas('8.8')),
            new ItemIbsCbs(3, '000', '000001', '0.10', new Aliquotas('8.8')),
            new ItemIbsCbs(4, '410', '410001', '50.00'),
        ], $data);

        $totais = $documento->totais();

        self::assertCount(4, $documento->itens());
        self::assertSame('1300.10', $totais->baseCalculo());
        self::assertSame('0.24', $totais->ibsUf());
        self::assertSame('0.24', $totais->ibsMun());
        self::assertSame('0.48', $totais->ibs());
        self::assertSame('42.25', $totais->cbs());
    }

    public function testTipoDeAliquotaSemTabelaPropriaNaoESuportado(): void
    {
        $this->expectException(OperacaoNaoSuportadaException::class);
        $this->expectExceptionMessage('o tipo de aliquota "Uniforme setorial" exige tabela propria');

        $tabela = new TabelaClassificacaoTributaria([
            'meta' => [],
            'dados' => [[
                'codigo' => '000999',
                'cst' => '000',
                'descricao' => 'Classificacao de teste',
                'tipoAliquota' => 'Uniforme setorial',
                'inicioVigencia' => '2026-01-01',
                'fimVigencia' => null,
            ]],
        ]);

        (new CalculadoraIbsCbs(null, null, $tabela))->calcularItem(
            new ItemIbsCbs(1, '000', '000999', '1000.00', new Aliquotas('8.8')),
            new \DateTimeImmutable('2027-01-15')
        );
    }

    public function testResultadoPorTributo(): void
    {
        $resultado = $this->calculadora->calcularItem(
            new ItemIbsCbs(1, '000', '000001', '1000.00'),
            new \DateTimeImmutable('2026-08-03')
        );

        self::assertSame($resultado->cbs(), $resultado->tributo(Tributo::cbs()));
        self::assertFalse($resultado->cbs()->possuiReducao());
        self::assertFalse($resultado->cbs()->possuiDiferimento());
    }

    public function testTotaisPorTributo(): void
    {
        $documento = $this->calculadora->calcularDocumento([
            new ItemIbsCbs(1, '510', '510001', '1000.00', null, new Diferimento('100', '50', '0')),
        ], new \DateTimeImmutable('2026-08-03'));

        self::assertSame('9.00', $documento->totais()->diferimento(Tributo::cbs()));
        self::assertSame('0.50', $documento->totais()->valor(Tributo::ibsUf()));
        self::assertSame('0.50', $documento->totais()->diferimentoIbsUf());
    }

    public function testDocumentoSemGrupoIbsCbsTemTotaisZerados(): void
    {
        $totais = $this->calculadora->calcularDocumento([
            new ItemIbsCbs(1, '410', '410001', '1000.00'),
        ], new \DateTimeImmutable('2026-08-03'))->totais();

        self::assertFalse($totais->possuiValores());
        self::assertSame('0.00', $totais->baseCalculo());
        self::assertSame('0.00', $totais->cbs());
    }
}
