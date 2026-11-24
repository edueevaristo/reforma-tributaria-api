# Reforma Tributária API (PHP)

Biblioteca PHP open source para sistemas que emitem documentos fiscais durante a transição da Reforma Tributária do Consumo (EC 132/2023 e LC 214/2025).

Ela resolve, em PHP puro e sem dependências externas:

- **Cronograma de transição 2026–2033**: alíquotas fixadas em lei, ano a ano, e o que continua valendo (PIS/COFINS, ICMS/ISS, Imposto Seletivo).
- **Base de cálculo unificada** da NF-e e da NFC-e, com a vigência de cada componente (vProd, vDesc, vICMS, vPIS etc.).
- **Tabelas oficiais de CST e cClassTrib**, com vigência e percentuais de redução.
- **Cálculo do IBS e da CBS por item**, com a mesma política de arredondamento da calculadora da Receita.
- **Grupos XML `IBSCBS` e `IBSCBSTot`** da NF-e/NFC-e (NT 2025.002), validados contra o XSD oficial.
- **Cliente da Calculadora de Tributos oficial** da Receita Federal, para os casos que ainda não são calculados localmente.
- **Mocks WireMock** da calculadora oficial e dos web services de NF-e da SEFAZ, com as rejeições da Reforma Tributária, para testar a emissão de ponta a ponta.

Compatível com **PHP 7.4 até 8.5**.

> Aviso: esta biblioteca não substitui a orientação de um contador. Confira os resultados com a Calculadora de Tributos oficial antes de usar em produção.

## Sumário

