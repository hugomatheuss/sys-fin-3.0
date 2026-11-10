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
