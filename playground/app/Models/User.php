<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PhpTurbo\Admin\Core\Builder\AdminSchema;
use PhpTurbo\Admin\Laravel\HasAdmin;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasAdmin, HasFactory, Notifiable;

    public static function configureAdmin(AdminSchema $schema): void
    {
        $schema->label('Usuário', 'Usuários')
            ->field('name')->label('Nome')->searchable()
            ->field('email')->label('E-mail')->searchable()
            ->field('password')->label('Senha')->writeOnly();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
