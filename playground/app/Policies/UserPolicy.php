<?php

namespace App\Policies;

use App\Models\User;

/** Playground: qualquer usuário autenticado pode tudo, exceto excluir a si mesmo. */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, User $model): bool
    {
        return true;
    }

    public function delete(User $user, User $model): bool
    {
        return ! ($model instanceof User && $model->is($user));
    }
}
