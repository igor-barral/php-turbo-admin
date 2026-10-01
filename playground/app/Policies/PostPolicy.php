<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/** Playground: qualquer usuário autenticado pode tudo, exceto excluir a si mesmo. */
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Post $model): bool
    {
        return true;
    }

    public function delete(User $user, Post $model): bool
    {
        return ! ($model instanceof User && $model->is($user));
    }
}
