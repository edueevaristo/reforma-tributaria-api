# Changelog

Todas as mudanças relevantes deste projeto. O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e as versões seguem o [SemVer](https://semver.org/lang/pt-BR/).

## [Não publicado]

## [0.3.0] - 2026-11-24

### Adicionado

- Coleção do Postman **WireMock - ReformaTributária** (`postman/`) com todos os mocks WireMock, organizada por pasta, com descrição e testes de cada resposta, gerada por `bin/gerar-postman.php`. O CI confere se ela está atualizada e roda ela inteira com o Newman.
- `TransportePsr18`: usa qualquer cliente HTTP PSR-18 (Guzzle, Symfony HttpClient) no cliente da Calculadora de Tributos oficial.
- Tabelas de CST e cClassTrib do Imposto Seletivo: `TabelaSituacaoTributaria::impostoSeletivo()` e `TabelaClassificacaoTributaria::impostoSeletivo()`.
- Value object `Tributo` (`Tributo::ibsUf()`, `Tributo::ibsMun()`, `Tributo::cbs()`, `Tributo::todos()`) e métodos por tributo: `ResultadoItem::tributo()`, `TotaisIbsCbs::valor()` e `diferimento()`, `AnoTransicao::aliquota()` e `ClassificacaoTributaria::percentualReducao()`.
- Value objects `Natureza` e `ModeloDocumento` para a base de cálculo.
- Tabelas aceitam os dados no construtor, para testes com dados próprios.

### Alterado

- `CalculadoraBaseCalculo::calcular()` recebe `Natureza` e `ModeloDocumento` em vez de `string` e `int`. As constantes `NATUREZA_BEM`, `NATUREZA_SERVICO`, `MODELO_NFE` e `MODELO_NFCE` saíram.
- A aritmética decimal passou para o value object imutável `Suporte\Decimal`, que substitui a classe estática `Suporte\Numero`.
- `ResultadoItem` e `ResultadoTributo` são criados só pelos construtores nomeados `ResultadoItem::semGrupoIbsCbs()`, `ResultadoItem::comGrupoIbsCbs()` e `ResultadoTributo::calcular()`.
- Código reorganizado segundo PSR-12, Clean Code e Object Calisthenics. Os valores calculados não mudaram: a saída foi conferida em mais de 3.300 cálculos e nos XMLs de exemplo.

### Removido

- `Suporte\Numero` (use `Suporte\Decimal`).
- Getters `Aliquotas::cbs()`, `ibsUf()`, `ibsMun()`, `Diferimento::cbs()`, `ibsUf()`, `ibsMun()`, `ItemIbsCbs::aliquotas()` e `ItemIbsCbs::diferimento()`. Use `Aliquotas::de(Tributo)`, `Diferimento::de(Tributo)`, `ItemIbsCbs::aliquotaInformada(Tributo)` e `ItemIbsCbs::percentualDiferimento(Tributo)`.
- `ArquivoDados::vigente()` (use `Vigencia::abrange()`).

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
