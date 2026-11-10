<?php

namespace Database\Seeders;

use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PortfolioDemoSeeder extends Seeder
{
    public function run(): void
    {
        $connection = DB::connection();
        $localDatabase = app()->environment('portfolio')
            && $connection->getDriverName() === 'pgsql'
            && $connection->getConfig('host') === 'portfolio-postgres'
            && $connection->getDatabaseName() === 'sysfinan_portfolio';
        $testDatabase = app()->environment('testing')
            && $connection->getDriverName() === 'sqlite'
            && $connection->getDatabaseName() === ':memory:';

        if (! config('portfolio.enabled') || (! $localDatabase && ! $testDatabase)) {
            throw new RuntimeException('Os dados fictícios exigem o ambiente isolado de portfólio.');
        }

        $password = config('portfolio.password');

        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Defina PORTFOLIO_DEMO_PASSWORD com pelo menos 12 caracteres.');
        }

        DB::transaction(function () use ($password): void {
            foreach (['00000000000000', '00000000000001'] as $index => $cnpj) {
                $number = $index + 1;
                $user = User::firstOrCreate(['cnpj' => $cnpj], [
                    'name' => "Usuário fictício {$number}",
                    'razaoSocial' => "Farmácia Demonstração {$number} — Fictícia",
                    // O painel atual exige Gmail. Nenhum e-mail sai do Mailpit local.
                    'email' => "sysfinan.demo.ficticio.{$number}@gmail.com",
                    'password' => Hash::make($password),
                ]);
                $anchor = CarbonImmutable::instance($user->created_at)->startOfDay();

                foreach ([-60, -30, -7, 0, 7, 15] as $position => $days) {
                    $paid = $position < 2;
                    Conta::firstOrCreate([
                        'user_id' => $user->id,
                        'numeroDocumento' => 'DEMO-'.($position + 1),
                    ], [
                        'fornecedor' => 'Fornecedor fictício '.($position + 1),
                        'descricao' => 'Conta fictícia para demonstração',
                        'valor' => ['1250.50', '89.90', '740.00', '315.25', '150.00', '2800.99'][$position],
                        'status' => $paid ? '1' : '2',
                        'tipo' => $position % 2 === 0 ? '1' : '2',
                        'dataVencimento' => $anchor->addDays($days)->toDateString(),
                        'dataPagamento' => $paid ? $anchor->addDays($days)->toDateString() : null,
                    ]);
                }

                foreach ([-60, -30, 0] as $position => $days) {
                    Lancamento::firstOrCreate([
                        'user_id' => $user->id,
                        'dataLancamento' => $anchor->addDays($days)->toDateString(),
                    ], [
                        'recebimento' => ['2000.00', '1750.50', '2500.99'][$position],
                        'pagamento' => ['500.00', '315.25', '150.00'][$position],
                        'tipoRecebimento' => $position % 2 === 0 ? '1' : '0',
                        'tipoPagamento' => $position % 2 === 0 ? '1' : '0',
                    ]);
                }
            }
        });
    }
}
