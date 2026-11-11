<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use Database\Seeders\PortfolioDemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
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

    public function test_credenciais_invalidas_exibem_erro_no_campo_cnpj(): void
    {
        foreach (['00000000000000', '99999999999999'] as $cnpj) {
            Livewire::test(Login::class)
                ->fillForm(['cnpj' => $cnpj, 'password' => 'senha-incorreta'])
                ->call('authenticate')
                ->assertHasFormErrors(['cnpj']);
            $this->assertGuest();
        }
    }

    public function test_recursos_financeiros_sem_sessao_redirecionam_para_login(): void
    {
        foreach (['/admin', '/admin/contas', '/admin/contas/create', '/admin/contas/1/edit', '/admin/lancamentos', '/admin/lancamentos/create', '/admin/lancamentos/1/edit'] as $url) {
            $this->get($url)->assertRedirect(route('filament.admin.auth.login'));
        }
    }
}
