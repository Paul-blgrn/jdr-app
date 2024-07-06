<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Board>
 */
class BoardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'capacity' => $this->faker->numberBetween(2,10),
            'code' => $this->generateUniqueCode(),
        ];
    }

    /**
     * Generate a unique random code.
     *
     * @return string
     */
    private function generateUniqueCode(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $codeLength = 10;
        $code = '';

        // Generate a random unique code
        do {
            $code = '';
            for ($i = 0; $i < $codeLength; $i++) {
                $code .= $characters[rand(0, strlen($characters) - 1)];
            }
        } while (\App\Models\Board::where('code', $code)->exists()); // Ensure code is unique

        return $code;
    }
}
