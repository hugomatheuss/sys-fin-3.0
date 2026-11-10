<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Filament\Exports\ContasNaoPagasExporter;
use App\Filament\Exports\ContasPagasExporter;
use App\Filament\Resources\ContaResource;
use App\Filament\Resources\ContaResource\Pages\CreateConta;
use App\Filament\Resources\ContaResource\Pages\ListContas;
use App\Filament\Resources\LancamentoResource;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\User;
use Database\Seeders\PortfolioDemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class PortfolioDemoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Execute com phpunit.portfolio.xml: os testes exigem SQLite em memória.');
        }

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        config(['portfolio.enabled' => true, 'portfolio.password' => 'senha-ficticia-testes']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_dados_ficticios_sao_reproduziveis_e_nao_apagam_alteracoes(): void
    {
        $this->seed(PortfolioDemoSeeder::class);
        $conta = Conta::first();
        $conta->update(['descricao' => 'Alteração feita na demonstração']);
        $this->seed(PortfolioDemoSeeder::class);

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('contas', 12);
        $this->assertDatabaseCount('lancamentos', 6);
        $this->assertSame('Alteração feita na demonstração', $conta->fresh()->descricao);
        $this->assertTrue(Hash::check('senha-ficticia-testes', User::first()->password));
        $this->assertSame(['1', '2'], Conta::distinct()->orderBy('status')->pluck('status')->all());
        $this->assertSame(['0', '1'], Lancamento::distinct()->orderBy('tipoRecebimento')->pluck('tipoRecebimento')->all());
    }

    public function test_seeder_recusa_producao_mesmo_com_demo_habilitada(): void
    {
        $this->app->instance('env', 'production');

        try {
            $this->app->make(PortfolioDemoSeeder::class)->run();
            $this->fail('O seeder deveria recusar produção.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ambiente isolado', $exception->getMessage());
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_seeder_recusa_demo_desabilitada(): void
    {
        config(['portfolio.enabled' => false]);
        $this->expectException(RuntimeException::class);
        $this->seed(PortfolioDemoSeeder::class);
    }

    public function test_seeder_recusa_senha_ausente(): void
    {
        config(['portfolio.password' => null]);
        $this->expectException(RuntimeException::class);
        $this->seed(PortfolioDemoSeeder::class);
    }

    public function test_consultas_e_relatorios_de_contas_preservam_usuario_autenticado(): void
    {
        $this->seed(PortfolioDemoSeeder::class);

        foreach (User::all() as $user) {
            $this->app['session']->flush();
            $this->actingAs($user);
            $this->assertSame([$user->id], ContaResource::getEloquentQuery()->get()->pluck('user_id')->unique()->all());
            $this->assertSame([$user->id], LancamentoResource::getEloquentQuery()->get()->pluck('user_id')->unique()->all());

            foreach ([ContasPagasExporter::class => '1', ContasNaoPagasExporter::class => '2'] as $exporter => $status) {
                $records = $exporter::modifyQuery(ContaResource::getEloquentQuery())->get();
                $this->assertNotEmpty($records);
                $this->assertSame([$user->id], $records->pluck('user_id')->unique()->all());
                $this->assertSame([$status], $records->pluck('status')->unique()->all());
            }
        }
    }

    public function test_edicao_direta_nao_expoe_registros_do_outro_usuario(): void
    {
        $this->seed(PortfolioDemoSeeder::class);

        foreach (User::all() as $user) {
            $this->app['session']->flush();
            $this->actingAs($user);

            foreach ([Conta::class => 'contas', Lancamento::class => 'lancamentos'] as $model => $resource) {
                $own = $model::where('user_id', $user->id)->firstOrFail();
                $other = $model::where('user_id', '!=', $user->id)->firstOrFail();
                $this->get("/admin/{$resource}/{$own->id}/edit")->assertOk();
                $this->get("/admin/{$resource}/{$other->id}/edit")->assertNotFound();
            }
        }
    }

    public function test_login_com_cnpj_ficticio(): void
    {
        $this->seed(PortfolioDemoSeeder::class);
        Livewire::test(Login::class)
            ->fillForm(['cnpj' => '00000000000000', 'password' => 'senha-ficticia-testes'])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertAuthenticatedAs(User::first());
    }

    public function test_cadastro_preserva_valor_e_usuario(): void
    {
        $this->seed(PortfolioDemoSeeder::class);
        $user = User::first();
        $this->actingAs($user);
        Livewire::test(CreateConta::class)
            ->fillForm(['fornecedor' => 'Fornecedor apresentação', 'numeroDocumento' => 'APRESENTACAO-01', 'valor' => '123.45', 'tipo' => '1', 'status' => '2', 'dataVencimento' => now()->addDays(7)->toDateString()])
            ->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('contas', ['numeroDocumento' => 'APRESENTACAO-01', 'user_id' => $user->id, 'valor' => '123.45']);
    }

    public function test_pagamento_preserva_codigo_string_e_data(): void
    {
        $this->seed(PortfolioDemoSeeder::class);
        $user = User::first();
        $this->actingAs($user);
        $conta = Conta::where('user_id', $user->id)->where('status', '2')->first();
        Livewire::test(ListContas::class)->call('updateTableColumnState', 'status', (string) $conta->id, true);
        $this->assertSame('1', $conta->fresh()->status);
        $this->assertSame(now()->toDateString(), $conta->fresh()->dataPagamento);
    }
}
