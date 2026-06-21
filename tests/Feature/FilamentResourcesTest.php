<?php

namespace Tests\Feature;

use App\Filament\Resources\Generations\Pages\ListGenerations;
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
}
