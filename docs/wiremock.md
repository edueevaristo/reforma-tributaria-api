# Testes com WireMock

A pasta [`wiremock/`](../wiremock) tem mocks prontos para testar a emissão de NF-e com IBS e CBS sem depender de serviços externos:

- **Calculadora de Tributos oficial** da Receita Federal (`/api/calculadora/...`), para testar o `CalculadoraOficial\Cliente` sem instalar Java nem subir a calculadora.
- **Web services de NF-e 4.00 da SEFAZ** (`/sefaz/...`), para testar o seu emissor com autorização, eventos, as rejeições da Reforma Tributária, SEFAZ fora do ar e SEFAZ lenta.
- **XMLs de exemplo** de NF-e e NFC-e com IBS e CBS (`/exemplos/nfe/...`): venda, venda com redução, NFC-e, devolução, bonificação, transferência, cancelamento e carta de correção.

Os mocks são simulações para desenvolvimento e testes automatizados. Eles não validam assinatura, schema nem regras de negócio da nota: o cenário é escolhido pela URL. Antes de produção, homologue no ambiente de homologação da SEFAZ e confira os cálculos na calculadora oficial.

## Subindo o WireMock

Requisito: Docker.

```bash
docker compose -f wiremock/docker-compose.yml up -d
curl http://localhost:8089/__admin/health
```

O WireMock fica em `http://localhost:8089`. A porta 8089 foi escolhida para não conflitar com a calculadora oficial, que usa a 8080.

Para parar:

```bash
docker compose -f wiremock/docker-compose.yml down
```

Para ver as requisições recebidas (útil para depurar o XML que o seu sistema enviou):

```bash
curl http://localhost:8089/__admin/requests
```

## Mocks da Calculadora de Tributos

Base: `http://localhost:8089/api/calculadora`

| Método e caminho | Resposta | Como acionar |
|---|---|---|
| `POST /regime-geral` | 200 com 2 itens calculados em 2026 (CST 000 e CST 200 com redução de 60%) | Qualquer operação |
| `POST /regime-geral` | 422 com erro `REG-011` | Envie um item com `"ncm": "00000000"` |
| `POST /observabilidade/regime-geral` | 200 com 2 itens `CALCULADO` e 1 item `INCONSISTENCIA_ENTRADA` | Qualquer operação |
| `POST /base-calculo/base-calculo-unificada` | 200 com o exemplo oficial (`baseRG` 870,00, `baseIS` 840,00, `baseSN` 1060,00) | Qualquer entrada |
| `POST /xml/generate?tipo=nfe` | XML com os grupos `IBSCBS` e `IBSCBSTot` | Qualquer entrada |
| `POST /xml/validate` | `true` | Qualquer XML |
| `POST /xml/validate` | 422 com linha e coluna do erro | XML que contenha `<CST>999</CST>` |
| `GET /dados-abertos/classificacoes-tributarias/cbs-ibs/class-trib/200028?data=AAAA-MM-DD` | 200 com a classificação de serviços de educação | Código 200028 |
| `GET /dados-abertos/classificacoes-tributarias/cbs-ibs/class-trib/{outro}` | 404 | Qualquer outro código |

Os valores da resposta de `/regime-geral` são os mesmos que a biblioteca calcula localmente, e um teste de integração confere isso.

Uso com a biblioteca:

```php
use DuNf\ReformaTributaria\CalculadoraOficial\Cliente;

$cliente = new Cliente('http://localhost:8089/api/calculadora');
$resultado = $cliente->calcularRegimeGeral($operacao);
```

## Mocks da SEFAZ (NF-e 4.00)

O cenário vai na URL: `http://localhost:8089/sefaz/{cenario}/{servico}`.

Serviços simulados:

- `NFeAutorizacao4`: autorização síncrona (`indSinc = 1`). Devolve `retEnviNFe` com `cStat` 104 e o `protNFe`.
- `NFeRecepcaoEvento4`: cancelamento (110111), carta de correção (110110) e outros eventos. Devolve `retEnvEvento` com `cStat` 128 e o evento com `cStat` 135 (evento registrado e vinculado a NF-e). Responde assim em qualquer cenário, menos `fora-do-ar`.
- `NFeStatusServico4`: devolve `cStat` 107 (serviço em operação) em qualquer cenário, menos `fora-do-ar`.

Cenários:

