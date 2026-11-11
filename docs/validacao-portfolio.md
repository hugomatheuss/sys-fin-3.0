# Validação do portfólio

## Etapa 1 — preparação da demonstração

Validação executada em 10/11/2026 na branch `portfolio/evolucao-sysfinan`. A aplicação executou com PHP 8.2.35, Laravel e dependências do lockfile existente. Nenhum deploy ou acesso ao banco de produção foi realizado.

## Resultados

| Verificação | Resultado |
| --- | --- |
| `docker compose -f compose.portfolio.yaml up -d --build` | Ambiente iniciado após ajustes locais de inicialização; projeto `sysfinan-portfolio` |
| `docker compose -f compose.portfolio.yaml config --quiet` | Passou |
| `php artisan migrate --force`, no serviço `app` de portfólio | Migrations existentes executadas no banco novo `sysfinan_portfolio` |
| `php artisan db:seed --class=PortfolioDemoSeeder --force`, no mesmo serviço | Executado duas vezes; carga fictícia disponível |
| `./vendor/bin/phpunit --configuration=phpunit.portfolio.xml`, no mesmo serviço | 11 testes, 58 asserções; todos passaram com SQLite em memória |
| `./vendor/bin/pint --test config/portfolio.php database/seeders/PortfolioDemoSeeder.php tests/Feature/PortfolioDemoTest.php`, no mesmo serviço | Passou nos três arquivos novos |
| `php -l` nos três arquivos PHP novos, a partir de `src/` | Sem erros de sintaxe |
| HTTP interno `http://nginx/admin/login` | 200 OK |
| HTTP interno `http://mailpit:8025` | 200 OK |
| `git diff --check` | Passou |
| `git check-ignore src/.env.portfolio` | Arquivo local ignorado |

Para reproduzir os comandos PHP acima, use `docker compose -f compose.portfolio.yaml exec --user app app` a partir da raiz, seguido do comando. O diretório de trabalho no contêiner é o da aplicação.

## Comportamentos cobertos

A suíte inclui os dois testes originais e nove testes de portfólio: repetição da carga sem apagar alterações; bloqueio do seeder em produção, quando desabilitado e sem senha; consultas e filtros dos exportadores restritos ao usuário; edição direta entre dois usuários; login por CNPJ; cadastro preservando o valor e proprietário; pagamento preservando o código string e data.

Uma sondagem temporária adicional passou em cinco cenários com 25 asserções: login, cadastro, pagamento e download dos dois PDFs. Essa sondagem não foi adicionada à suíte: o download, isoladamente, não valida o conteúdo do documento. Login, cadastro e pagamento foram incorporados à suíte permanente.

## Bloqueios e limites

- **PDF de lançamentos:** confirmado que `Blade::render('lancamentos-pdf', ...)` produz o texto literal `lancamentos-pdf`. O template correspondente não existe com esse nome. O download pode ocorrer sem o conteúdo esperado. O template de contas existe e é resolvido pelo renderizador instalado.
- Os arquivos visuais não foram alterados. Não foi executado build Node nesta entrega.
- Não houve revisão visual no navegador nem inspeção visual dos PDFs. O roteiro manual permanece disponível no guia.
- O fluxo completo dos XLSX, incluindo jobs e autorização de download, não foi validado; os testes verificam os filtros aplicados à consulta dos exportadores de contas.
- A transição inversa de pagamento, os filtros monetários, a exclusão e a adulteração de `user_id` nos formulários ainda precisam de cobertura específica. Os riscos existentes estão descritos no guia.
- `php artisan test --configuration=phpunit.portfolio.xml` não selecionou corretamente a configuração nesta combinação de versões. O comando documentado usa PHPUnit diretamente e força variáveis de servidor para não herdar o PostgreSQL do contêiner.
- A instalação não executa os scripts Composer que republicam assets. Os arquivos gerados e demais alterações que já estavam no workspace foram preservados.

O ambiente ficou em execução para inspeção local. Para encerrá-lo preservando os dados fictícios, execute `docker compose -f compose.portfolio.yaml stop`.