- [Instalação](#instalação)
- [Uso](#uso)
- [Testes com WireMock](#testes-com-wiremock)
- [O que é calculado localmente](#o-que-é-calculado-localmente)
- [Regras por ano](#regras-por-ano)
- [Origem dos dados](#origem-dos-dados)
- [Como contribuir](#como-contribuir)
- [Padrão de branches](#padrão-de-branches)
- [Configuração do repositório (dono)](#configuração-do-repositório-dono)
- [Dúvidas frequentes](#dúvidas-frequentes)
- [Licença](#licença)

## Instalação

Requisitos: PHP 7.4 ou superior com as extensões `bcmath`, `dom` e `json`. A extensão `curl` só é necessária para usar o cliente da calculadora oficial.

O pacote está publicado no [Packagist](https://packagist.org/packages/du-nf/reforma-tributaria):

```bash
composer require du-nf/reforma-tributaria
```

Para testar o que ainda não saiu em versão, use a `main`:

```bash
composer require du-nf/reforma-tributaria:dev-main
```

### Migrando de `edueevaristo/reforma-tributaria-api`

A partir da versão 0.3.0 o pacote mudou de nome e de namespace. O pacote antigo `edueevaristo/reforma-tributaria-api` foi descontinuado e não recebe novas versões.

1. Troque o pacote no Composer:

   ```bash
   composer remove edueevaristo/reforma-tributaria-api
   composer require du-nf/reforma-tributaria
   ```

2. Troque o namespace no seu código, de `EduardoEvaristo\ReformaTributaria` para `DuNf\ReformaTributaria`:

   ```php
   // antes
   use EduardoEvaristo\ReformaTributaria\Transicao\Cronograma;

   // depois
   use DuNf\ReformaTributaria\Transicao\Cronograma;
   ```

   Nenhuma classe ou método mudou de nome; só o prefixo do namespace.

## Uso

### Cronograma de transição

```php
use DuNf\ReformaTributaria\Transicao\Cronograma;

$ano = (new Cronograma())->ano(2027);

$ano->aliquotaCbs();                // null: depende da alíquota de referência do Senado
$ano->ajusteCbsPontosPercentuais(); // '-0.1': redução legal em 2027 e 2028
$ano->aliquotaIbsUf();              // '0.05'
$ano->aliquotaIbsMun();             // '0.05'
$ano->pisCofinsVigentes();          // false
$ano->percentualIcmsIss();          // '100'
$ano->fundamentoLegal();            // ['LC 214/2025, art. 344', 'LC 214/2025, art. 347']
```

### Base de cálculo

```php
use DuNf\ReformaTributaria\BaseCalculo\CalculadoraBaseCalculo;
use DuNf\ReformaTributaria\BaseCalculo\ComponentesBaseCalculo;

$base = (new CalculadoraBaseCalculo())->calcular(
    new ComponentesBaseCalculo([
        'vProd' => '1000.00',
        'vDesc' => '50.00',
        'vPIS' => '15.68',
        'vCOFINS' => '72.20',
        'vICMS' => '171.00',
    ]),
    2026,
    CalculadoraBaseCalculo::NATUREZA_BEM,
    CalculadoraBaseCalculo::MODELO_NFE
);

$base->baseRegimeRegular();   // '691.12' (vBC do IBS/CBS)
$base->baseImpostoSeletivo(); // vBCIS
$base->avisos();              // componentes ignorados no ano, natureza ou modelo
```

### Cálculo do IBS e da CBS

```php
use DuNf\ReformaTributaria\Calculo\Aliquotas;
use DuNf\ReformaTributaria\Calculo\CalculadoraIbsCbs;
use DuNf\ReformaTributaria\Calculo\ItemIbsCbs;

$calculadora = new CalculadoraIbsCbs();

// 2026: usa as alíquotas de teste da lei (CBS 0,9% e IBS 0,1%)
$item = $calculadora->calcularItem(
    new ItemIbsCbs(1, '000', '000001', '691.12'),
    new DateTimeImmutable('2026-11-20')
);
$item->cbs()->valor();   // '6.22'
$item->ibsUf()->valor(); // '0.69'

// 2027 em diante: informe a alíquota da CBS (referência definida pelo Senado)
$documento = $calculadora->calcularDocumento([
    new ItemIbsCbs(1, '200', '200028', '1200.00', new Aliquotas('8.8')),
    new ItemIbsCbs(2, '410', '410001', '50.00'),
], new DateTimeImmutable('2027-03-10'));

$documento->totais()->cbs(); // '42.24'
```

Regra das alíquotas: a alíquota informada em `Aliquotas` tem prioridade. Quando não é informada, vale a alíquota fixada em lei para o ano. Se o ano não tiver alíquota fixada em lei, a biblioteca lança `DadoInvalidoException` pedindo a alíquota.

### Grupos XML da NF-e/NFC-e

```php
use DuNf\ReformaTributaria\Xml\GeradorXmlNfe;

$gerador = new GeradorXmlNfe();

// Como string
echo $gerador->grupoItemXml($item);
echo $gerador->grupoTotalXml($documento->totais());

// Ou como DOMElement, para anexar ao XML que seu sistema já monta (ex.: NFePHP)
$imposto->appendChild($gerador->grupoItem($item, $dom));
$total->appendChild($gerador->grupoTotal($documento->totais(), $dom));
```

Exemplo de saída (`php exemplos/nfe-2026.php`):

```xml
<IBSCBS xmlns="http://www.portalfiscal.inf.br/nfe">
  <CST>000</CST><cClassTrib>000001</cClassTrib>
  <gIBSCBS>
    <vBC>691.12</vBC>
    <gIBSUF><pIBSUF>0.10</pIBSUF><vIBSUF>0.69</vIBSUF></gIBSUF>
    <gIBSMun><pIBSMun>0.00</pIBSMun><vIBSMun>0.00</vIBSMun></gIBSMun>
    <vIBS>0.69</vIBS>
    <gCBS><pCBS>0.90</pCBS><vCBS>6.22</vCBS></gCBS>
  </gIBSCBS>
</IBSCBS>
```

### Tabelas de CST e cClassTrib

```php
use DuNf\ReformaTributaria\Tabelas\TabelaClassificacaoTributaria;
use DuNf\ReformaTributaria\Tabelas\TabelaSituacaoTributaria;

$data = new DateTimeImmutable('2027-01-01');

$classificacao = (new TabelaClassificacaoTributaria())->buscar('200028', $data);
$classificacao->descricao();            // 'Fornecimento dos serviços de educação (Anexo II)'
$classificacao->percentualReducaoCbs(); // '60'

$cst = (new TabelaSituacaoTributaria())->buscar('200', $data);
$cst->exigeGrupo('gRed'); // true

(new TabelaClassificacaoTributaria())->listar($data, '000'); // todas as cClassTrib do CST 000
```

### Calculadora de Tributos oficial

Para os casos que ainda não são calculados localmente, rode a [calculadora oficial](https://github.com/ReceitaFederal/calculadora-rtc-regime-regular) no seu ambiente e use o cliente:

```php
use DuNf\ReformaTributaria\CalculadoraOficial\Cliente;

$cliente = new Cliente('http://localhost:8080/api/calculadora');

$resultado = $cliente->calcularRegimeGeral($operacao);   // mesmo JSON do Swagger da calculadora
$xml = $cliente->gerarXml($resultado, 'nfe');
$cliente->validarXml($xml, 'nfe', 'grupo');
```

O transporte HTTP padrão usa `ext-curl`. Para usar Guzzle ou outro cliente, implemente a interface `TransporteHttp`.

### Erros

Todas as exceções estendem `ReformaTributariaException`:

| Exceção | Quando acontece |
|---|---|
| `DadoInvalidoException` | Valor, código ou combinação inválida (ex.: cClassTrib de outro CST, alíquota obrigatória não informada). |
| `RegraNaoEncontradaException` | Código ou ano sem regra vigente na data. |
| `OperacaoNaoSuportadaException` | O caso existe na lei, mas ainda não é calculado localmente. Use a calculadora oficial. |
| `ErroCalculadoraOficialException` | A calculadora oficial respondeu com erro. `statusHttp()` e `corpoResposta()` trazem os detalhes. |

## Testes com WireMock

A pasta [`wiremock/`](wiremock) traz mocks prontos para testar a emissão de NF-e sem serviços externos:

- **Calculadora de Tributos oficial**: cálculo, observabilidade, base unificada, geração e validação de XML e consulta de cClassTrib, com cenários de sucesso e de erro.
- **SEFAZ NF-e 4.00** (`NFeAutorizacao4`, `NFeRecepcaoEvento4` e `NFeStatusServico4`): nota autorizada, eventos registrados, SEFAZ lenta, SEFAZ fora do ar e as rejeições da Reforma Tributária (1023, 1024, 1041, 1069, 1076, 1085, 1091, 1104, 1115 e 1150).
- **XMLs de exemplo** com IBS e CBS, validados no schema oficial da NF-e: venda, venda com redução, NFC-e, devolução, bonificação, transferência, cancelamento e carta de correção.

```bash
docker compose -f wiremock/docker-compose.yml up -d

# Calculadora oficial simulada
curl -X POST http://localhost:8089/api/calculadora/regime-geral -H 'Content-Type: application/json' -d '{"itens":[]}'

# SEFAZ simulada: o cenario vai na URL
# http://localhost:8089/sefaz/autorizado/NFeAutorizacao4
# http://localhost:8089/sefaz/rejeicao-1091/NFeAutorizacao4

# XMLs de exemplo: lista, download e envio
curl http://localhost:8089/exemplos/nfe
curl http://localhost:8089/exemplos/nfe/xml/devolucao.xml -o devolucao.xml
php exemplos/enviar-wiremock.php devolucao autorizado
```

O protocolo de autorização usa a chave e o `DigestValue` da nota enviada, então o seu emissor recebe um retorno coerente com o XML que mandou. Todos os cenários, os XMLs de exemplo, como apontar o seu emissor para o mock e como criar cenários novos estão em [docs/wiremock.md](docs/wiremock.md).

## O que é calculado localmente

| CST | Situação | Local |
|---|---|---|
| 000 | Tributação integral | Sim |
| 200 | Alíquota reduzida (gRed) | Sim |
| 510 | Diferimento (gDif) | Sim |
| 515 | Diferimento com redução | Sim |
| 400, 410, 820 | Isenção, imunidade, documento específico (sem gIBSCBS) | Sim |
| 010, 011 | Alíquotas uniformes | Não, use a calculadora oficial |
| 220, 221, 222 | Alíquota fixa e redução de base | Não, use a calculadora oficial |
| 550 | Suspensão | Não, use a calculadora oficial |
| 620 | Monofasia | Não, use a calculadora oficial |
| 800, 810, 811 | Transferência de crédito e ajustes | Não, use a calculadora oficial |
| Imposto Seletivo | Grupo IS | Não, use a calculadora oficial |

A lista cresce com as contribuições. Cada novo caso precisa de testes que batam com a calculadora oficial.

Política de arredondamento (a mesma da calculadora oficial, ADR003): contas internas exatas, `HALF_EVEN`, base com 2 casas, alíquota efetiva com 4 casas, valor = base x alíquota publicada / 100 arredondado a 2 casas, e totais somando os valores publicados nos itens.

## Regras por ano

As regras ficam em [`resources/regras/transicao.php`](resources/regras/transicao.php), uma entrada por ano, sempre com o fundamento legal.

| Ano | CBS | IBS UF | IBS Mun | PIS/COFINS | ICMS/ISS | IS |
|---|---|---|---|---|---|---|
| 2026 | 0,9% (teste) | 0,1% (teste) | 0% | Vigentes | 100% | Não |
| 2027 | Referência − 0,1 p.p. | 0,05% | 0,05% | Extintos | 100% | Sim |
| 2028 | Referência − 0,1 p.p. | 0,05% | 0,05% | Extintos | 100% | Sim |
| 2029 | Referência | Senado | Senado | Extintos | 90% | Sim |
| 2030 | Referência | Senado | Senado | Extintos | 80% | Sim |
| 2031 | Referência | Senado | Senado | Extintos | 70% | Sim |
| 2032 | Referência | Senado | Senado | Extintos | 60% | Sim |
| 2033 | Referência | Senado | Senado | Extintos | Extintos | Sim |

"Referência" e "Senado" significam que a alíquota não está fixada na LC 214/2025: ela depende de resolução do Senado Federal e precisa ser informada em `Aliquotas` até ser cadastrada aqui.

## Origem dos dados

As tabelas de `resources/dados/` e as regras de base de cálculo vêm da [Calculadora de Tributos – Regime Regular](https://github.com/ReceitaFederal/calculadora-rtc-regime-regular), publicada pela Receita Federal e pelo Serpro sob licença MIT. A versão da base usada fica no campo `meta` de cada arquivo e pode ser lida com `versao()` nas classes de tabela.

Para atualizar as tabelas quando a Receita publicar uma nova versão, siga o passo a passo em [CONTRIBUTING.md](CONTRIBUTING.md#atualizar-as-tabelas-oficiais).

## Como contribuir

Contribuições são bem-vindas: novas regras, novos CST calculados localmente, correções e documentação. Leia o [CONTRIBUTING.md](CONTRIBUTING.md) antes de abrir um pull request.

Resumo:

1. Faça um fork e crie uma branch seguindo o [padrão de branches](#padrão-de-branches).
2. Escreva o código e os testes. Toda regra fiscal precisa citar o fundamento legal.
3. Rode `composer check` (padrão de código + testes). Se mexer nos mocks, rode também os testes de integração com WireMock ([docs/wiremock.md](docs/wiremock.md#testes-de-integração)).
4. Abra um pull request para a `main`. O CI roda em PHP 7.4 a 8.5.
5. O merge é feito pelo mantenedor depois da revisão.

Somente o dono do repositório (Eduardo Evaristo) envia commits direto para a `main`. Qualquer outra pessoa contribui por pull request.

## Padrão de branches

| Prefixo | Uso | Sai de | Volta para |
|---|---|---|---|
| `feature/` | Nova funcionalidade ou novo caso de cálculo | `main` | `main` via PR |
| `bugfix/` | Correção de erro sem urgência | `main` | `main` via PR |
| `hotfix/` | Correção urgente de valor errado já publicado em versão | `main` | `main` via PR, seguido de nova versão de correção |
| `regra/` | Nova regra legal ou atualização de alíquota, tabela ou vigência | `main` | `main` via PR |
| `docs/` | Somente documentação | `main` | `main` via PR |
| `chore/` | CI, dependências, configuração | `main` | `main` via PR |
| `release/` | Manutenção de uma versão antiga (ex.: `release/1.x`) | tag da versão | não volta: recebe cherry-pick |
| `cherry-pick/` | Levar uma correção da `main` para uma `release/` | `release/X` | `release/X` via PR |

Formato do nome: `prefixo/numero-da-issue-descricao-curta`, em minúsculas e com hífens. Exemplos:

```
feature/12-calculo-cst-620-monofasia
bugfix/18-arredondamento-total-cbs
regra/25-aliquota-referencia-cbs-2027
cherry-pick/release-1.x-18-arredondamento-total-cbs
```

Cherry-pick, quando uma correção da `main` precisa ir para uma versão antiga:

```bash
git checkout release/1.x
git checkout -b cherry-pick/release-1.x-18-arredondamento-total-cbs
git cherry-pick -x <hash-do-commit-na-main>
git push origin cherry-pick/release-1.x-18-arredondamento-total-cbs
# abra o PR para release/1.x
```

O `-x` registra no commit de onde ele veio.

Mensagens de commit seguem o [Conventional Commits](https://www.conventionalcommits.org/pt-br/), em português: `feat:`, `fix:`, `regra:`, `docs:`, `test:`, `refactor:`, `chore:`. Exemplo: `regra: cadastra aliquota de referencia da CBS para 2027`.

## Configuração do repositório (dono)

O GitHub só impede o push direto na `main` com a proteção de branch ligada. No repositório, acesse **Settings > Rules > Rulesets > New branch ruleset**:

1. Nome: `protecao-main`. Enforcement: **Active**. Target: **Default branch**.
2. Em **Bypass list**, adicione **Repository admin** (você). Assim só você envia direto para a `main`.
3. Marque **Restrict deletions** e **Block force pushes**.
4. Marque **Require a pull request before merging**, com 1 aprovação e **Require review from Code Owners**.
5. Marque **Require status checks to pass** e adicione os checks do workflow `CI`.
6. Salve.

O arquivo [`.github/CODEOWNERS`](.github/CODEOWNERS) já define você como revisor obrigatório de todo o código.

### Atualização automática no Packagist

O pacote já está com atualização automática: o Packagist lê cada push e cada tag sozinho. Se a página do pacote voltar a mostrar o aviso "This package is not auto-updated", configure o webhook no GitHub em **Settings > Webhooks > Add webhook**:

1. **Payload URL**: `https://packagist.org/api/github?username=edueevaristo`
2. **Content type**: `application/json`
3. **Secret**: o API Token da sua página de perfil no Packagist (packagist.org/profile)
4. **Which events**: Just the push event
5. Salve. O aviso "This package is not auto-updated" some da página do pacote depois do primeiro push.

## Dúvidas frequentes

**Por que não calcular tudo localmente?**
Alguns CST dependem de tabelas próprias (alíquotas uniformes, fixas, monofasia) que mudam com frequência. Até alguém implementar e testar esses casos contra a calculadora oficial, a biblioteca prefere avisar com `OperacaoNaoSuportadaException` a devolver um valor errado.

**Por que bcmath e não float?**
Float não representa valores decimais com exatidão. Um centavo de diferença entre item e total gera a rejeição 1091 na SEFAZ.

**Os valores batem com a calculadora oficial?**
Os testes reproduzem os exemplos da documentação oficial (base unificada e item com redução de 60%), e os grupos XML são validados contra o XSD distribuído com a calculadora.

**Como fica quando o Senado publicar a alíquota de referência?**
Até a regra ser cadastrada em `resources/regras/transicao.php`, informe a alíquota em `Aliquotas`. Depois, abra um PR na branch `regra/` com o número da resolução.

**Como testo a emissão sem a SEFAZ e sem a calculadora oficial?**
Use os mocks WireMock. Suba com `docker compose -f wiremock/docker-compose.yml up -d` e aponte o seu emissor para `http://localhost:8089/sefaz/{cenario}/NFeAutorizacao4`. Veja [docs/wiremock.md](docs/wiremock.md).

**Como o Packagist recebe as versões novas?**
Automaticamente, a cada push e a cada tag. Se a atualização automática parar, use o botão **Update** na página do pacote no Packagist.

**Posso usar com NFePHP?**
Sim. Use `grupoItem()` e `grupoTotal()` passando o `DOMDocument` do seu XML e anexe os elementos em `det/imposto` e `total`.

## Licença

[MIT](LICENSE). Os dados e regras derivados da Calculadora de Tributos oficial também são MIT, da Receita Federal do Brasil. Veja [NOTICE](NOTICE).