| Cenário | `cStat` do protocolo | Mensagem |
|---|---|---|
| `autorizado` | 100 | Autorizado o uso da NF-e |
| `lento` | 100 | Igual ao `autorizado`, com 15 segundos de atraso, para testar timeout |
| `fora-do-ar` | HTTP 503 | Página de erro em todos os serviços, para testar contingência |
| `rejeicao-1023` | 1023 | Classificação Tributária do IBS/CBS informada inexistente |
| `rejeicao-1024` | 1024 | Classificação Tributária do IBS e da CBS incompatível com o CST informado |
| `rejeicao-1041` | 1041 | Valor do IBS da UF difere do calculado |
| `rejeicao-1069` | 1069 | Valor da CBS difere do calculado |
| `rejeicao-1076` | 1076 | Total da BC do IBS e da CBS difere da soma dos itens |
| `rejeicao-1085` | 1085 | Total do IBS difere da soma do vIBS dos itens |
| `rejeicao-1091` | 1091 | Total de CBS difere da soma dos itens |
| `rejeicao-1104` | 1104 | Valor da Base de cálculo do IBS e CBS difere do somatório dos valores que a compõem |
| `rejeicao-1115` | 1115 | Grupo IBSCBS não informado |
| `rejeicao-1150` | 1150 | Valor do IBS do Item (vIBS) difere do calculado |

Códigos e mensagens das regras de validação da NT 2025.002-RTC (grupos UB e W03).

Como o protocolo é montado:

- `chNFe` e `cUF` vêm do atributo `Id="NFe..."` da nota enviada.
- `digVal` é copiado do `DigestValue` da assinatura enviada. Assim, sistemas que conferem o `digVal` do protocolo com a assinatura da nota aceitam a resposta.
- `nProt` é gerado com 15 dígitos (`1` + `cUF` + 13 dígitos aleatórios). Só existe nos cenários autorizados.
- `dhRecbto` é a data e hora atual de Brasília.
- `tpAmb` é sempre 2 (homologação).

### Usando no seu emissor

Aponte a URL do web service de homologação do seu emissor para o cenário que você quer testar. Exemplo:

```
Autorização:     http://localhost:8089/sefaz/rejeicao-1091/NFeAutorizacao4
Status serviço:  http://localhost:8089/sefaz/rejeicao-1091/NFeStatusServico4
```

O mock responde em HTTP (sem TLS) e não exige certificado digital. Se o seu emissor só aceita HTTPS ou exige certificado do servidor, use um proxy local com TLS na frente do WireMock ou chame o mock direto nos testes automatizados.

Exemplo com curl:

```bash
curl -s -X POST http://localhost:8089/sefaz/autorizado/NFeAutorizacao4 \
  -H 'Content-Type: application/soap+xml; charset=utf-8' \
  --data-binary @lote-assinado.xml
```

## XMLs de exemplo

A pasta `wiremock/__files/nfe/` tem notas completas de NF-e 4.00 com os grupos `IBSCBS` e `IBSCBSTot`, no ambiente de homologação (`tpAmb` 2), com emissão em 20/11/2026 em São Paulo. Os valores de ICMS, PIS, COFINS, IBS e CBS batem entre si, e todas as notas passam no schema oficial completo da NF-e (o teste `XmlExemplosTest` confere isso).

| Exemplo | O que é | IBS/CBS |
|---|---|---|
| `venda` | Venda de mercadoria (NF-e, finNFe 1, CFOP 5102) | CST 000, cClassTrib 000001 |
| `venda-com-reducao` | Venda com um item de higiene pessoal e um item comum | CST 200, cClassTrib 200035 (redução de 60%) e CST 000 |
| `venda-nfce` | Venda ao consumidor final em NFC-e (modelo 65, com `infNFeSupl`) | CST 200 e CST 000 |
| `devolucao` | Devolução de venda (finNFe 4, tpNF 0, CFOP 1202) referenciando a NF-e `venda` em `NFref` | CST 000, cClassTrib 000001 |
| `bonificacao` | Remessa em bonificação (CFOP 5910) | CST 410, cClassTrib 410001 (sem gIBSCBS) |
| `transferencia` | Transferência entre estabelecimentos do mesmo contribuinte (CFOP 5152) | CST 410, cClassTrib 410002 (sem gIBSCBS) |
| `evento-cancelamento` | Cancelamento (110111) da NF-e `venda` | Somente envelope SOAP |
| `evento-carta-correcao` | Carta de correção (110110) da NF-e `venda` | Somente envelope SOAP |

