<?php

namespace Database\Factories;

use App\Enums\InfraStatus;
use App\Enums\ResponseType;
use App\Models\Generation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory()->assistant(),
            'operation_id' => (string) Str::uuid(),
            'model' => 'openai/gpt-5.4-nano',
            'response_type' => ResponseType::Answer,
            'input_tokens' => $this->faker->numberBetween(100, 500),
            'output_tokens' => $this->faker->numberBetween(10, 100),
            'cost' => $this->faker->randomFloat(8, 0.00001, 0.001),
            'infra_status' => InfraStatus::Completed,
        ];
    }
}
