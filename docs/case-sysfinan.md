# Case: controle financeiro sob medida com o SysFinan

## Contexto e entrega

Um proprietário de farmácias precisava de um sistema que atendesse ao seu fluxo de cadastro de contas, consulta de histórico e acompanhamento de pagamentos. O SysFinan foi desenvolvido sob medida e é utilizado por esse cliente em produção há aproximadamente três anos, conforme relato do desenvolvedor.

O desenvolvimento foi realizado integralmente pelo autor do projeto. A identidade do cliente e seus dados são preservados. Não foi informado como o cliente trabalhava antes: não se presume uma migração de planilhas ou outro sistema.

## Fluxo atendido

O usuário cadastra uma conta, informa fornecedor, valor e vencimento, consulta os registros e acompanha sua situação de pagamento. A aplicação também contém lançamentos financeiros, indicadores e exportadores. A demonstração usa dados fictícios e verifica separadamente cada recurso; a existência do código não significa que todas as ações estejam validadas.

## Decisões observáveis no repositório

- Laravel organiza modelos, migrations, autenticação e infraestrutura da aplicação.
- Filament concentra formulários, listagens, filtros e ações administrativas.
- `Conta` e `Lancamento` possuem vínculo com usuário; as consultas dos recursos e widgets usam o usuário autenticado.
- Exportadores XLSX e templates Blade dão suporte aos documentos.
- Valores monetários são strings e status/tipos são códigos string existentes. A preparação do portfólio preserva esse contrato.

Essas são características verificáveis do código. Motivações históricas para cada escolha não foram registradas e não devem ser inventadas em entrevistas.

## Evidências e limites

A evidência disponível é a entrega individual de uma solução sob medida e seu uso em produção pelo cliente, por aproximadamente três anos. Não há métricas documentadas de disponibilidade, volume de uso ou economia de tempo. O período de produção não equivale a uma garantia de operação ininterrupta ou a três anos de manutenção contínua.

O projeto não deve ser apresentado como ERP farmacêutico, sistema de estoque, solução fiscal ou integração entre filiais. Não há base para alegar essas funcionalidades.

## Texto para currículo

Desenvolvimento individual do SysFinan, sistema financeiro sob medida para um proprietário de farmácias, utilizado em produção há aproximadamente três anos. Implementação de cadastro e histórico de contas, acompanhamento de pagamentos e relatórios com PHP, Laravel, Filament e PostgreSQL.

## Texto para propostas

Desenvolvi o SysFinan sob medida para atender ao fluxo financeiro de um proprietário de farmácias. O sistema permite cadastrar contas, consultar o histórico e acompanhar pagamentos, e é usado pelo cliente em produção há aproximadamente três anos. Posso apresentar uma demonstração com dados fictícios para explicar a solução e as decisões de implementação.

## Roteiro para entrevista

1. Explique a necessidade específica de cadastro, histórico e acompanhamento de contas.
2. Delimite sua autoria: desenvolvimento individual do sistema.
3. Mostre o fluxo principal com dados fictícios, conforme o roteiro de demonstração.
4. Explique como o código organiza autenticação, consultas por usuário e documentos.
5. Reconheça as limitações encontradas e diferencie o comportamento existente das melhorias planejadas.

Capturas e vídeo serão produzidos após validar o fluxo principal. Depoimentos ou identificação do cliente dependem de autorização específica.