## Etapa 2 — conteúdo e isolamento dos PDFs

As ações passaram a usar `Pdf::loadView(...)->output()`. Foi adicionado o template `lancamentos-pdf` para listar os registros selecionados, com tipos legíveis e data de lançamento. As duas ações filtram a coleção selecionada pelo usuário autenticado antes de renderizar. Valores continuam como strings, sem novos cálculos ou conversões monetárias.

Validação após a correção:

- `./vendor/bin/phpunit --configuration=phpunit.portfolio.xml`: **14 testes, 115 asserções; passou**.
- `./vendor/bin/pint --test app/Filament/Resources/ContaResource.php app/Filament/Resources/LancamentoResource.php tests/Feature/PdfExportTest.php`: **passou**. A formatação foi aplicada somente aos três arquivos PHP desta etapa.
- `npm ci` no serviço Node local: instalação concluída com o lockfile existente. A auditoria automática reportou 20 vulnerabilidades (6 moderadas, 13 altas e 1 crítica); nenhuma dependência foi atualizada. A avaliação dessas dependências continua pendente.
- A primeira tentativa de `npm run build` não encontrou o preset Tailwind do Filament, porque o Node não tinha acesso ao volume de dependências PHP. Com esse volume montado apenas para leitura, **o build passou**:

```bash
docker compose -f compose.yaml run --rm --no-deps --volume sysfinan-portfolio_vendor:/var/www/html/vendor:ro node npm run build
```

- `git diff --check`: **passou**.

Os testes adicionais exercitam as ações de download para contas e lançamentos com dois usuários, incluindo seleção adulterada com ID de outro usuário. Verificam que somente o registro selecionado do usuário atual é enviado ao template e que os valores ou fornecedores não selecionados ficam fora do HTML. Há ainda geração real de PDF para o template de lançamentos, com verificação de tipos legíveis e assinatura PDF.

O bloqueio de conteúdo do PDF de lançamentos registrado na etapa 1 está corrigido. A revisão visual dos documentos, a formatação monetária e a validação completa do XLSX seguem pendentes. Esta etapa não altera migrations, códigos de status ou as regras de pagamento. Nenhum arquivo gerado do Filament foi incluído.


## Etapa 3 — valores monetários e reversão de pagamentos

Os testes de regressão reproduziram quatro falhas antes da correção: filtro sem encontrar o valor informado, reversão gravando `0` em vez de `2`, repetição do estado pago alterando a data e perda de centavos no total de valores altos.

Correções:

- O filtro passa a usar entrada textual decimal, normalização por strings e comparação das representações legadas equivalentes (`12`, `12.0` e `12.00`). Não há multiplicação por dez, arredondamento ou conversão para float. Campo vazio mantém a consulta; entrada inválida não retorna contas.
- O toggle persiste status e data na mesma atualização: `1` para pago e `2` para pendente. Desmarcar limpa a data; marcar usa o relógio Laravel; repetir pago mantém a data original. A consulta do recurso continua impedindo alterações em contas de outro usuário.
- O total dos lançamentos usa `bcsub` com duas casas e formatação BRL por strings, preservando os centavos mesmo acima da precisão de float. BCMath já está presente na imagem PHP utilizada pelo projeto.

Validação:

```bash
docker compose -f compose.portfolio.yaml exec --user app app ./vendor/bin/phpunit --configuration=phpunit.portfolio.xml
docker compose -f compose.portfolio.yaml exec --user app app ./vendor/bin/pint --test app/Filament/Resources/ContaResource.php app/Filament/Resources/LancamentoResource.php app/Support/ValorMonetario.php tests/Feature/FinanceiroTest.php tests/Unit/ValorMonetarioTest.php
```

- PHPUnit: **23 testes, 202 asserções; passou**.
- Pint: **passou nos cinco arquivos PHP desta etapa**.
- `git diff --check`: **passou**.
- Os novos casos cobrem BRL, ponto decimal, milhares, zero, centavos, strings sem zeros finais, entrada inválida/vazia, saldo negativo, valores altos, os dois sentidos do toggle, repetição do pagamento e tentativa de alterar conta de outro usuário.
- Não houve alteração em Blade, CSS ou JavaScript nesta etapa; o build não foi repetido.

