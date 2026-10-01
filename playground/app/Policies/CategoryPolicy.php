<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/** Playground: qualquer usuário autenticado pode tudo, exceto excluir a si mesmo. */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Category $model): bool
    {
        return true;
    }

    public function delete(User $user, Category $model): bool
    {
        return ! ($model instanceof User && $model->is($user));
    }
}