Cada exemplo existe em dois formatos:

- `xml/{exemplo}.xml`: a NF-e (`<NFe>`), como você guardaria ou importaria no seu sistema.
- `soap/{exemplo}.xml`: o envelope SOAP 1.2 completo (`enviNFe` ou `envEvento`), pronto para enviar à SEFAZ simulada.

Sobre a assinatura: o `DigestValue` é o hash SHA-1 real do `infNFe`, então o protocolo devolvido pelo mock traz um `digVal` coerente. O `SignatureValue` e o certificado são fictícios, e uma SEFAZ real rejeitaria essas notas. Para usar uma delas na homologação de verdade, carregue o XML no seu emissor, troque o emitente pelos seus dados e assine com o seu certificado.

### Baixando os XMLs pelo WireMock

Com o WireMock no ar, os exemplos ficam disponíveis por HTTP:

```bash
# Lista de exemplos (nome, descricao, chave e caminhos)
curl http://localhost:8089/exemplos/nfe

# A NF-e de devolucao
curl http://localhost:8089/exemplos/nfe/xml/devolucao.xml -o devolucao.xml

# O envelope SOAP da NFC-e
curl http://localhost:8089/exemplos/nfe/soap/venda-nfce.xml -o venda-nfce-soap.xml
```

### Enviando para a SEFAZ simulada

Com curl, escolhendo o exemplo e o cenário:

```bash
# Venda autorizada
curl -s -X POST http://localhost:8089/sefaz/autorizado/NFeAutorizacao4 \
  -H 'Content-Type: application/soap+xml; charset=utf-8' \
  --data-binary @wiremock/__files/nfe/soap/venda.xml

# A mesma venda rejeitada por total de CBS divergente
curl -s -X POST http://localhost:8089/sefaz/rejeicao-1091/NFeAutorizacao4 \
  -H 'Content-Type: application/soap+xml; charset=utf-8' \
  --data-binary @wiremock/__files/nfe/soap/venda.xml

# Cancelamento da venda
curl -s -X POST http://localhost:8089/sefaz/autorizado/NFeRecepcaoEvento4 \
  -H 'Content-Type: application/soap+xml; charset=utf-8' \
  --data-binary @wiremock/__files/nfe/soap/evento-cancelamento.xml
```