Não há migration nem correção automática de dados históricos. Registros que eventualmente já tenham status `0`, formatos monetários não suportados ou precisão superior a centavos exigem uma avaliação específica. Os campos monetários de cadastro/edição e a apresentação de outras colunas ainda usam os componentes existentes; esta etapa corrige especificamente o filtro, o total e o toggle. Os demais caminhos de edição e a validação completa dos XLSX continuam pendentes.


## Etapa 4 — formulários monetários e proteção do proprietário

Os seis testes iniciais de regressão falharam antes das correções, reproduzindo perda de precisão no cadastro/edição, validação monetária insuficiente, alteração de proprietário e mass assignment liberado globalmente. Foram acrescentados testes de validação de tipos/códigos e datas.

Correções e compatibilidade:

- O componente `ValorMonetarioInput`, baseado no `TextInput` do Filament, substitui o campo que convertia valores para float. Ele usa o normalizador por strings, exibe pt-BR e grava decimais com duas casas em strings. Preserva valores altos ao abrir e salvar edições. Não há atualização de esquema ou conversão em lote dos dados existentes.
- A entrada agora é decimal explícita (`1.234,56` ou `1234.56`), sem deslocamento automático de dígitos para centavos. A orientação aparece no formulário. Valores inválidos, vazios ou acima da capacidade da coluna são recusados no servidor, sem arredondamento.
- Fornecedor, campos monetários, datas necessárias e códigos string permitidos recebem validação. Conta paga exige data de pagamento; reabrir a conta pelo formulário limpa essa data. Campos opcionais continuam opcionais.
- A liberação global de mass assignment foi retirada. `user_id` não é mais um campo do formulário nem um atributo preenchível dos modelos financeiros. A criação associa o proprietário pelo relacionamento com o usuário autenticado; a edição ignora qualquer `user_id` enviado pelo cliente.

Validação:

```bash
docker compose -f compose.portfolio.yaml exec --user app app ./vendor/bin/phpunit --configuration=phpunit.portfolio.xml
docker compose -f compose.portfolio.yaml exec --user app app ./vendor/bin/pint --test     app/Filament/Resources/ContaResource.php     app/Filament/Resources/LancamentoResource.php     app/Filament/Resources/ContaResource/Pages/CreateConta.php     app/Filament/Resources/ContaResource/Pages/EditConta.php     app/Filament/Resources/LancamentoResource/Pages/CreateLancamento.php     app/Filament/Resources/LancamentoResource/Pages/EditLancamento.php     app/Models/Lancamento.php app/Providers/AppServiceProvider.php     app/Filament/Forms/Components/ValorMonetarioInput.php     tests/Feature/FormularioFinanceiroTest.php
```

- PHPUnit: **32 testes, 319 asserções; passou**. Os nove testes novos cobrem criação, edição, round-trip de valores altos, centavos, entradas inválidas, array na validação do servidor, códigos/obrigatoriedade, datas e tentativa de transferência entre dois usuários.
- Pint: **passou nos dez arquivos PHP alterados**.
- `git diff --check`: **passou**.
- Sem mudanças em Blade, CSS ou JavaScript; build não foi repetido nesta etapa.

Não foram alterados banco de produção, dados históricos ou dependências. A revisão visual da nova interação decimal e dos documentos, os formatadores das demais colunas de dinheiro e a validação completa dos XLSX continuam pendentes. O ambiente de portfólio permanece isolado da produção.

## Etapa 5 — exportações XLSX e colunas monetárias

Validação repetida em 11/11/2026 na branch `portfolio/evolucao-sysfinan`, no ambiente Docker de portfólio, usando SQLite em memória.

Correções:

