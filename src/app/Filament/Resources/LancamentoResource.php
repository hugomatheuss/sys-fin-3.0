<?php

namespace App\Filament\Resources;

use App\Filament\Exports\LancamentoExporter;
use App\Filament\Resources\LancamentoResource\Pages;
use App\Models\Lancamento;
use App\Support\ValorMonetario;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Leandrocfe\FilamentPtbrFormFields\Money;

class LancamentoResource extends Resource
{
    protected static ?string $model = Lancamento::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $modelLabel = 'Lançamentos';

    protected static ?string $navigationGroup = 'Estabelecimento';

    private const DINHEIRO = '1';

    private const BANCARIO = '0';

    private const MERCADORIAS = '1';

    private const OUTROS = '0';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Money::make('recebimento')->label('Recebimento'),
                Money::make('pagamento')->label('Pagamento'),
                Radio::make('tipoRecebimento')->label('Tipo de Recebimento')
                    ->options([
                        '1' => 'Dinheiro',
                        '0' => 'Bancário',
                    ]),
                Radio::make('tipoPagamento')->label('Tipo de Pagamento')
                    ->options([
                        '1' => 'Mercadorias',
                        '0' => 'Outros',
                    ]),
                DatePicker::make('dataLancamento')->label('Data de Lançamento'),
                //FileUpload::make('attachment')->label('Comprovante')->preserveFilenames()->deletable(),
                Hidden::make('user_id'),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->select(['lancamentos.*'])
            ->where('user_id', auth()->id())
            ->orderBy('dataLancamento', 'desc');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->filtersTriggerAction(fn (Tables\Actions\Action $action) => $action->icon('heroicon-o-adjustments-vertical')
            )
            ->columns([
                TextColumn::make('recebimento')
                    ->label('Recebimento')
                    ->money('BRL', 0, 'pt_BR')
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('pagamento')
                    ->label('Pagamento')
                    ->money('BRL', 0, 'pt_BR')
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('tipoRecebimento')
                    ->getStateUsing(fn (Lancamento $lancamento): string => $lancamento->tipoRecebimento === self::DINHEIRO
                        ? 'Dinheiro'
                        : 'Bancário'
                    )
                    ->label('Tipo de Recebimento')
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('tipoPagamento')
                    ->getStateUsing(fn (Lancamento $lancamento): string => $lancamento->tipoPagamento === self::MERCADORIAS
                        ? 'Mercadorias'
                        : 'Outros'
                    )
                    ->label('Tipo de Pagamento')
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('dataLancamento')
                    ->label('Data de Lançamento')
                    ->sortable()
                    ->date('d-m-Y'),
                TextColumn::make('Total')
                    ->getStateUsing(fn (Lancamento $lancamento): string => bcsub($lancamento->recebimento, $lancamento->pagamento, 2))
                    ->formatStateUsing(fn (string $state): string => ValorMonetario::formatar($state))
                    ->sortable(),
            ])
            ->filters([
                Filter::make('Dinheiro')
                    ->query(fn (Builder $query): Builder => $query->where('tipoRecebimento', self::DINHEIRO)),
                Filter::make('Bancário')
                    ->query(fn (Builder $query): Builder => $query->where('tipoRecebimento', self::BANCARIO)),
                Filter::make('Mercadoria')
                    ->query(fn (Builder $query): Builder => $query->where('tipoPagamento', self::MERCADORIAS)),
                Filter::make('Outros')
                    ->query(fn (Builder $query): Builder => $query->where('tipoPagamento', self::OUTROS)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->hiddenLabel(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(function (Tables\Actions\Action $action) {
                        $action->modalDescription('Tem certeza que deseja excluir este lançamento?');
                        $action->modalHeading('Excluir Lançamento');

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
                            $lancamentos = $records->where('user_id', auth()->id());

                            return response()->streamDownload(function () use ($lancamentos) {
                                echo Pdf::loadView('lancamentos-pdf', ['lancamentos' => $lancamentos])->output();
                            }, 'lancamentos.pdf');
                        }),
                ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(LancamentoExporter::class)
                    ->label('Relatório de Lançamentos')
                    ->formats([
                        ExportFormat::Xlsx,
                    ])
                    ->fileName(fn (): string => 'lancamentos'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLancamentos::route('/'),
            'create' => Pages\CreateLancamento::route('/create'),
            'edit' => Pages\EditLancamento::route('/{record}/edit'),
        ];
    }
}