No PowerShell, troque as barras invertidas de continuação de linha por crase (`` ` ``) ou escreva o comando em uma linha só, e use `curl.exe` em vez de `curl`.

Com o script PHP do projeto, que já mostra o resultado resumido:

```bash
php exemplos/enviar-wiremock.php                        # venda, cenario autorizado
php exemplos/enviar-wiremock.php devolucao              # outro exemplo
php exemplos/enviar-wiremock.php venda rejeicao-1091    # outro cenario
php exemplos/enviar-wiremock.php evento-cancelamento    # eventos vao para NFeRecepcaoEvento4
```

Saída do primeiro comando:

```
POST http://localhost:8089/sefaz/autorizado/NFeAutorizacao4
HTTP 200
chNFe    35261111222333000181550010000010011079269192
cStat    100
xMotivo  Autorizado o uso da NF-e
nProt    135xxxxxxxxxxxxx
digVal   (DigestValue da nota enviada)
```

### Usando os XMLs nos seus testes

Alguns usos comuns:

- **Teste do seu leitor de XML**: importe `xml/venda.xml`, `xml/devolucao.xml` e `xml/venda-nfce.xml` e confira se o seu sistema lê os grupos `IBSCBS` e `IBSCBSTot`.
- **Teste do fluxo de emissão**: envie o envelope `soap/` para o cenário `autorizado` e confira se o seu sistema grava o protocolo. Depois repita com `rejeicao-1091` e `fora-do-ar` para testar o tratamento de erro e a contingência.
- **Teste de devolução**: emita a `venda`, depois envie a `devolucao`, que já referencia a chave da venda.
- **Teste de cancelamento**: autorize a `venda` e envie o `evento-cancelamento`, que usa a mesma chave.

### Gerando os XMLs de novo

Os XMLs são gerados pela biblioteca. Para mudar valores, itens ou criar um tipo novo, edite a lista `$exemplos` em `bin/gerar-xml-exemplos.php` e rode:

```bash
php bin/gerar-xml-exemplos.php
composer test
```

O teste `XmlExemplosTest` valida todos os XMLs contra o schema oficial da NF-e, confere o dígito verificador da chave, o `DigestValue` e o total da CBS.

## Coleção do Postman

A pasta [`postman/`](../postman) tem a coleção **WireMock - ReformaTributária**, com todos os mocks. Cada requisição tem descrição (aba **Docs**) com o que faz e a resposta esperada, e testes que conferem o status HTTP e o `cStat`.

| Pasta | Requisições |
|---|---|
| WireMock \| Administração | Health e requisições recebidas |
| Calculadora \| Tributos | Regime geral (sucesso e NCM inválido), observabilidade, base de cálculo unificada, geração e validação de XML e consulta de cClassTrib |
| NFes \| XML | Lista de exemplos e cada NF-e/NFC-e de exemplo (ex.: **NFe Xml Venda**, **NFe Xml Devolução**) |
| NFes \| SOAP | Os mesmos exemplos no envelope SOAP (ex.: **NFe Soap Venda com Redução**, **NFCe Soap Venda**, **Evento Soap Cancelamento**) |
| SEFAZ \| Autorização | A NF-e de venda em cada cenário: **Autorizado**, **Lento**, **Fora do Ar** e cada rejeição (ex.: **Rejeição 1091 \| Total de CBS difere da soma dos itens**) |
| SEFAZ \| Status e Eventos | Status do serviço, cancelamento e carta de correção |
| SEFAZ \| Enviar Exemplos | Todos os exemplos enviados ao cenário da variável `CENARIO` |

Para usar:

1. Suba o WireMock (`docker compose -f wiremock/docker-compose.yml up -d`).
2. No Postman, clique em **Import** e escolha os dois arquivos da pasta `postman/`: a coleção e o ambiente `WireMock local`.
3. Selecione o ambiente `WireMock local` e rode a coleção inteira com **Run collection**, ou só uma pasta.

Variáveis do ambiente:

- `BASE_URL`: endereço do WireMock (padrão `http://localhost:8089`).
- `CENARIO`: cenário usado na pasta **SEFAZ | Enviar Exemplos** (padrão `autorizado`). Troque para `rejeicao-1091`, por exemplo, para ver todos os exemplos rejeitados.

O cenário `lento` demora 15 segundos de propósito.

Pela linha de comando, com o [Newman](https://www.npmjs.com/package/newman):

```bash
npx newman run postman/reforma-tributaria-wiremock.postman_collection.json \
  -e postman/wiremock-local.postman_environment.json
```

A coleção é gerada a partir dos próprios mocks: os cenários vêm de `wiremock/mappings/sefaz` e os envelopes SOAP de `wiremock/__files/nfe/soap`. Não edite o JSON à mão. Depois de criar ou mudar um mock ou um XML de exemplo, gere de novo:

```bash
php bin/gerar-postman.php
```

O CI confere se a coleção está atualizada e roda ela inteira com o Newman contra o WireMock.

## Testes de integração

Os testes de `tests/Integracao` usam os mocks e os XMLs de exemplo. Eles são ignorados quando a variável `WIREMOCK_URL` não está definida.

```bash
docker compose -f wiremock/docker-compose.yml up -d
WIREMOCK_URL=http://localhost:8089 vendor/bin/phpunit --testsuite integracao
```

No Windows (PowerShell):

```powershell
docker compose -f wiremock/docker-compose.yml up -d
$env:WIREMOCK_URL = "http://localhost:8089"
vendor/bin/phpunit --testsuite integracao
```

O CI do GitHub roda esses testes em todo push e pull request (job `integracao (WireMock)`).

## Criando um cenário novo

1. Crie o mapeamento em `wiremock/mappings/sefaz/` ou `wiremock/mappings/calculadora/`. Para uma rejeição nova da SEFAZ, copie `autorizacao-rejeicao-1091.json` e troque o caminho, o `cStat` e o `xMotivo`.
2. Se a resposta for longa, coloque o corpo em `wiremock/__files/` e use `bodyFileName`.
3. Reinicie o WireMock: `docker compose -f wiremock/docker-compose.yml restart`.
4. Adicione o teste em `tests/Integracao/`.
5. Gere a coleção do Postman de novo: `php bin/gerar-postman.php`.
6. Atualize as tabelas deste documento.

Rejeições novas precisam citar a regra de validação da nota técnica (ex.: `W56-10`) no pull request.
