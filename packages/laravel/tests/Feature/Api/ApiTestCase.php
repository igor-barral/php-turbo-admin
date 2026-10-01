<?php

declare(strict_types=1);

namespace PhpTurbo\Admin\Laravel\Tests\Feature\Api;

use Illuminate\Support\Facades\Gate;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\Post;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\PostStatus;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Models\User;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Policies\PostPolicy;
use PhpTurbo\Admin\Laravel\Tests\Fixtures\Policies\UserPolicy;
use PhpTurbo\Admin\Laravel\Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    protected User $admin;
    protected User $author;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::define('viewAdmin', static fn (User $user): bool => $user->email !== 'blocked@example.com');

        $this->admin = $this->makeUser('Admin', 'admin@example.com', admin: true);
        $this->author = $this->makeUser('Autora', 'autora@example.com');
    }

    protected function makeUser(string $name, string $email, bool $admin = false): User
    {
        $user = new User();
        $user->forceFill(['name' => $name, 'email' => $email, 'password' => 'secret-password', 'is_admin' => $admin])->save();

        return $user;
    }

    protected function makePost(User $owner, string $title, array $attributes = []): Post
    {
        $post = new Post();
        $post->forceFill($attributes + [
            'user_id' => $owner->id,
            'title' => $title,
            'slug' => str($title)->slug()->value(),
            'body' => 'Conteúdo de ' . $title,
            'status' => PostStatus::Draft,
            'api_token' => 'tok_' . $title,
        ])->save();

        return $post;
    }

    protected function api(string $path = ''): string
    {
        return '/admin/api/v1/' . ltrim($path, '/');
    }
}
