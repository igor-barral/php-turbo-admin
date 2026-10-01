<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::factory()->create(['name' => 'Admin', 'email' => 'admin@example.com']);
        $authors = User::factory(4)->create()->push($admin);

        $categories = collect(['Laravel', 'Symfony', 'PHP', 'Performance', 'Segurança'])
            ->map(fn (string $name) => Category::create(['name' => $name, 'description' => "Artigos sobre {$name}."]));

        foreach (range(1, 60) as $i) {
            $title = fake()->unique()->sentence(5);
            $status = fake()->randomElement(PostStatus::cases());

            Post::create([
                'user_id' => $authors->random()->id,
                'category_id' => $i % 7 === 0 ? null : $categories->random()->id,
                'title' => rtrim($title, '.'),
                'slug' => Str::slug($title),
                'body' => fake()->paragraphs(3, true),
                'status' => $status,
                'featured' => $i % 5 === 0,
                'metadata' => ['reading_time' => fake()->numberBetween(2, 15)],
                'published_at' => $status === PostStatus::Published ? fake()->dateTimeBetween('-1 year') : null,
            ]);
        }
    }
}
