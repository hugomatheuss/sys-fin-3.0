<?php

namespace App\Policies;

use App\Models\Conta;
use App\Models\User;

class ContaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Conta $conta): bool
    {
        return (string) $user->id === (string) $conta->user_id;
    }

    public function update(User $user, Conta $conta): bool
    {
        return (string) $user->id === (string) $conta->user_id;
    }

    public function delete(User $user, Conta $conta): bool
    {
        return (string) $user->id === (string) $conta->user_id;
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }
}