- Os três exportadores herdam de `ExportadorFinanceiro`: novas exportações e arquivos intermediários usam o disco privado `local`. A consulta restringe os registros ao usuário autenticado, preservando os filtros da tabela; cada linha também verifica o proprietário persistido da exportação, sem depender da sessão do worker.
- As planilhas preservam valores monetários como texto decimal, inclusive valores altos e centavos. Datas ausentes ficam vazias. Os rótulos dos tipos de lançamento e as notificações permanecem em português.
- As colunas de valor, recebimento e pagamento usam o formatador BRL por strings, sem conversão para float.
- O middleware de autenticação redireciona para o login existente do painel. Downloads sem sessão deixam de falhar com erro 500; requisições JSON recebem 401.

Verificações executadas a partir da raiz:

```bash
docker compose -f compose.portfolio.yaml exec --user app app ./vendor/bin/phpunit --configuration=phpunit.portfolio.xml
docker compose -f compose.portfolio.yaml exec --user app app ./vendor/bin/pint --test app/Filament/Exports/ExportadorFinanceiro.php app/Filament/Exports/ContasNaoPagasExporter.php app/Filament/Exports/ContasPagasExporter.php app/Filament/Exports/LancamentoExporter.php app/Filament/Resources/ContaResource.php app/Filament/Resources/LancamentoResource.php app/Http/Middleware/Authenticate.php tests/Feature/FinanceiroTest.php tests/Feature/XlsxExportTest.php
git diff --check
```

- PHPUnit: **41 testes, 455 asserções; passou**.
- Pint: **passou nos nove arquivos PHP da entrega**.
- `git diff --check`: **passou**.
- Os testes executam os jobs com fila síncrona, leem os arquivos XLSX gerados e verificam filtros de fornecedor, valor, status e tipo, isolamento entre dois usuários, precisão monetária, datas e armazenamento privado. O download entrega os bytes XLSX ao proprietário, retorna 403 para outro usuário e recusa acesso sem autenticação. Há cobertura da verificação de linhas sem sessão e de consultas sem usuário.
- Não houve mudanças em Blade, CSS ou JavaScript nesta etapa; o build não foi repetido. Alterações locais preexistentes em assets gerados do Filament ficaram fora da entrega.

Sem migrations, atualização de dependências ou deploy. Exportações antigas no disco público não foram movidas nem removidas. A execução com workers externos, a inspeção visual das planilhas e PDFs, a ordenação numérica dos valores armazenados como strings e a revisão das demais ações seguem pendentes.

## Etapa 6 — ordenação numérica e apresentação dos PDFs

Os novos testes reproduziram a ordenação incorreta de contas e lançamentos antes da correção. A consulta base ordenava por data antes da escolha do usuário; recebimento e pagamento eram comparados como texto; a coluna calculada `Total` tentava ordenar por uma coluna inexistente no PostgreSQL.

Correções:

- A ordenação por data financeira foi movida para `defaultSort` do Filament. O padrão continua decrescente, mas a escolha de uma coluna passa a prevalecer.
- Valor, recebimento e pagamento usam conversão SQL para `NUMERIC`. Total ordena pela diferença entre os dois valores decimais. A direção é limitada a `asc` ou `desc`, e as consultas mantêm o filtro do usuário autenticado. Não houve alteração dos valores armazenados.
- Os dois PDFs usam estilos compartilhados em `resources/views/pdf/estilos.blade.php`, A4 paisagem, valores alinhados à direita, datas `d/m/Y`, cabeçalhos repetidos e mensagens para seleções vazias. Contas passam a exibir datas financeiras, situação, tipo e documento; lançamentos incluem saldo por linha, calculado com BCMath. Datas ausentes aparecem como travessão. Os textos continuam escapados pelo Blade.

Verificações executadas a partir da raiz:

