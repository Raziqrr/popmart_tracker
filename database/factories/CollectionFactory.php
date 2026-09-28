<?php

namespace Database\Factories;

use App\Models\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;


/**
 * @extends Factory<Collection>
 */
class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'id' => Str::uuid(),
            'theme_id' => Theme::factory(),
            'area' => fake()->randomElement(['US', 'EU', 'ASIA', 'MY']),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}