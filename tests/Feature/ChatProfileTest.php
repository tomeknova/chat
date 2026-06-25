<?php

namespace Tests\Feature;

use App\Enums\CorpusProfile;
use App\Livewire\Chat;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Faza 2 — instruction switching: profile per conversation, lifecycle, isolation.
 */
class ChatProfileTest extends TestCase
{
    use RefreshDatabase;

    private string $corpusDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->corpusDir = sys_get_temp_dir().'/chatprof_'.uniqid();
        @mkdir($this->corpusDir, 0775, true);
        $this->writeCorpus('kings5-docs', 'start.kings', 'Treść KINGS only.');
        $this->writeCorpus('clams-docs', 'start.clams', 'Treść CLAMS only.');

        config([
            'askdocs.default' => 'openrouter',
            'askdocs.providers.openrouter' => [
                'driver' => 'openrouter',
                'base_url' => 'https://openrouter.ai/api/v1',
                'key' => 'test-key',
                'model' => 'openai/gpt-5.4-nano',
                'providers' => ['openai', 'azure'],
            ],
            'corpus.output_dir' => $this->corpusDir,
            'corpus.active_profile' => 'kings5-docs',
            'corpus.output_path' => $this->corpusDir.'/corpus-kings5-docs.json',
        ]);

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->corpusDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->corpusDir);

        parent::tearDown();
    }

    private function writeCorpus(string $profile, string $id, string $content): void
    {
        file_put_contents($this->corpusDir."/corpus-{$profile}.json", json_encode([
            'units' => [['answer_unit_id' => $id, 'content' => $content, 'content_hash' => 'h', 'intents' => [], 'canonical_url' => "/{$id}"]],
        ]));
    }

    public function test_switch_starts_a_fresh_conversation_in_the_new_profile(): void
    {
        Livewire::test(Chat::class)
            ->call('switchProfile', 'clams-docs')
            ->assertSet('profile', 'clams-docs')
            ->assertSee('CLAMS'); // clams greeting

        $this->assertSame(CorpusProfile::ClamsDocs, Conversation::latest('id')->first()->profile);
    }

    public function test_switch_to_unknown_profile_is_ignored(): void
    {
        Livewire::test(Chat::class)
            ->call('switchProfile', 'nope')
            ->assertSet('profile', 'kings5-docs');

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_switch_to_unavailable_profile_is_ignored(): void
    {
        @unlink($this->corpusDir.'/corpus-clams-docs.json'); // artifact gone → unavailable

        Livewire::test(Chat::class)
            ->call('switchProfile', 'clams-docs')
            ->assertSet('profile', 'kings5-docs');
    }

    public function test_messages_inherit_the_active_conversation_profile(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['response_type' => 'answer', 'answer_unit_ids' => ['start.clams']])]]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'cost' => 0.0001],
        ])]);

        Livewire::test(Chat::class)
            ->call('switchProfile', 'clams-docs')
            ->set('question', 'Pytanie o CLAMS?')
            ->call('sendMessage');

        $this->assertGreaterThan(0, Message::count());
        foreach (Message::all() as $message) {
            $this->assertSame(CorpusProfile::ClamsDocs, $message->profile, "Message {$message->id} ma zły profil");
        }
    }

    public function test_returning_user_restores_the_conversation_profile(): void
    {
        $hash = hash('sha256', (string) config('chat.owner_token_pepper').'tok-clams');
        Conversation::factory()->create(['owner_token_hash' => $hash, 'profile' => 'clams-docs']);

        Livewire::withCookies(['kings_chat_owner' => 'tok-clams'])
            ->test(Chat::class)
            ->assertSet('profile', 'clams-docs');
    }
}
