# Reforma Tributária API (PHP)

Biblioteca PHP open source para sistemas que emitem documentos fiscais durante a transição da Reforma Tributária do Consumo (EC 132/2023 e LC 214/2025).

Ela resolve, em PHP puro e sem dependências externas:

- **Cronograma de transição 2026–2033**: alíquotas fixadas em lei, ano a ano, e o que continua valendo (PIS/COFINS, ICMS/ISS, Imposto Seletivo).
- **Base de cálculo unificada** da NF-e e da NFC-e, com a vigência de cada componente (vProd, vDesc, vICMS, vPIS etc.).
- **Tabelas oficiais de CST e cClassTrib**, com vigência e percentuais de redução.
- **Cálculo do IBS e da CBS por item**, com a mesma política de arredondamento da calculadora da Receita.
- **Grupos XML `IBSCBS` e `IBSCBSTot`** da NF-e/NFC-e (NT 2025.002), validados contra o XSD oficial.
- **Cliente da Calculadora de Tributos oficial** da Receita Federal, para os casos que ainda não são calculados localmente.

Compatível com **PHP 7.4 até 8.5**.

> Aviso: esta biblioteca não substitui a orientação de um contador. Confira os resultados com a Calculadora de Tributos oficial antes de usar em produção.

## Sumário

- [Instalação](#instalação)
- [Uso](#uso)
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

Enquanto o pacote não estiver publicado no Packagist, instale direto do GitHub:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/edueevaristo/reforma-tributaria-api" }
    ],
    "require": {
        "edueevaristo/reforma-tributaria-api": "dev-main"
    }
}
```

Depois que estiver no Packagist:

```bash
composer require edueevaristo/reforma-tributaria-api
```

## Uso

### Cronograma de transição

```php
use EduardoEvaristo\ReformaTributaria\Transicao\Cronograma;

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
use EduardoEvaristo\ReformaTributaria\BaseCalculo\CalculadoraBaseCalculo;
use EduardoEvaristo\ReformaTributaria\BaseCalculo\ComponentesBaseCalculo;

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
use EduardoEvaristo\ReformaTributaria\Calculo\Aliquotas;
use EduardoEvaristo\ReformaTributaria\Calculo\CalculadoraIbsCbs;
use EduardoEvaristo\ReformaTributaria\Calculo\ItemIbsCbs;

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
use EduardoEvaristo\ReformaTributaria\Xml\GeradorXmlNfe;

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
use EduardoEvaristo\ReformaTributaria\Tabelas\TabelaClassificacaoTributaria;
use EduardoEvaristo\ReformaTributaria\Tabelas\TabelaSituacaoTributaria;

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
use EduardoEvaristo\ReformaTributaria\CalculadoraOficial\Cliente;

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
3. Rode `composer check` (padrão de código + testes).
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

## Dúvidas frequentes

**Por que não calcular tudo localmente?**
Alguns CST dependem de tabelas próprias (alíquotas uniformes, fixas, monofasia) que mudam com frequência. Até alguém implementar e testar esses casos contra a calculadora oficial, a biblioteca prefere avisar com `OperacaoNaoSuportadaException` a devolver um valor errado.

**Por que bcmath e não float?**
Float não representa valores decimais com exatidão. Um centavo de diferença entre item e total gera a rejeição 1091 na SEFAZ.

**Os valores batem com a calculadora oficial?**
Os testes reproduzem os exemplos da documentação oficial (base unificada e item com redução de 60%), e os grupos XML são validados contra o XSD distribuído com a calculadora.

**Como fica quando o Senado publicar a alíquota de referência?**
Até a regra ser cadastrada em `resources/regras/transicao.php`, informe a alíquota em `Aliquotas`. Depois, abra um PR na branch `regra/` com o número da resolução.

**Posso usar com NFePHP?**
Sim. Use `grupoItem()` e `grupoTotal()` passando o `DOMDocument` do seu XML e anexe os elementos em `det/imposto` e `total`.

## Licença

[MIT](LICENSE). Os dados e regras derivados da Calculadora de Tributos oficial também são MIT, da Receita Federal do Brasil. Veja [NOTICE](NOTICE).
