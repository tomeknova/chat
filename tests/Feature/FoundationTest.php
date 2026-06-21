<?php

namespace Tests\Feature;

use App\Enums\InfraStatus;
use App\Enums\MessageRole;
use App\Enums\ProductStatus;
use App\Enums\Rating;
use App\Enums\ResponseType;
use App\Enums\ValidationStatus;
use App\Models\Conversation;
use App\Models\Generation;
use App\Models\GenerationContext;
use App\Models\Message;
use App\Models\MessageUnit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_auto_generates_ulid_public_id(): void
    {
        $conversation = Conversation::factory()->create();

        $this->assertNotEmpty($conversation->public_id);
        $this->assertSame(26, strlen($conversation->public_id));
        $this->assertNotEmpty($conversation->owner_token_hash);
    }

    public function test_full_chain_relations(): void
    {
        $generation = Generation::factory()
            ->has(GenerationContext::factory()->count(2), 'context')
            ->has(MessageUnit::factory()->count(1), 'units')
            ->create();

        $this->assertInstanceOf(Message::class, $generation->message);
        $this->assertInstanceOf(Conversation::class, $generation->message->conversation);
        $this->assertCount(2, $generation->context);
        $this->assertCount(1, $generation->units);
    }

    public function test_deleting_a_conversation_cascades_recursively(): void
    {
        $generation = Generation::factory()
            ->has(GenerationContext::factory()->count(2), 'context')
            ->has(MessageUnit::factory()->count(2), 'units')
            ->create();

        $generation->message->conversation->delete();

        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('generation_context', 0);
        $this->assertDatabaseCount('message_units', 0);
    }

    public function test_enum_casts_round_trip(): void
    {
        $message = Message::factory()->assistant()->ratedDown()->create();
        $this->assertSame(MessageRole::Assistant, $message->refresh()->role);
        $this->assertSame(ProductStatus::Answered, $message->product_status);
        $this->assertSame(Rating::Down, $message->rating);

        $generation = Generation::factory()->create(['message_id' => $message->id]);
        $this->assertSame(ResponseType::Answer, $generation->refresh()->response_type);
        $this->assertSame(InfraStatus::Completed, $generation->infra_status);

        $unit = MessageUnit::factory()->create(['generation_id' => $generation->id]);
        $this->assertSame(ValidationStatus::Accepted, $unit->refresh()->validation_status);
    }

    public function test_operation_id_is_unique(): void
    {
        Generation::factory()->create(['operation_id' => 'op-duplicate']);

        $this->expectException(QueryException::class);

        Generation::factory()->create(['operation_id' => 'op-duplicate']);
    }

    public function test_messages_have_no_updated_at(): void
    {
        $message = Message::factory()->create();

        $this->assertNotNull($message->created_at);
        $this->assertArrayNotHasKey('updated_at', $message->getAttributes());
    }
}
