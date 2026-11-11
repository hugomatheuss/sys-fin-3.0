<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de contas</title>
    @include('pdf.estilos')
</head>
<body>
    <div class="cabecalho">
        <p class="marca">SysFinan</p>
        <h1>Relatório de contas</h1>
        <p class="resumo">Registros selecionados: {{ $contas->count() }}</p>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width: 16%">Fornecedor</th>
                <th style="width: 18%">Descrição</th>
                <th style="width: 9%">Documento</th>
                <th style="width: 17%" class="valor">Valor</th>
                <th style="width: 12%">Tipo</th>
                <th style="width: 8%">Situação</th>
                <th style="width: 10%">Vencimento</th>
                <th style="width: 10%">Pagamento</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($contas as $conta)
                <tr>
                    <td>{{ $conta->fornecedor }}</td>
                    <td>{{ $conta->descricao }}</td>
                    <td>{{ $conta->numeroDocumento }}</td>
                    <td class="valor">{{ \App\Support\ValorMonetario::formatar($conta->valor) }}</td>
                    <td>{{ $conta->tipo === '1' ? 'Operacional' : 'Não Operacional' }}</td>
                    <td>{{ $conta->status === '1' ? 'Pago' : 'Não pago' }}</td>
                    <td class="data">{{ filled($conta->dataVencimento) ? \Illuminate\Support\Carbon::parse($conta->dataVencimento)->format('d/m/Y') : '—' }}</td>
                    <td class="data">{{ filled($conta->dataPagamento) ? \Illuminate\Support\Carbon::parse($conta->dataPagamento)->format('d/m/Y') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="vazio">Nenhuma conta selecionada.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
