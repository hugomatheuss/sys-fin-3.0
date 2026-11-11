# SysFinan

Sistema financeiro desenvolvido sob medida para um proprietário de farmácias, com foco em cadastro de contas, histórico e acompanhamento de pagamentos.

O projeto foi desenvolvido individualmente e, segundo o autor, está em produção há aproximadamente três anos. Esta branch prepara uma demonstração independente para entrevistas, currículo e propostas de trabalho.

## O problema atendido

O cliente precisava de um fluxo específico para cadastrar suas contas, manter um histórico e marcar os pagamentos realizados. O SysFinan foi construído para atender essa rotina. A identidade e os dados financeiros do cliente não fazem parte da demonstração.

## Funcionalidades presentes no código

- Cadastro de contas com fornecedor, documento, valor, tipo, status e datas.
- Consulta e filtros por fornecedor, valor, situação, tipo e vencimento.
- Marcação de pagamento e registro da data correspondente.
- Lançamentos com recebimentos, pagamentos e tipos de movimentação.
- Indicadores, exportação XLSX com armazenamento privado e ações de PDF; limitações conhecidas estão documentadas no roteiro.
- Consultas principais filtradas pelo usuário autenticado.

## Implementação

PHP, Laravel 10, Filament 3, PostgreSQL, Blade, Vite e Tailwind CSS. A aplicação fica em `src/`. O painel utiliza recursos do Filament para formulários, tabelas e ações, além de exportadores e templates Blade para documentos.

## Demonstração local

Siga o [guia de preparação e validação](docs/demonstracao.md). O ambiente exclusivo utiliza `compose.portfolio.yaml`, banco e volumes próprios, dados fictícios e Mailpit. O acesso fica limitado à máquina local.

O `compose.yaml` existente continua sendo a configuração de desenvolvimento geral. O arquivo de portfólio é independente: não combine os dois com múltiplas opções `-f`.

## Materiais do case

- [Contexto, autoria e narrativa para apresentação](docs/case-sysfinan.md).
- [Preparação, roteiro e limitações da demonstração](docs/demonstracao.md).
- [Registro dos checks realizados](docs/validacao-portfolio.md).

## Situação desta etapa

Esta evolução documenta o projeto, prepara sua demonstração e corrige os PDFs, as exportações XLSX, a ordenação monetária, as transições de pagamento e os formulários de cadastro/edição. Os valores são tratados sem float, o proprietário é definido no servidor e policies restringem o acesso aos registros. Os indicadores mensais usam as datas financeiras. Não inclui mudanças de esquema. A página inicial ainda é a página padrão do Laravel; o painel está em `/admin`.

O uso real comprova que houve uma entrega para um cliente. Ganhos de tempo, redução de erros e retorno financeiro não foram medidos e não são alegados neste case. Não há publicação ou deploy automático configurado nesta entrega.
