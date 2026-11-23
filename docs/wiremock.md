# Testes com WireMock

A pasta [`wiremock/`](../wiremock) tem mocks prontos para testar a emissão de NF-e com IBS e CBS sem depender de serviços externos:

- **Calculadora de Tributos oficial** da Receita Federal (`/api/calculadora/...`), para testar o `CalculadoraOficial\Cliente` sem instalar Java nem subir a calculadora.
- **Web services de NF-e 4.00 da SEFAZ** (`/sefaz/...`), para testar o seu emissor com autorização, as rejeições da Reforma Tributária, SEFAZ fora do ar e SEFAZ lenta.

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
use EduardoEvaristo\ReformaTributaria\CalculadoraOficial\Cliente;

$cliente = new Cliente('http://localhost:8089/api/calculadora');
$resultado = $cliente->calcularRegimeGeral($operacao);
```

## Mocks da SEFAZ (NF-e 4.00)

O cenário vai na URL: `http://localhost:8089/sefaz/{cenario}/{servico}`.

Serviços simulados:

- `NFeAutorizacao4`: autorização síncrona (`indSinc = 1`). Devolve `retEnviNFe` com `cStat` 104 e o `protNFe`.
- `NFeStatusServico4`: devolve `cStat` 107 (serviço em operação).

Cenários:

| Cenário | `cStat` do protocolo | Mensagem |
|---|---|---|
| `autorizado` | 100 | Autorizado o uso da NF-e |
| `lento` | 100 | Igual ao `autorizado`, com 15 segundos de atraso, para testar timeout |
| `fora-do-ar` | HTTP 503 | Página de erro, para testar contingência |
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

## Testes de integração

Os testes de `tests/Integracao` usam os mocks. Eles são ignorados quando a variável `WIREMOCK_URL` não está definida.

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
5. Atualize as tabelas deste documento.

Rejeições novas precisam citar a regra de validação da nota técnica (ex.: `W56-10`) no pull request.
