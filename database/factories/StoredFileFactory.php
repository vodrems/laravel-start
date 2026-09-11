<?php

namespace Database\Factories;

use App\Models\StoredFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StoredFile>
 */
class StoredFileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'original_name' => fake()->word().'.pdf',
            'path' => 'uploads/'.Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1_000, 5_000_000),
            'expires_at' => now()->addHours(config('filestorage.ttl_hours')),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'created_at' => now()->subHours(config('filestorage.ttl_hours') + 1),
            'expires_at' => now()->subHour(),
        ]);
    }
}
