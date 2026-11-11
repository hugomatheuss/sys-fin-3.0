<?php

namespace App\Policies;

use App\Models\Lancamento;
use App\Models\User;

class LancamentoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lancamento $lancamento): bool
    {
        return (string) $user->id === (string) $lancamento->user_id;
    }

    public function update(User $user, Lancamento $lancamento): bool
    {
        return (string) $user->id === (string) $lancamento->user_id;
    }

    public function delete(User $user, Lancamento $lancamento): bool
    {
        return (string) $user->id === (string) $lancamento->user_id;
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }
}
