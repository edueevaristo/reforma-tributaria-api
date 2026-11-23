# Changelog

Todas as mudanças relevantes deste projeto. O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e as versões seguem o [SemVer](https://semver.org/lang/pt-BR/).

## [Não publicado]

### Adicionado

- Cronograma de transição 2026-2033 com fundamento legal (`Transicao\Cronograma`).
- Base de cálculo unificada da NF-e e da NFC-e (`BaseCalculo\CalculadoraBaseCalculo`).
- Tabelas de CST e cClassTrib do IBS/CBS e do IS, base V0073 da calculadora oficial (09/11/2026).
- Cálculo local do IBS e da CBS para os CST 000, 200, 510, 515, 400, 410 e 820 (`Calculo\CalculadoraIbsCbs`).
- Geração dos grupos XML `IBSCBS` e `IBSCBSTot` da NF-e/NFC-e (`Xml\GeradorXmlNfe`).
- Cliente da Calculadora de Tributos oficial (`CalculadoraOficial\Cliente`).
- Script `bin/atualizar-tabelas.php` para regenerar as tabelas.
