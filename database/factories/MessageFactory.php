<?php

namespace Database\Factories;

use App\Enums\CorpusProfile;
use App\Enums\MessageRole;
use App\Enums\ProductStatus;
use App\Enums\Rating;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Default state: a user question (already redacted in production).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'profile' => CorpusProfile::Kings5Docs->value,
            'role' => MessageRole::User,
            'content' => $this->faker->sentence().'?',
            'normalized_question_hash' => hash('sha256', $this->faker->sentence()),
            'product_status' => null,
            'rating' => null,
            'rating_reason_code' => null,
        ];
    }

    /**
     * An assistant answer composed by the backend.
     */
    public function assistant(): static
    {
        return $this->state(fn (): array => [
            'role' => MessageRole::Assistant,
            'content' => $this->faker->paragraph(),
            'normalized_question_hash' => null,
            'product_status' => ProductStatus::Answered,
        ]);
    }

    public function ratedUp(): static
    {
        return $this->state(fn (): array => ['rating' => Rating::Up]);
    }

    public function ratedDown(): static
    {
        return $this->state(fn (): array => ['rating' => Rating::Down]);
    }
}
