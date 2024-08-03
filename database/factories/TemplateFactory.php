<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Template>
 */
class TemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $layouts = [];
        $items = [];

        // Generate a random number of layout items
        $numItems = $this->faker->numberBetween(1, 5);

        for ($i = 0; $i < $numItems; $i++) {
            $layouts[] = [
                'i' => (string) $i,
                'x' => $this->faker->numberBetween(0, 10),
                'y' => $this->faker->numberBetween(0, 10),
                'w' => $this->faker->numberBetween(1, 4),
                'h' => $this->faker->numberBetween(1, 4)
            ];

            $items[] = [
                'id' => (string) $i,
                'type' => $this->faker->randomElement(['text', 'image', 'video']),
                'content' => $this->faker->text(20),
                'src' => $this->faker->imageUrl()
            ];
        }

        return [
            'name' => $this->faker->sentence(3, true),
            'content' => json_encode([
                'layouts' => $layouts,
                'items' => $items
            ]),
            'default' => $this->faker->boolean,
            'type' => $this->faker->randomElement(['personnage', 'inventaire', 'quete', 'competences', 'note', 'autres']),
        ];
    }
}
