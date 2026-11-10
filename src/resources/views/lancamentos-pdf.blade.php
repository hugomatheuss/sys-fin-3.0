<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de lançamentos</title>
</head>
<body>
    <h1>Relatório de lançamentos</h1>
    <table>
        <thead>
            <tr>
                <th>Recebimento</th>
                <th>Pagamento</th>
                <th>Tipo de recebimento</th>
                <th>Tipo de pagamento</th>
                <th>Data do lançamento</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lancamentos as $lancamento)
                <tr>
                    <td>{{ $lancamento->recebimento }}</td>
                    <td>{{ $lancamento->pagamento }}</td>
                    <td>{{ $lancamento->tipoRecebimento === '1' ? 'Dinheiro' : 'Bancário' }}</td>
                    <td>{{ $lancamento->tipoPagamento === '1' ? 'Mercadorias' : 'Outros' }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($lancamento->dataLancamento)->format('d/m/Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
