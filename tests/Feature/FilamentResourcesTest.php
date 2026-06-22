<?php

namespace Tests\Feature;

use App\Enums\ProcessingStatus;
use App\Filament\Resources\Generations\Pages\ListGenerations;
use App\Filament\Resources\Generations\Pages\ViewGeneration;
use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Messages\Pages\ViewMessage;
use App\Models\Generation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_messages_list_loads_and_shows_records(): void
    {
        $messages = Message::factory()->count(3)->create();

        Livewire::test(ListMessages::class)
            ->assertOk()
            ->assertCanSeeTableRecords($messages);
    }

    public function test_messages_can_be_filtered_to_thumbs_down(): void
    {
        $down = Message::factory()->assistant()->ratedDown()->create();
        $up = Message::factory()->assistant()->ratedUp()->create();

        Livewire::test(ListMessages::class)
            ->filterTable('rating', 'down')
            ->assertCanSeeTableRecords([$down])
            ->assertCanNotSeeTableRecords([$up]);
    }

    public function test_message_view_loads(): void
    {
        $message = Message::factory()->assistant()->create();

        Livewire::test(ViewMessage::class, ['record' => $message->getRouteKey()])
            ->assertOk();
    }

    public function test_generations_list_loads_and_shows_records(): void
    {
        $generations = Generation::factory()->count(2)->create();

        Livewire::test(ListGenerations::class)
            ->assertOk()
            ->assertCanSeeTableRecords($generations);
    }

    public function test_generation_view_loads_with_status_and_attempts_telemetry(): void
    {
        $generation = Generation::factory()->create([
            'status' => ProcessingStatus::Completed,
            'execution_attempt' => 2,
            'metadata' => ['attempts' => [
                ['provider' => 'bielik', 'model' => 'bielik-11b-v3-q80:latest', 'status' => 'grounding_violation', 'fallbackable' => true],
                ['provider' => 'openrouter', 'model' => 'openai/gpt-5.4-nano', 'status' => 'completed', 'fallbackable' => false],
            ]],
        ]);

        Livewire::test(ViewGeneration::class, ['record' => $generation->getRouteKey()])
            ->assertOk()
            ->assertSee('bielik')
            ->assertSee('openrouter');
    }
}
