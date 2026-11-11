<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de lançamentos</title>
    @include('pdf.estilos')
</head>
<body>
    <div class="cabecalho">
        <p class="marca">SysFinan</p>
        <h1>Relatório de lançamentos</h1>
        <p class="resumo">Registros selecionados: {{ $lancamentos->count() }}</p>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width: 18%" class="valor">Recebimento</th>
                <th style="width: 18%" class="valor">Pagamento</th>
                <th style="width: 18%" class="valor">Total</th>
                <th style="width: 16%">Tipo de recebimento</th>
                <th style="width: 16%">Tipo de pagamento</th>
                <th style="width: 14%">Data do lançamento</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lancamentos as $lancamento)
                <tr>
                    <td class="valor">{{ \App\Support\ValorMonetario::formatar($lancamento->recebimento) }}</td>
                    <td class="valor">{{ \App\Support\ValorMonetario::formatar($lancamento->pagamento) }}</td>
                    <td class="valor">{{ \App\Support\ValorMonetario::formatar(bcsub($lancamento->recebimento, $lancamento->pagamento, 2)) }}</td>
                    <td>{{ $lancamento->tipoRecebimento === '1' ? 'Dinheiro' : 'Bancário' }}</td>
                    <td>{{ $lancamento->tipoPagamento === '1' ? 'Mercadorias' : 'Outros' }}</td>
                    <td class="data">{{ filled($lancamento->dataLancamento) ? \Illuminate\Support\Carbon::parse($lancamento->dataLancamento)->format('d/m/Y') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="vazio">Nenhum lançamento selecionado.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
