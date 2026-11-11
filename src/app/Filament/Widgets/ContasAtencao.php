<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ContaResource;
use App\Models\Conta;
use App\Support\PainelFinanceiro;
use App\Support\ValorMonetario;
use Carbon\CarbonImmutable;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ContasAtencao extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Contas que precisam de atenção')
            ->description('Pendentes vencidas ou com vencimento de hoje até '.now()->addDays(6)->format('d/m/Y').'.')
            ->query(Conta::query()->where('user_id', auth()->id())->where('status', '2')
                ->where('dataVencimento', '<', now()->addDays(7)->toDateString()))
            ->defaultSort('dataVencimento', 'asc')
            ->columns([
                TextColumn::make('fornecedor')->label('Fornecedor')->wrap(),
                TextColumn::make('valor')->label('Valor')->formatStateUsing(fn (string $state): string => ValorMonetario::formatar($state)),
                TextColumn::make('dataVencimento')->label('Vencimento')->date('d/m/Y'),
                TextColumn::make('prazo')->label('Prazo')->badge()->visibleFrom('md')
                    ->state(function (Conta $record): string {
                        $dias = (int) CarbonImmutable::instance(now())->startOfDay()->diffInDays(CarbonImmutable::parse($record->dataVencimento), false);
                        $unidade = abs($dias) === 1 ? ' dia' : ' dias';

                        return match (true) {
                            $dias < 0 => 'Vencida há '.abs($dias).$unidade,
                            $dias === 0 => 'Vence hoje',
                            default => 'Vence em '.$dias.$unidade,
                        };
                    })
                    ->color(fn (Conta $record): string => $record->dataVencimento < now()->toDateString() ? 'danger' : 'warning'),
            ])
            ->filters([
                SelectFilter::make('prazo')->label('Prazo')->options(['vencidas' => 'Vencidas', 'proximas' => 'Próximos 7 dias'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'vencidas' => $query->whereIn('id', app(PainelFinanceiro::class)->contasVencidas()->select('id')),
                        'proximas' => $query->whereIn('id', app(PainelFinanceiro::class)->proximasContas()->select('id')),
                        default => $query,
                    }),
            ])
            ->actions([
                Action::make('abrir')->label('Abrir conta')->icon('heroicon-o-arrow-top-right-on-square')
                    ->iconButton()->tooltip('Abrir conta')
                    ->authorize(fn (Conta $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->url(fn (Conta $record): string => ContaResource::getUrl('edit', ['record' => $record])),
            ])
            ->paginated([5, 10, 25])->defaultPaginationPageOption(5)
            ->emptyStateHeading('Nenhuma conta precisa de atenção')
            ->emptyStateDescription('Não há contas pendentes para o prazo escolhido.');
    }
}
