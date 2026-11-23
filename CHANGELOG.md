# Changelog

Todas as mudanças relevantes deste projeto. O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e as versões seguem o [SemVer](https://semver.org/lang/pt-BR/).

## [Não publicado]

## [0.2.0] - 2026-11-23

### Adicionado

- XMLs de exemplo de NF-e e NFC-e com IBS e CBS (venda, venda com redução, NFC-e, devolução, bonificação, transferência, cancelamento e carta de correção), validados no schema oficial e servidos pelo WireMock em `/exemplos/nfe`.
- Mock do serviço `NFeRecepcaoEvento4` da SEFAZ.
- Script `bin/gerar-xml-exemplos.php` e exemplo `exemplos/enviar-wiremock.php`.

## [0.1.0] - 2026-11-23

### Adicionado

- Mocks WireMock da Calculadora de Tributos oficial e dos web services de NF-e 4.00 da SEFAZ (autorização, status, rejeições da Reforma Tributária, SEFAZ lenta e fora do ar), com testes de integração no CI.
- Publicação no Packagist.
- Cronograma de transição 2026-2033 com fundamento legal (`Transicao\Cronograma`).
- Base de cálculo unificada da NF-e e da NFC-e (`BaseCalculo\CalculadoraBaseCalculo`).
- Tabelas de CST e cClassTrib do IBS/CBS e do IS, base V0073 da calculadora oficial (09/11/2026).
- Cálculo local do IBS e da CBS para os CST 000, 200, 510, 515, 400, 410 e 820 (`Calculo\CalculadoraIbsCbs`).
- Geração dos grupos XML `IBSCBS` e `IBSCBSTot` da NF-e/NFC-e (`Xml\GeradorXmlNfe`).
- Cliente da Calculadora de Tributos oficial (`CalculadoraOficial\Cliente`).
- Script `bin/atualizar-tabelas.php` para regenerar as tabelas.
