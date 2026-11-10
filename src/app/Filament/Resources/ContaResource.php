<?php

namespace App\Filament\Resources;

use App\Filament\Exports\ContasNaoPagasExporter;
use App\Filament\Exports\ContasPagasExporter;
use App\Filament\Resources\ContaResource\Pages;
use App\Models\Conta;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Leandrocfe\FilamentPtbrFormFields\Money;

class ContaResource extends Resource
{
    protected static ?string $model = Conta::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $modelLabel = 'Contas';

    protected static ?string $navigationGroup = 'Estabelecimento';

    private const OPERACIONAL = '1';

    private const NAO_OPERACIONAL = '2';

    private const PAGO = '1';

    private const NAO_PAGO = '2';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('fornecedor')->label('Fornecedor'),
                TextInput::make('numeroDocumento')->label('Número do Documento'),
                Money::make('valor')->label('Valor'),
                TextInput::make('descricao')->label('Descrição'),
                Radio::make('tipo')
                    ->label('Tipo')
                    ->options([
                        '1' => 'Operacional',
                        '2' => 'Não Operacional',
                    ]),
                Radio::make('status')
                    ->label('Status')
                    ->options([
                        '1' => 'Pago',
                        '2' => 'Não pago',
                    ]),
                DatePicker::make('dataPagamento')->label('Data do Pagamento'),
                DatePicker::make('dataVencimento')->label('Data do Vencimento'),
                Hidden::make('user_id'),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->select(['contas.*'])
            ->where('user_id', auth()->id())
            ->orderBy('dataVencimento', 'desc');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->filtersTriggerAction(fn (Tables\Actions\Action $action) => $action->icon('heroicon-o-adjustments-vertical')
            )
            ->filtersFormColumns(2)
            ->columns([
                TextColumn::make('fornecedor')
                    ->label('Fornecedor')
                    ->searchable()
                    ->visibleFrom('md'),
                TextColumn::make('numeroDocumento')
                    ->label('Número do Documento')
                    ->visibleFrom('md'),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->money('BRL', 0, 'pt_BR'),
                TextColumn::make('descricao')
                    ->label('Descrição')
                    ->visibleFrom('md'),
                TextColumn::make('tipo')
                    ->getStateUsing(fn (Conta $conta): string => $conta->tipo === self::OPERACIONAL
                        ? 'Operacional'
                        : 'Não Operacional'
                    )
                    ->label('Tipo')
                    ->searchable()
                    ->visibleFrom('md'),
                ToggleColumn::make('status')
                    ->beforeStateUpdated(fn (Conta $conta) => $conta->status !== self::PAGO
                        ? $conta->update(['dataPagamento' => date('Y-m-d')])
                        : $conta->update(['dataPagamento' => null])
                    )
                    ->getStateUsing(fn (Conta $conta): string => $conta->status === self::OPERACIONAL)
                    ->onColor('success')
                    ->offColor('danger')
                    ->label('Status'),
                TextColumn::make('dataPagamento')
                    ->label('Data de Pagamento')
                    ->date('d-m-Y')
                    ->visibleFrom('md'),
                TextColumn::make('dataVencimento')
                    ->label('Data de Vencimento')
                    ->date('d-m-Y'),
            ])
            ->filters([
                Filter::make('fornecedor')
                    ->form([
                        TextInput::make('fornecedor')->label('Fornecedor'),
                    ])
                    ->query(function (Builder $query, $data) {
                        return $query
                            ->when(
                                $data['fornecedor'],
                                fn (Builder $query, $value): Builder => $query->where('fornecedor', 'LIKE', "%{$data['fornecedor']}%")
                            );
                    }),
                Filter::make('Valor')
                    ->form([
                        Money::make('valor')->label('Valor'),
                    ])
                    ->query(function (Builder $query, $data): Builder {
                        $valorFloat = (float) str_replace(',', '.', $data['valor']);

                        return $query
                            ->when(
                                $valorFloat > 0,
                                fn (Builder $query, $valor): Builder => $query
                                    ->where(
                                        'valor',
                                        '=',
                                        number_format($valorFloat * 10, 2, '.', '')
                                    )
                            );
                    }),
                Filter::make('Contas Pagas')
                    ->query(fn (Builder $query): Builder => $query->where('status', self::PAGO)),
                Filter::make('Contas Não Pagas')
                    ->query(fn (Builder $query): Builder => $query->where('status', self::NAO_PAGO)),
                Filter::make('Operacional')
                    ->query(fn (Builder $query): Builder => $query->where('tipo', self::OPERACIONAL)),
                Filter::make('Não Operacional')
                    ->query(fn (Builder $query): Builder => $query->where('tipo', self::NAO_OPERACIONAL)),
                Filter::make('dataIntervalo')
                    ->form([
                        DatePicker::make('dataVencimento')->label('Data inicial:'),
                    ])
                    ->query(function (Builder $query, $data) {
                        return $query
                            ->when(
                                $data['dataVencimento'],
                                fn (Builder $query, $date): Builder => $query->whereDate('dataVencimento', '>=', $date)
                            );
                    }),
                Filter::make('dataIntervalo2')
                    ->form([
                        DatePicker::make('dataVencimento')->label('Data Final:'),
                    ])
                    ->query(function (Builder $query, $data) {
                        return $query
                            ->when(
                                $data['dataVencimento'],
                                fn (Builder $query, $date): Builder => $query->whereDate('dataVencimento', '<=', $date)
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->hiddenLabel(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(function (Tables\Actions\Action $action) {
                        $action->modalDescription('Tem certeza que deseja excluir esta conta?');
                        $action->modalHeading('Excluir Conta');

                        return $action;
                    })
                    ->hiddenLabel(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('Pdf')
                        ->icon('heroicon-m-arrow-down-tray')
                        ->openUrlInNewTab()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $contas = $records->where('user_id', auth()->id());

                            return response()->streamDownload(function () use ($contas) {
                                echo Pdf::loadView('contas-pdf', ['contas' => $contas])->output();
                            }, 'contas.pdf');
                        }),
                ]),
            ])->headerActions([
                ExportAction::make('relatorioContasNaoPagas')
                    ->exporter(ContasNaoPagasExporter::class)
                    ->label('Relatório Contas Não Pagas')
                    ->formats([
                        ExportFormat::Xlsx,
                    ])
                    ->fileName(fn (): string => 'contas_nao_pagas'),
                ExportAction::make('relatorioContasPagas')
                    ->exporter(ContasPagasExporter::class)
                    ->label('Relatório Contas Pagas')
                    ->formats([
                        ExportFormat::Xlsx,
                    ])
                    ->fileName(fn (): string => 'contas_pagas'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContas::route('/'),
            'create' => Pages\CreateConta::route('/create'),
            'edit' => Pages\EditConta::route('/{record}/edit'),
        ];
    }
}