```bash
docker compose -f compose.portfolio.yaml exec --user app app ./vendor/bin/phpunit --configuration=phpunit.portfolio.xml
docker compose -f compose.portfolio.yaml exec --user app -e SYSFINAN_TEST_POSTGRES=1 app ./vendor/bin/phpunit --configuration=phpunit.portfolio.xml tests/Integration/OrdenacaoPostgresTest.php
docker compose -f compose.portfolio.yaml exec --user app app ./vendor/bin/pint --test app/Filament/Resources/ContaResource.php app/Filament/Resources/LancamentoResource.php tests/Feature/FinanceiroTest.php tests/Feature/PdfExportTest.php tests/Integration/OrdenacaoPostgresTest.php
docker compose -f compose.yaml run --rm --no-deps --volume sysfinan-portfolio_vendor:/var/www/html/vendor:ro node npm run build
git diff --check
```

- Suíte principal: **46 testes, 516 asserções; passou**. Os novos casos cobrem ordenação nos dois sentidos, precedência sobre datas, saldo negativo, padrão por data financeira, isolamento, valores BRL nos PDFs, datas ausentes, escape de texto e seleção vazia.
- Após o ajuste final dos cabeçalhos, `./vendor/bin/phpunit --configuration=phpunit.portfolio.xml --filter=PdfExportTest` foi repetido no serviço `app`: **5 testes, 75 asserções; passou**. O build também foi repetido e passou.
- PostgreSQL: **1 teste adicional, 24 asserções; passou**. Usa somente tabelas temporárias em conexão exclusiva com `search_path=pg_temp`, sem acesso a registros persistentes. Compara a ordenação com `bccomp`, incluindo diferenças de um centavo acima da precisão de float e strings no limite de armazenamento. Essa verificação é explícita, fora da suíte padrão, porque o SQLite converte `NUMERIC` para uma representação de precisão limitada.
- Pint: **passou nos cinco arquivos PHP**. A execução sem `--test` não precisou alterar a formatação.
- Build Vite: **passou**, sem instalar ou atualizar dependências. Houve apenas o aviso existente sobre a base Browserslist desatualizada.
- `git diff --check`: **passou**.
- PDFs fictícios gerados em `/tmp`, renderizados com `pdftoppm` e conferidos visualmente: **32 contas em duas páginas e 48 lançamentos em três páginas**. A extração com `pdftotext -layout` confirmou os 32 documentos, repetição dos cabeçalhos em todas as páginas e valores altos/negativos sem perda de centavos. A inspeção encontrou que `colgroup` não aplicava as larguras no DomPDF instalado; as larguras foram transferidas para os cabeçalhos e as prévias foram regeneradas e reinspecionadas. Os arquivos de prévia não foram versionados.

Sem migrations, alterações de configuração de produção, atualização de dependências ou deploy. A revisão visual das telas no navegador, workers externos dos XLSX e revisão das demais ações continuam pendentes. Dados monetários históricos fora do formato decimal suportado ainda exigem avaliação específica. Os assets gerados do Filament e o `.gitignore` preexistentes ficaram fora desta entrega.

## Etapa 7 — autorização, indicadores mensais e feedback do login

Os testes iniciais confirmaram que as exclusões individuais e em lote já respeitavam a consulta filtrada pelo usuário. Foram acrescentadas policies explícitas para proteger também a autorização de registros em outros pontos da aplicação. Os testes dos indicadores reproduziram contagens incorretas quando a data de cadastro pertencia a um mês diferente da data financeira. Um teste adicional reproduziu a mensagem de credenciais inválidas associada ao campo inexistente `data.login`.

Implementações:

- `ContaPolicy` e `LancamentoPolicy`, registradas no `AuthServiceProvider`, autorizam visualização, edição e exclusão apenas ao proprietário. Listagem, criação e exclusão em lote exigem usuário autenticado; os filtros existentes por `user_id` continuam limitando os registros da seleção. Nenhuma regra de acesso por domínio de e-mail foi alterada.
- O painel usa vencimento para contar contas pendentes do mês, pagamento para contas pagas e data de lançamento para as quatro categorias de movimentação. As contagens gerais continuam abrangendo todo o histórico do usuário. As descrições dos cartões explicam o período e a data utilizada. Não foram introduzidos cálculos monetários nem conversões para float.
- Falhas de autenticação por CNPJ agora são exibidas no campo `data.cnpj`, tanto para senha incorreta quanto para identificador inexistente, usando a mesma mensagem do Filament.

