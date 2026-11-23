<?php

/**
 * Componentes da base de calculo unificada da NF-e (55) e NFC-e (65).
 *
 * Fonte: enum ElementoBaseCalculo da Calculadora de Tributos oficial
 * (github.com/ReceitaFederal/calculadora-rtc-regime-regular, licenca MIT).
 *
 * Campos de cada componente:
 * - operacao: "soma" ou "subtrai".
 * - bases: bases em que o componente entra. RG = regime regular (IBS/CBS),
 *   IS = Imposto Seletivo, SN = Simples Nacional.
 * - natureza: "bem", "servico" ou null (vale para os dois).
 * - vigencia: [ano inicial, ano final], inclusive.
 * - modelos: modelos de documento em que o componente existe.
 *
 * Ao alterar, mantenha o mesmo comportamento da calculadora oficial e cite a fonte no PR.
 */

return [
    'vProd' => ['operacao' => 'soma', 'bases' => ['RG', 'IS', 'SN'], 'natureza' => null, 'vigencia' => [2026, 2033], 'modelos' => [55, 65]],
    'vFrete' => ['operacao' => 'soma', 'bases' => ['RG', 'IS', 'SN'], 'natureza' => null, 'vigencia' => [2026, 2033], 'modelos' => [55, 65]],
    'vSeg' => ['operacao' => 'soma', 'bases' => ['RG', 'IS', 'SN'], 'natureza' => null, 'vigencia' => [2026, 2033], 'modelos' => [55, 65]],
    'vOutro' => ['operacao' => 'soma', 'bases' => ['RG', 'IS', 'SN'], 'natureza' => null, 'vigencia' => [2026, 2033], 'modelos' => [55, 65]],
    'vII' => ['operacao' => 'soma', 'bases' => ['RG', 'IS'], 'natureza' => 'bem', 'vigencia' => [2026, 2033], 'modelos' => [55]],
    'vIS' => ['operacao' => 'soma', 'bases' => ['RG', 'SN'], 'natureza' => null, 'vigencia' => [2027, 2033], 'modelos' => [55, 65]],
    'vDesc' => ['operacao' => 'subtrai', 'bases' => ['RG', 'IS', 'SN'], 'natureza' => null, 'vigencia' => [2026, 2033], 'modelos' => [55, 65]],
    'vPIS' => ['operacao' => 'subtrai', 'bases' => ['RG', 'IS'], 'natureza' => null, 'vigencia' => [2026, 2026], 'modelos' => [55, 65]],
    'vCOFINS' => ['operacao' => 'subtrai', 'bases' => ['RG', 'IS'], 'natureza' => null, 'vigencia' => [2026, 2026], 'modelos' => [55, 65]],
    'vICMS' => ['operacao' => 'subtrai', 'bases' => ['RG', 'IS'], 'natureza' => 'bem', 'vigencia' => [2026, 2032], 'modelos' => [55, 65]],
    'vICMSUFDest' => ['operacao' => 'subtrai', 'bases' => ['RG', 'IS'], 'natureza' => 'bem', 'vigencia' => [2026, 2032], 'modelos' => [55]],
    'vFCP' => ['operacao' => 'subtrai', 'bases' => ['RG', 'IS'], 'natureza' => 'bem', 'vigencia' => [2026, 2032], 'modelos' => [55, 65]],
    'vFCPUFDest' => ['operacao' => 'subtrai', 'bases' => ['RG', 'IS'], 'natureza' => 'bem', 'vigencia' => [2026, 2032], 'modelos' => [55]],
    'vICMSMono' => ['operacao' => 'subtrai', 'bases' => ['RG', 'IS'], 'natureza' => 'bem', 'vigencia' => [2026, 2032], 'modelos' => [55, 65]],
    'vISSQN' => ['operacao' => 'subtrai', 'bases' => ['RG', 'IS'], 'natureza' => 'servico', 'vigencia' => [2026, 2032], 'modelos' => [55, 65]],
];
