<?php

namespace Tests\Feature;

use App\Filament\Resources\ContaResource\Pages\ListContas;
use App\Filament\Resources\LancamentoResource\Pages\ListLancamentos;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\User;
use Database\Seeders\PortfolioDemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceiroTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Execute com phpunit.portfolio.xml: os testes exigem SQLite em memória.');
        }

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->seed(PortfolioDemoSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::firstOrFail());
    }

    public function test_filtro_monetario_preserva_centavos_zero_e_isolamento(): void
    {
        $own = Conta::where('user_id', auth()->id())->get();
        $other = Conta::where('user_id', '!=', auth()->id())->firstOrFail();

        foreach (['123,45' => '123.45', '1.234,56' => '1234.56', '0,10' => '0.10', '0,00' => '0.00', '123.45' => '123.45', '12,00' => '12', '12,10' => '12.1', '12,0' => '12.0'] as $input => $value) {
            $own->first()->update(['valor' => $value]);
            $own->last()->update(['valor' => '999.99']);
            $other->update(['valor' => $value]);
            Livewire::test(ListContas::class)
                ->filterTable('Valor', ['valor' => $input])
                ->assertCanSeeTableRecords([$own->first()])
                ->assertCanNotSeeTableRecords([$own->last(), $other]);
        }
    }

    public function test_filtro_vazio_nao_restringe_e_entrada_invalida_nao_retorna_contas(): void
    {
        $own = Conta::where('user_id', auth()->id())->get();
        Livewire::test(ListContas::class)->filterTable('Valor', ['valor' => ''])->assertCanSeeTableRecords($own);
        Livewire::test(ListContas::class)->filterTable('Valor', ['valor' => 'abc'])->assertCanNotSeeTableRecords($own);
    }

    public function test_reversao_preserva_codigo_e_limpa_data_de_pagamento(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(12, 0));
        $conta = Conta::where('user_id', auth()->id())->where('status', '1')->firstOrFail();
        $page = Livewire::test(ListContas::class);
        $page->call('updateTableColumnState', 'status', (string) $conta->id, false);
        $this->assertSame('2', $conta->fresh()->status);
        $this->assertNull($conta->fresh()->dataPagamento);
        $page->call('updateTableColumnState', 'status', (string) $conta->id, true);
        $this->assertSame('1', $conta->fresh()->status);
        $this->assertSame(now()->toDateString(), $conta->fresh()->dataPagamento);
    }

    public function test_repetir_estado_pago_preserva_a_data_original(): void
    {
        $conta = Conta::where('user_id', auth()->id())->where('status', '1')->firstOrFail();
        $original = $conta->dataPagamento;
        Livewire::test(ListContas::class)->call('updateTableColumnState', 'status', (string) $conta->id, true);
        $this->assertSame('1', $conta->fresh()->status);
        $this->assertSame($original, $conta->fresh()->dataPagamento);
    }

    public function test_toggle_nao_altera_conta_de_outro_usuario(): void
    {
        $conta = Conta::where('user_id', '!=', auth()->id())->where('status', '1')->firstOrFail();
        $original = $conta->dataPagamento;
        Livewire::test(ListContas::class)->call('updateTableColumnState', 'status', (string) $conta->id, false);
        $this->assertSame('1', $conta->fresh()->status);
        $this->assertSame($original, $conta->fresh()->dataPagamento);
    }

    public function test_total_preserva_centavos_e_formata_brl_sem_perder_precisao(): void
    {
        $record = Lancamento::where('user_id', auth()->id())->firstOrFail();
        $record->update(['recebimento' => '90071992547409.93', 'pagamento' => '0.01']);
        $page = Livewire::test(ListLancamentos::class);
        $column = $page->instance()->getTable()->getColumn('Total')->record($record->fresh());
        $this->assertSame('90071992547409.92', $column->getState());
        $this->assertSame('R$ 90.071.992.547.409,92', $column->formatState($column->getState()));

        $record->update(['recebimento' => '0.10', 'pagamento' => '0.20']);
        $column = Livewire::test(ListLancamentos::class)->instance()->getTable()->getColumn('Total')->record($record->fresh());
        $this->assertSame('-0.10', $column->getState());
        $this->assertSame('-R$ 0,10', $column->formatState($column->getState()));
    }
}
