<?php

namespace App\Filament\Exports;

use App\Models\Lancamento;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Carbon;

class LancamentoExporter extends ExportadorFinanceiro
{
    protected static ?string $model = Lancamento::class;

    private const DINHEIRO = '1';

    private const BANCARIO = '0';

    private const MERCADORIAS = '1';

    private const OUTROS = '0';

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('recebimento')
                ->label('Recebimento'),
            ExportColumn::make('pagamento')
                ->label('Pagamento'),
            ExportColumn::make('tipoRecebimento')
                ->label('Tipo de Recebimento')
                ->getStateUsing(fn (Lancamento $lancamento): string => $lancamento->tipoRecebimento === self::DINHEIRO
                    ? 'Dinheiro'
                    : 'Bancário'
                ),
            ExportColumn::make('tipoPagamento')
                ->label('Tipo de Pagamento')
                ->getStateUsing(fn (Lancamento $lancamento): string => $lancamento->tipoPagamento === self::MERCADORIAS
                    ? 'Mercadorias'
                    : 'Outros'
                ),
            ExportColumn::make('dataLancamento')
                ->label('Data do Lançamento')
                ->getStateUsing(fn (Lancamento $lancamento): string => filled($lancamento->dataLancamento) ? Carbon::parse($lancamento->dataLancamento)->format('d/m/Y') : ''),
            ExportColumn::make('created_at')
                ->label('Data do Cadastro')
                ->getStateUsing(fn (Lancamento $lancamento): string => filled($lancamento->created_at) ? $lancamento->created_at->format('d/m/Y') : ''),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Seu relatório de todos os lançamentos está finalizado e tem '.number_format($export->successful_rows).' '.str('linha')->plural($export->successful_rows).' exportadas.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao serem exportadas.';
        }

        return $body;
    }
}
