<?php

namespace Database\Factories;

use App\Models\Theme;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Theme>
 */
class ThemeFactory extends Factory
{
    protected $model = Theme::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'id' => Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'main_image' => fake()->imageUrl(),
        ];
    }
}