Verificações executadas a partir da raiz:

```bash
docker compose -f compose.portfolio.yaml exec --user app app ./vendor/bin/phpunit --configuration=phpunit.portfolio.xml
docker compose -f compose.portfolio.yaml exec --user app app ./vendor/bin/pint --test app/Policies/ContaPolicy.php app/Policies/LancamentoPolicy.php app/Providers/AuthServiceProvider.php app/Filament/Widgets/StatsOverview.php app/Filament/Auth/Login.php tests/Feature/AutorizacaoFinanceiraTest.php tests/Feature/IndicadoresFinanceirosTest.php tests/Feature/LoginTest.php
git diff --check
```

- Suíte principal: **55 testes, 750 asserções; passou**, com SQLite em memória. Inclui os testes existentes de cadastro, edição direta, tentativa de transferência de propriedade, pagamento, PDFs e geração/download de XLSX.
- Os nove novos testes cobrem autorização pelo Gate com proprietário, outro usuário e visitante; exclusão individual por ID adulterado; seleção mista na exclusão em lote; exclusão pela página de edição; indicadores com datas de cadastro divergentes, primeiro e último dia do mês, datas anteriores/posteriores, ausência de data e isolamento; indicadores sem sessão; feedback de credenciais inválidas; redirecionamento de visitantes nas páginas financeiras.
- Pint: **passou nos oito arquivos PHP alterados**. A primeira execução apontou imports/espaçamento no login e indentação no widget; foram corrigidos apenas nesses arquivos e a verificação foi repetida com sucesso.
- `git diff --check`: **passou**.
- Sem alteração em Blade, CSS ou JavaScript; o build não foi repetido nesta etapa.

As exclusões dos testes ocorreram somente no banco SQLite em memória; nenhum registro persistente foi excluído. Sem migrations, atualização de dependências, alterações de configuração de produção ou deploy. Permanecem pendentes a revisão visual das telas no navegador, o uso de workers externos para XLSX, a avaliação da regra Gmail e a avaliação de suporte das dependências. Os indicadores seguem como contagens. As alterações locais preexistentes em assets gerados do Filament e no `.gitignore` ficaram fora desta entrega.

## Etapa 8 — painel financeiro com gráficos e pendências

O painel passou a destacar valores financeiros e prazos, com contagens como informação auxiliar. A página `App\Filament\Pages\Dashboard` substitui o dashboard padrão na mesma rota `/admin` e usa o formulário de filtros nativo do Filament.

Comportamento:

- Seis cartões: valor das contas vencidas, vencimentos dos próximos sete dias, contas pagas no mês, recebimentos, pagamentos e saldo dos lançamentos.
- O seletor mensal oferece os últimos 36 meses. Pagamentos de contas e fornecedores usam a data de pagamento; recebimentos, pagamentos e evolução usam a data de lançamento. O saldo considera somente os lançamentos, sem somar contas e movimentações entre si.
- Vencidas são contas com status `2` e vencimento anterior a hoje. Próximos sete dias incluem hoje e os seis dias seguintes. As duas urgências e a lista de atenção não mudam ao selecionar outro mês; isso é explicado no filtro do painel.
- Evolução em colunas agrupadas de recebimentos e pagamentos nos seis meses até o período selecionado, incluindo meses vazios. Ranking horizontal dos cinco maiores totais de contas pagas por fornecedor.
- Lista paginada de pendências, com filtros de prazo e link para abrir a conta. A consulta restringe os registros ao usuário autenticado, e a página de destino mantém o filtro de propriedade e as policies existentes.
- `PainelFinanceiro` centraliza as consultas e agrega os valores com BCMath, percorrendo cursores e preservando strings decimais. A normalização suporta os formatos monetários já aceitos pela aplicação. Não há SQL `SUM` sobre strings nem conversão de valores financeiros para float no PHP. Os datasets dos gráficos enviam strings; os tooltips formatam essas strings diretamente em BRL. A geometria das barras e os marcadores de escala usam a representação numérica do Chart.js, apenas para visualização.
- Gráficos sem registros exibem uma mensagem clara. O canvas permanece montado quando oculto, preservando os listeners ao alternar períodos. O layout usa uma coluna no celular e dois gráficos lado a lado em telas largas; a lista compacta os campos no celular.

