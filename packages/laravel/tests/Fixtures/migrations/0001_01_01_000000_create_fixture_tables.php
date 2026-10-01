<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('iso_code', 2);
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->nullable()->constrained();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title')->index();
            $table->string('slug')->unique();
            $table->text('body');
            $table->string('status', 20)->default('draft');
            $table->json('metadata')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->uuid('external_id')->nullable();
            $table->date('publish_on')->nullable();
            $table->time('publish_at_time')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('api_token')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->morphs('commentable');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number');
            $table->enum('status', ['open', 'paid']);
            $table->boolean('paid')->default(false);
            $table->bigInteger('amount_cents');
            $table->timestamps();
        });
    }
};
