<?php

/**
 * Cronograma de transicao da Reforma Tributaria do Consumo (EC 132/2023 e LC 214/2025).
 *
 * Regras de manutencao deste arquivo (veja CONTRIBUTING.md):
 * - Uma entrada por ano. Nunca apague um ano: notas antigas continuam sendo emitidas e corrigidas.
 * - Aliquota com valor null significa "nao fixada em lei": depende de resolucao do Senado
 *   ou de lei do ente e precisa ser informada por quem usa a biblioteca.
 * - Toda alteracao precisa de fundamento legal (artigo e norma) no campo "fundamentoLegal".
 * - Percentuais sao strings com ponto decimal, sem simbolo de %.
 */

return [
    2026 => [
        'descricao' => 'Ano de teste: CBS e IBS destacados com aliquotas de teste, compensaveis com PIS/COFINS.',
        'aliquotaCbs' => '0.9',
        'ajusteCbsPontosPercentuais' => '0',
        'aliquotaIbsUf' => '0.1',
        'aliquotaIbsMun' => '0',
        'percentualIcmsIss' => '100',
        'pisCofinsVigentes' => true,
        'impostoSeletivoVigente' => false,
        'fundamentoLegal' => ['LC 214/2025, art. 343', 'LC 214/2025, art. 346'],
    ],
    2027 => [
        'descricao' => 'CBS passa a ser cobrada pela aliquota de referencia reduzida em 0,1 ponto percentual; '
            . 'PIS/COFINS extintos; IBS de teste dividido entre UF e municipio; inicio do Imposto Seletivo.',
        'aliquotaCbs' => null,
        'ajusteCbsPontosPercentuais' => '-0.1',
        'aliquotaIbsUf' => '0.05',
        'aliquotaIbsMun' => '0.05',
        'percentualIcmsIss' => '100',
        'pisCofinsVigentes' => false,
        'impostoSeletivoVigente' => true,
        'fundamentoLegal' => ['LC 214/2025, art. 344', 'LC 214/2025, art. 347'],
    ],
    2028 => [
        'descricao' => 'Mesmas regras de 2027.',
        'aliquotaCbs' => null,
        'ajusteCbsPontosPercentuais' => '-0.1',
        'aliquotaIbsUf' => '0.05',
        'aliquotaIbsMun' => '0.05',
        'percentualIcmsIss' => '100',
        'pisCofinsVigentes' => false,
        'impostoSeletivoVigente' => true,
        'fundamentoLegal' => ['LC 214/2025, art. 344', 'LC 214/2025, art. 347'],
    ],
    2029 => [
        'descricao' => 'Inicio da troca ICMS/ISS por IBS: ICMS e ISS a 90% das aliquotas atuais.',
        'aliquotaCbs' => null,
        'ajusteCbsPontosPercentuais' => '0',
        'aliquotaIbsUf' => null,
        'aliquotaIbsMun' => null,
        'percentualIcmsIss' => '90',
        'pisCofinsVigentes' => false,
        'impostoSeletivoVigente' => true,
        'fundamentoLegal' => ['LC 214/2025, art. 501', 'LC 214/2025, art. 508'],
    ],
    2030 => [
        'descricao' => 'ICMS e ISS a 80% das aliquotas atuais.',
        'aliquotaCbs' => null,
        'ajusteCbsPontosPercentuais' => '0',
        'aliquotaIbsUf' => null,
        'aliquotaIbsMun' => null,
        'percentualIcmsIss' => '80',
        'pisCofinsVigentes' => false,
        'impostoSeletivoVigente' => true,
        'fundamentoLegal' => ['LC 214/2025, art. 501', 'LC 214/2025, art. 508'],
    ],
    2031 => [
        'descricao' => 'ICMS e ISS a 70% das aliquotas atuais.',
        'aliquotaCbs' => null,
        'ajusteCbsPontosPercentuais' => '0',
        'aliquotaIbsUf' => null,
        'aliquotaIbsMun' => null,
        'percentualIcmsIss' => '70',
        'pisCofinsVigentes' => false,
        'impostoSeletivoVigente' => true,
        'fundamentoLegal' => ['LC 214/2025, art. 501', 'LC 214/2025, art. 508'],
    ],
    2032 => [
        'descricao' => 'ICMS e ISS a 60% das aliquotas atuais. Ultimo ano dos tributos antigos.',
        'aliquotaCbs' => null,
        'ajusteCbsPontosPercentuais' => '0',
        'aliquotaIbsUf' => null,
        'aliquotaIbsMun' => null,
        'percentualIcmsIss' => '60',
        'pisCofinsVigentes' => false,
        'impostoSeletivoVigente' => true,
        'fundamentoLegal' => ['LC 214/2025, art. 501', 'LC 214/2025, art. 508'],
    ],
    2033 => [
        'descricao' => 'Sistema pleno: ICMS e ISS extintos; IBS e CBS integrais.',
        'aliquotaCbs' => null,
        'ajusteCbsPontosPercentuais' => '0',
        'aliquotaIbsUf' => null,
        'aliquotaIbsMun' => null,
        'percentualIcmsIss' => '0',
        'pisCofinsVigentes' => false,
        'impostoSeletivoVigente' => true,
        'fundamentoLegal' => ['EC 132/2023', 'LC 214/2025, art. 542', 'LC 214/2025, art. 543'],
    ],
];
