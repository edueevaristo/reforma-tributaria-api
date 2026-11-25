# Como contribuir

Obrigado por ajudar. A Reforma Tributária vai mudar até 2033 e este projeto só se mantém atualizado com a comunidade.

## Fluxo

1. Abra uma issue descrevendo o problema ou a regra (use os modelos de issue). Para regra legal, cite a norma e o artigo.
2. Faça um fork e crie a branch a partir da `main`, seguindo o padrão `prefixo/numero-da-issue-descricao-curta` (veja o [README](README.md#padrão-de-branches)).
3. Faça as alterações com testes.
4. Rode `composer check`.
5. Abra o pull request para a `main` preenchendo o modelo.
6. O mantenedor revisa e faz o merge. Pull requests são integrados com **squash merge**.

Somente o dono do repositório envia commits direto para a `main`.

## Regras de código

- **Compatibilidade**: o código precisa rodar do PHP 7.4 ao 8.5. Não use recursos de PHP 8 (`match`, enums, promoção de propriedades no construtor, union types, named arguments, `readonly`, nullsafe `?->`, `str_contains` e similares). O `composer lint` usa o PHPCompatibility para conferir.
- **Padrão**: PSR-1, PSR-4 e PSR-12, `declare(strict_types=1)` em todo arquivo, classes `final` sempre que possível, propriedades tipadas.
- **Números**: toda conta passa pelo value object `Suporte\Decimal` (bcmath, imutável). Nunca use `float` em cálculo. Na API pública, os valores publicados saem como `string`, já no formato do documento fiscal.
- **Sem primitivos soltos**: conceitos do domínio são value objects com construtores nomeados (`Tributo::cbs()`, `Natureza::bem()`, `ModeloDocumento::nfe()`, `Vigencia`). Não passe `'bem'`, `55` ou `'cbs'` pelo código.
- **Um tributo, uma regra**: IBS estadual, IBS municipal e CBS têm a mesma regra de cálculo. Percorra `Tributo::todos()` em vez de repetir o código três vezes.
- **Diga, não pergunte**: peça ao objeto o que ele sabe (`$item->aliquotaInformada($tributo)`, `$situacao->exigeReducao()`) em vez de encadear getters (`$item->aliquotas()->cbs()`).
- **Métodos pequenos**: um nível de indentação sempre que possível, sem `else` (use retorno antecipado), nomes por extenso.
- **Injeção de dependência**: classes que leem dados (`Cronograma`, `CalculadoraBaseCalculo`, tabelas) aceitam os dados no construtor; com `null`, carregam os oficiais do pacote. Use isso nos testes em vez de mexer nos arquivos de `resources/`.
- **Resultados imutáveis**: objetos de resultado são criados só por construtores nomeados (`ResultadoItem::comGrupoIbsCbs()`, `ResultadoTributo::calcular()`) e não têm setters.
- **Classes internas**: classes marcadas com `@internal` (ex.: `Enquadramento`, `Bases`, `ConstrutorXml`) podem mudar sem aviso. Não dependa delas fora do pacote.
- **Arredondamento**: siga a política da calculadora oficial (ADR003). Arredonde só o número publicado e use o valor publicado na conta seguinte.
- **Simplicidade**: siga o padrão do arquivo que você está alterando. Não crie `try/catch` genérico para esconder erro, validações fora do padrão do projeto ou condições que só existem para um caso.
- **Erros**: lance as exceções do pacote (`DadoInvalidoException`, `RegraNaoEncontradaException`, `OperacaoNaoSuportadaException`). Mensagens em português, dizendo o que fazer.
- **Caso não suportado**: se a regra ainda não é calculada localmente, lance `OperacaoNaoSuportadaException`. Nunca devolva um valor aproximado.
- **Sem emojis** em código, comentários, commits e documentação.
- **Sem dependências** de produção além das extensões do PHP.

## Regras para alterar regras fiscais

As regras ficam em `resources/regras/` e os dados oficiais em `resources/dados/`.

- Uma entrada por ano em `transicao.php`. Nunca apague um ano: notas antigas continuam sendo emitidas, canceladas e corrigidas.
- Toda alteração de alíquota, percentual ou vigência precisa preencher `fundamentoLegal` com norma e artigo (ex.: `Resolucao do Senado Federal n. X/2026, art. 1`).
- Use `null` quando a lei não fixa a alíquota. Não invente valores estimados.
- Use a branch `regra/` e informe no PR a fonte oficial (link do DOU, da Receita ou do Portal NF-e).
- Adicione ou ajuste os testes em `tests/Unit/CronogramaTest.php`.

## Atualizar as tabelas oficiais

As tabelas de CST e cClassTrib são geradas a partir do banco da Calculadora de Tributos oficial. Não edite os JSON à mão.

```bash
# 1. Baixe a calculadora oficial (precisa de Java 25)
git clone https://github.com/ReceitaFederal/calculadora-rtc-regime-regular.git
cd calculadora-rtc-regime-regular
mkdir -p ./calculadora/db
./mvnw flyway:migrate -Dflyway.configFiles=./flyway/flyway-pro.conf --batch-mode --errors

# 2. Volte para este projeto e gere os JSON (precisa de ext-pdo_sqlite)
php bin/atualizar-tabelas.php /caminho/calculadora-rtc-regime-regular/calculadora/db/calculadora-pro.db

# 3. Rode os testes e confira o diff
composer check
git diff --stat resources/dados
```

Abra o PR em uma branch `regra/` com a versão da base (`versaoBaseDados`) no título. Exemplo: `regra: atualiza tabelas para a base V0074`.

## Novo caso de cálculo local

Para tirar um CST da lista de não suportados:

1. Leia a regra na NT 2025.002 e no código da calculadora oficial.
2. Implemente em `src/Calculo/`: as regras de compatibilidade do CST ficam em `Enquadramento` e a conta de cada tributo em `ResultadoTributo::calcular()`.
3. Escreva testes com valores conferidos na calculadora oficial (informe a entrada e a resposta no PR).
4. Se o caso gera um grupo XML novo, valide contra o XSD em `tests/Fixtures/xsd`.
5. Atualize a tabela "O que é calculado localmente" do README.

## Commits

[Conventional Commits](https://www.conventionalcommits.org/pt-br/) em português:

| Tipo | Uso |
|---|---|
| `feat:` | Nova funcionalidade |
| `fix:` | Correção de erro |
| `regra:` | Regra legal, alíquota, tabela ou vigência |
| `docs:` | Documentação |
| `test:` | Somente testes |
| `refactor:` | Mudança de código sem mudar comportamento |
| `chore:` | CI, configuração, dependências |

## Versões

O projeto segue [SemVer](https://semver.org/lang/pt-BR/):

- **Patch** (1.0.x): correções e atualização de tabelas sem mudança de API.
- **Minor** (1.x.0): novas regras, novos casos de cálculo e novos métodos.
- **Major** (x.0.0): mudança que quebra a API pública.

Cada versão é uma tag `vX.Y.Z` criada pelo mantenedor, com o resumo no `CHANGELOG.md`.

## Rodando localmente

```bash
composer install
composer test    # PHPUnit
composer lint    # PSR-12 + compatibilidade PHP 7.4+
composer check   # os dois
php exemplos/nfe-2026.php

# Testes de integracao com os mocks WireMock (precisa de Docker)
docker compose -f wiremock/docker-compose.yml up -d
WIREMOCK_URL=http://localhost:8089 vendor/bin/phpunit --testsuite integracao
```

Detalhes dos mocks e como criar cenários novos: [docs/wiremock.md](docs/wiremock.md).

## Publicar uma versão (mantenedor)

1. Atualize o `CHANGELOG.md`, movendo os itens de "Não publicado" para a versão nova.
2. Crie a tag e envie: `git tag -a vX.Y.Z -m "vX.Y.Z" && git push origin vX.Y.Z`.
3. O Packagist lê a tag pelo webhook do GitHub e publica a versão em [du-nf/reforma-tributaria](https://packagist.org/packages/du-nf/reforma-tributaria). Se a versão não aparecer em alguns minutos, clique em **Update** na página do pacote.
4. Confira se o CI da tag passou na aba **Actions** do GitHub.
