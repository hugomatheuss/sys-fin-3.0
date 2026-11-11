<?php

namespace App\Providers;

use App\Models\Conta;
use App\Models\Lancamento;
use App\Policies\ContaPolicy;
use App\Policies\LancamentoPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Conta::class => ContaPolicy::class,
        Lancamento::class => LancamentoPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
