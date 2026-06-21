<?php

namespace Database\Factories;

use App\Enums\ValidationStatus;
use App\Models\Generation;
use App\Models\MessageUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageUnit>
 */
class MessageUnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'generation_id' => Generation::factory(),
            'answer_unit_id' => 'start.'.$this->faker->unique()->slug(2),
            'validation_status' => ValidationStatus::Accepted,
            'display_ordinal' => 1,
        ];
    }

    public function rejectedUnknownUnit(): static
    {
        return $this->state(fn (): array => [
            'validation_status' => ValidationStatus::RejectedUnknownUnit,
            'display_ordinal' => null,
        ]);
    }
}