Verificações executadas a partir da raiz:

```bash
docker compose -f compose.portfolio.yaml exec --user app app ./vendor/bin/phpunit --configuration=phpunit.portfolio.xml
docker compose -f compose.portfolio.yaml exec --user app app ./vendor/bin/phpunit --configuration=phpunit.portfolio.xml --filter='DashboardFinanceiroTest|IndicadoresFinanceirosTest'
docker compose -f compose.portfolio.yaml exec --user app app ./vendor/bin/pint --test app/Support/PainelFinanceiro.php app/Filament/Pages/Dashboard.php app/Filament/Widgets/StatsOverview.php app/Filament/Widgets/GraficoFinanceiro.php app/Filament/Widgets/MovimentacaoMensal.php app/Filament/Widgets/PagamentosPorFornecedor.php app/Filament/Widgets/ContasAtencao.php app/Providers/Filament/AdminPanelProvider.php tests/Feature/IndicadoresFinanceirosTest.php tests/Feature/DashboardFinanceiroTest.php
docker compose -f compose.yaml run --rm --no-deps --volume sysfinan-portfolio_vendor:/var/www/html/vendor:ro node npm run build
git diff --check
```

- Suíte principal: **63 testes, 814 asserções; passou** com SQLite em memória.
- Após estabilizar o template dos gráficos: **11 testes do painel, 100 asserções; passou**. Cobrem datas financeiras e limites, valores altos e centavos, saldo negativo, separação entre contas e lançamentos, dois usuários, ausência de sessão, entradas de período adulteradas, formatos monetários legados, payload decimal dos gráficos, virada do ano, meses vazios, agrupamento e ordem exata dos fornecedores, filtros da lista e autorização da página de destino.
- Pint: **passou nos dez arquivos PHP da entrega**, após corrigir o espaçamento dos tipos union apontado na primeira execução.
- Build Vite: **passou**, inclusive após a versão final do template Blade. Permanece o aviso de Browserslist desatualizado; nenhuma dependência da aplicação foi instalada ou atualizada.
- Navegador: Playwright/Chromium instalados somente em `/tmp`, sem alterar manifestos ou lockfiles do projeto. O roteiro `/tmp/sysfinan-dashboard-review.cjs` passou em **1440 px e 390 px**: login local, gráficos com dados, ranking de fornecedores, troca de mês, transição entre dados e estado vazio e retorno ao mês atual. Conferiu que os tooltips preservam `90.071.992.547.409,93` e valores negativos, sem erros JavaScript e sem transbordamento horizontal da página no celular.
- A revisão no navegador encontrou uma falha ao desmontar o gráfico na transição para o estado vazio. O template foi ajustado para manter o componente e o canvas estáveis; o fluxo completo foi repetido e passou.
- Capturas em `/tmp/sysfinan-dashboard-desktop.png`, `/tmp/sysfinan-dashboard-mobile.png`, `/tmp/sysfinan-dashboard-fornecedores.png` e `/tmp/sysfinan-dashboard-grafico-mobile.png` foram inspecionadas visualmente e não foram versionadas.
- `git diff --check`: **passou**.

Sem migrations, alterações de dependências ou configuração de produção, integração automática de contas com lançamentos ou deploy. A inspeção do navegador usou somente o ambiente isolado e os dados fictícios existentes; não criou, editou nem excluiu registros financeiros persistentes. Permanecem pendentes workers externos dos XLSX, inspeção das planilhas em um aplicativo de escritório, revisão visual dos demais fluxos e avaliação de suporte das dependências/regra Gmail. Dados históricos fora dos formatos monetários suportados continuam exigindo avaliação específica. Assets gerados do Filament e `.gitignore` preexistentes ficaram fora da entrega.
