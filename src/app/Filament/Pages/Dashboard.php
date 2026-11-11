<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ContasAtencao;
use App\Filament\Widgets\MovimentacaoMensal;
use App\Filament\Widgets\PagamentosPorFornecedor;
use App\Filament\Widgets\StatsOverview;
use App\Support\PainelFinanceiro;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Section::make('Visão financeira')
                ->description('Totais e fornecedores seguem o mês escolhido. Contas vencidas e próximos vencimentos mostram a situação de hoje.')
                ->schema([
                    Select::make('periodo')->label('Período')
                        ->options(PainelFinanceiro::periodos())
                        ->default(now()->format('Y-m'))
                        ->selectablePlaceholder(false)
                        ->rules(['in:'.implode(',', array_keys(PainelFinanceiro::periodos()))]),
                ])->columns(3),
        ]);
    }

    public function getWidgets(): array
    {
        return [StatsOverview::class, MovimentacaoMensal::class, PagamentosPorFornecedor::class, ContasAtencao::class];
    }

    public function getColumns(): int|string|array
    {
        return ['default' => 1, 'xl' => 2];
    }
}
