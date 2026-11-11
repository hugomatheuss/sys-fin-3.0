<?php

namespace Tests\Feature;

use App\Filament\Resources\ContaResource\Pages\EditConta;
use App\Filament\Resources\ContaResource\Pages\ListContas;
use App\Filament\Resources\LancamentoResource\Pages\EditLancamento;
use App\Filament\Resources\LancamentoResource\Pages\ListLancamentos;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\User;
use Database\Seeders\PortfolioDemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class AutorizacaoFinanceiraTest extends TestCase
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
    }

    public function test_policies_autorizam_apenas_o_proprietario_dos_registros(): void
    {
        foreach (User::all() as $user) {
            foreach ([Conta::class, Lancamento::class] as $model) {
                $own = $model::where('user_id', $user->id)->firstOrFail();
                $other = $model::where('user_id', '!=', $user->id)->firstOrFail();
                $gate = Gate::forUser($user);
                foreach (['viewAny', 'create', 'deleteAny'] as $ability) {
                    $this->assertTrue($gate->allows($ability, $model));
                    $this->assertFalse(Gate::forUser(null)->allows($ability, $model));
                }
                foreach (['view', 'update', 'delete'] as $ability) {
                    $this->assertTrue($gate->allows($ability, $own));
                    $this->assertFalse($gate->allows($ability, $other));
                    $this->assertFalse(Gate::forUser(null)->allows($ability, $own));
                }
            }
        }
    }

    public function test_exclusao_na_tabela_nao_aceita_id_de_outro_usuario(): void
    {
        foreach (User::all() as $user) {
            $this->app['session']->flush();
            $this->actingAs($user);
            foreach ([Conta::class => ListContas::class, Lancamento::class => ListLancamentos::class] as $model => $page) {
                $own = $model::where('user_id', $user->id)->firstOrFail();
                $other = $model::where('user_id', '!=', $user->id)->firstOrFail();
                $original = $other->getAttributes();
                Livewire::test($page)
                    ->call('mountTableAction', 'delete', (string) $other->id)
                    ->call('callMountedTableAction');
                $this->assertSame($original, $other->fresh()->getAttributes());
                Livewire::test($page)->callTableAction('delete', $own)->assertHasNoTableActionErrors();
                $this->assertModelMissing($own);
            }
        }
    }

    public function test_exclusao_em_lote_limita_selecao_ao_usuario_autenticado(): void
    {
        foreach (User::all() as $user) {
            $this->app['session']->flush();
            $this->actingAs($user);
            foreach ([Conta::class => ListContas::class, Lancamento::class => ListLancamentos::class] as $model => $page) {
                $own = $model::where('user_id', $user->id)->firstOrFail();
                $other = $model::where('user_id', '!=', $user->id)->firstOrFail();
                $remaining = $model::where('user_id', $user->id)->whereKeyNot($own->id)->get();
                Livewire::test($page)->callTableBulkAction('delete', [$own->id, $other->id])->assertHasNoTableBulkActionErrors();
                $this->assertModelMissing($own);
                $this->assertModelExists($other);
                foreach ($remaining as $record) {
                    $this->assertModelExists($record);
                }
            }
        }
    }

    public function test_exclusao_na_pagina_de_edicao_preserva_outros_registros(): void
    {
        foreach (User::all() as $user) {
            $this->app['session']->flush();
            $this->actingAs($user);
            foreach ([Conta::class => EditConta::class, Lancamento::class => EditLancamento::class] as $model => $page) {
                $own = $model::where('user_id', $user->id)->firstOrFail();
                $other = $model::where('user_id', '!=', $user->id)->firstOrFail();
                Livewire::test($page, ['record' => $own->id])->callAction('delete')->assertHasNoActionErrors();
                $this->assertModelMissing($own);
                $this->assertModelExists($other);
            }
        }
    }
}
