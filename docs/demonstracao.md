# Demonstração local do SysFinan

## Ambiente independente

Use somente `compose.portfolio.yaml` nesta branch. O projeto Docker se chama `sysfinan-portfolio`; banco, dependências, cache e arquivos locais usam volumes próprios. O PostgreSQL não publica porta no host. A aplicação e o Mailpit publicam apenas em `127.0.0.1`, nas portas 8081 e 8026. Não altere o nome do projeto com `-p` ou combine este arquivo com outro Compose.

Pré-requisitos: Docker Engine com Compose v2, portas 8081 e 8026 disponíveis e acesso à internet para baixar imagens e as dependências fixadas no lockfile. Não é necessário instalar PHP ou Node no host.

## Preparação

Na raiz, confirme a branch:

```bash
git branch --show-current
```

Em `src/`, crie o arquivo exclusivo sem sobrescrever um arquivo existente:

```bash
cp -n .env.portfolio.example .env.portfolio
```

Preencha `APP_KEY` com `base64:` seguido de uma chave de 32 bytes em base64 e `PORTFOLIO_DEMO_PASSWORD` com uma senha local de ao menos 12 caracteres. Para gerar os valores, use um gerenciador de senhas; se houver PHP no host, os comandos abaixo geram cada valor:

```bash
php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
php -r 'echo bin2hex(random_bytes(12)).PHP_EOL;'
```

Sem PHP no host, execute a partir da raiz:

```bash
docker compose -f compose.portfolio.yaml build app
docker compose -f compose.portfolio.yaml run --rm --no-deps --entrypoint php app -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
docker compose -f compose.portfolio.yaml run --rm --no-deps --entrypoint php app -r 'echo bin2hex(random_bytes(12)).PHP_EOL;'
```

O ambiente `portfolio` faz o Laravel carregar `.env.portfolio`, sem reutilizar `.env`. O arquivo `.env.portfolio` é ignorado pelo Git. Não copie `.env`, chaves, dumps ou credenciais de produção. As credenciais do PostgreSQL no Compose são exclusivas deste banco local fictício.

Na raiz:

```bash
docker compose -f compose.portfolio.yaml up -d --build
docker compose -f compose.portfolio.yaml logs --tail=30 app
```

Aguarde o Composer terminar e confira se a tela `/admin/login` responde. Os logs do PHP-FPM ficam em `/tmp/portfolio-fpm-error.log` dentro do contêiner. A instalação usa `--no-scripts` para evitar a republicação automática dos assets Filament versionados; somente a descoberta de pacotes é executada. As versões não são atualizadas.

Crie as tabelas no banco novo e carregue os dados fictícios:

```bash
docker compose -f compose.portfolio.yaml exec --user app app php artisan migrate --force
docker compose -f compose.portfolio.yaml exec --user app app php artisan db:seed --class=PortfolioDemoSeeder --force
```

O seeder não é chamado por `DatabaseSeeder`. Exige ambiente `portfolio`, habilitação explícita, driver PostgreSQL, host `portfolio-postgres` e banco `sysfinan_portfolio`. Em testes, aceita somente SQLite em memória. Recusa produção antes de gravar dados.

São criados dois usuários, seis contas e três lançamentos por usuário. As datas são ancoradas no cadastro de cada usuário fictício e incluem vencimentos anteriores e posteriores a essa data. Reexecutar o seeder não apaga nem redefine registros existentes; a senha já cadastrada também é preservada.

## Acesso e roteiro

- Painel: http://localhost:8081/admin
- Caixa de e-mails local: http://localhost:8026
- CNPJ fictício A: `00000000000000`.
- CNPJ fictício B: `00000000000001`.
- Senha: valor local escolhido em `PORTFOLIO_DEMO_PASSWORD`.

Os CNPJs são identificadores fictícios, sem validade cadastral. Os e-mails de demonstração usam Gmail apenas para atender à restrição atual do painel; o transporte de e-mail está direcionado ao Mailpit.

1. Entre com A e confira as contas pagas, pendentes e vencidas.
2. Cadastre uma conta com documento `APRESENTACAO-01`, fornecedor fictício, valor `123,45`, tipo operacional, situação não paga e vencimento futuro.
3. Consulte o histórico e filtre pelo fornecedor. Compare o valor exibido com o cadastrado.
4. Marque a conta como paga, recarregue e confira status e data. Edite o registro para conferir persistência.
5. Gere XLSX de contas pagas e não pagas, conferindo status, valores e filtros usados.
6. Selecione contas e gere PDF. Confira o conteúdo e a formatação.
7. Guarde a URL de edição de uma conta de A, saia e entre como B. A URL deve negar acesso. Repita com lançamentos e tente as ações disponíveis apenas sobre registros de B.
8. Confira o relatório de lançamentos. Confira o PDF de lançamentos: os valores, os tipos e as datas devem corresponder aos registros selecionados.

Use somente o painel como demonstração nesta etapa; `/` ainda exibe a página padrão. Não grave vídeo antes de conferir os resultados do roteiro.

## Testes isolados

```bash
docker compose -f compose.portfolio.yaml exec --user app app ./vendor/bin/phpunit --configuration=phpunit.portfolio.xml
docker compose -f compose.portfolio.yaml exec --user app app ./vendor/bin/pint --test config/portfolio.php database/seeders/PortfolioDemoSeeder.php tests/Feature/PortfolioDemoTest.php tests/Feature/PdfExportTest.php app/Filament/Resources/ContaResource.php app/Filament/Resources/LancamentoResource.php
```

A configuração de testes força SQLite em memória, ambiente de testes e chave fictícia. As migrations são executadas nessa memória, sem `migrate:fresh`, truncamentos ou acesso ao banco persistente. Os testes cobrem repetição do seeder, bloqueio de produção, login por CNPJ, cadastro com valor exato, pagamento, consultas e edição direta entre dois usuários e filtros dos exportadores de contas. Os testes de PDF adicionais verificam o download, os dados enviados ao template e a rejeição de IDs de outro usuário na seleção; há também um teste de geração de PDF real. Não comprovam o fluxo completo de XLSX nem a resistência à adulteração de formulários.

## Limitações para a próxima etapa

- O bloqueio de conteúdo do PDF de lançamentos foi corrigido: as ações agora usam `loadView` e recebem somente os registros selecionados do usuário autenticado. A apresentação visual dos PDFs e a formatação monetária ainda precisam de revisão; esta correção preserva as strings monetárias existentes, sem converter para float.
- O teste de pagamento passou para a transição de pendente para pago; a reversão para pendente e os demais caminhos de edição precisam de cobertura adicional.
- O filtro por valor usa float e multiplicação por dez; o total de lançamentos faz subtração implícita de strings monetárias.
- O acesso exige e-mail Gmail. Os formulários não declaram várias validações exigidas pelo banco.
- `Model::unguard()` global e `user_id` oculto exigem revisão de autorização e mass assignment. A criação atribui o usuário no servidor; edição e ações precisam de testes adicionais de adulteração.
- Exportadores recebem consultas filtradas da tabela; a fila e a autorização do download ainda precisam de validação completa.
- Indicadores mensais usam a data de cadastro, em vez da data financeira; a apresentação atual mostra contagens.
- Laravel 10 e as dependências existentes precisam de avaliação de suporte e atualização antes de publicar uma demonstração na internet.

## Encerramento

```bash
docker compose -f compose.portfolio.yaml stop
```

O comando mantém os dados fictícios para a próxima apresentação. Esta etapa não inclui publicação, push ou deploy. O serviço de deploy de produção deve ser conferido antes de publicar qualquer branch remota.
