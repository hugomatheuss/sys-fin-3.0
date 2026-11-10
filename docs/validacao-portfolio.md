# Validação da preparação do portfólio

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
