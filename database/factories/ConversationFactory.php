<?php

namespace Database\Factories;

use App\Enums\CorpusProfile;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * Define the model's default state. (public_id is auto-set by the model.)
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_token_hash' => hash('sha256', Str::random(40)),
            'profile' => CorpusProfile::Kings5Docs->value,
        ];
    }
}
