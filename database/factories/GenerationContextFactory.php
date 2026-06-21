<?php

namespace Database\Factories;

use App\Models\Generation;
use App\Models\GenerationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GenerationContext>
 */
class GenerationContextFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitId = 'start.'.$this->faker->unique()->slug(2);

        return [
            'generation_id' => Generation::factory(),
            'answer_unit_id' => $unitId,
            'content_hash' => hash('sha256', $this->faker->paragraph()),
        ];
    }
}
