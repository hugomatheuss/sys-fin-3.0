<?php

namespace Tests\Integration;

use App\Filament\Resources\ContaResource;
use App\Filament\Resources\ContaResource\Pages\ListContas;
use App\Filament\Resources\LancamentoResource;
use App\Filament\Resources\LancamentoResource\Pages\ListLancamentos;
use App\Models\User;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrdenacaoPostgresTest extends TestCase
{
    public function test_ordenacao_preserva_centavos_e_isolamento_no_postgres(): void
    {
        if (getenv('SYSFINAN_TEST_POSTGRES') !== '1' || getenv('DB_HOST') !== 'portfolio-postgres') {
            $this->markTestSkipped('Execute explicitamente no contêiner isolado de portfólio com SYSFINAN_TEST_POSTGRES=1.');
        }

        config([
            'database.default' => 'ordenacao',
            'database.connections.ordenacao' => [
                'driver' => 'pgsql', 'host' => 'portfolio-postgres', 'port' => 5432,
                'database' => 'sysfinan_portfolio', 'username' => 'portfolio',
                'password' => 'portfolio-local-only', 'charset' => 'utf8',
                'prefix' => '', 'search_path' => 'pg_temp', 'sslmode' => 'prefer',
            ],
        ]);
        $connection = DB::connection();

        try {
            // Apenas tabelas temporárias desta conexão; nenhum registro persistente é consultado.
            $connection->statement('CREATE TEMP TABLE contas (id INTEGER, user_id INTEGER, valor VARCHAR(255))');
            $connection->statement('CREATE TEMP TABLE lancamentos (id INTEGER, user_id INTEGER, recebimento VARCHAR(255), pagamento VARCHAR(255))');
            $user = new User;
            $user->id = 1;
            $this->actingAs($user);

            $valores = ['90071992547409.93', '90071992547409.92', '90071992547409.91', '-0.10', '0', str_repeat('9', 252).'.99'];
            foreach ($valores as $index => $valor) {
                $connection->table('contas')->insert(['id' => $index + 1, 'user_id' => 1, 'valor' => $valor]);
                $connection->table('lancamentos')->insert([
                    'id' => $index + 1, 'user_id' => 1,
                    'recebimento' => $valor, 'pagamento' => $valores[count($valores) - $index - 1],
                ]);
            }
            $connection->table('contas')->insert(['id' => 99, 'user_id' => 2, 'valor' => '0.01']);
            $connection->table('lancamentos')->insert(['id' => 99, 'user_id' => 2, 'recebimento' => '0.01', 'pagamento' => '0']);

            foreach ([
                [ContaResource::class, ListContas::class, ['valor']],
                [LancamentoResource::class, ListLancamentos::class, ['recebimento', 'pagamento', 'Total']],
            ] as [$resource, $page, $columns]) {
                $table = $resource::table(Table::make(new $page));
                foreach ($columns as $name) {
                    foreach (['asc', 'desc'] as $direction) {
                        $query = $resource::getEloquentQuery();
                        $table->getColumn($name)->applySort($query, $direction);
                        $records = $query->get();
                        $this->assertCount(6, $records);
                        $this->assertSame([1], $records->pluck('user_id')->unique()->values()->all());
                        $values = $records->map(fn ($record): string => $name === 'Total'
                            ? bcsub($record->recebimento, $record->pagamento, 2)
                            : $record->{$name})->all();
                        $expected = $values;
                        usort($expected, fn (string $a, string $b): int => bccomp($a, $b, 2) * ($direction === 'desc' ? -1 : 1));
                        $this->assertSame($expected, $values, "Ordenação de {$name} em {$direction}");
                    }
                }
            }
        } finally {
            DB::disconnect('ordenacao');
        }
    }
}
