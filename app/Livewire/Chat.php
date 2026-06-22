<?php

/**
 * Livewire Component: Chat
 *
 * Public single-page assistant. The user asks; App\Actions\AskDocs answers
 * STRICTLY from approved docs (grounded answer-unit selection — never free
 * text) and the validated units + source links are rendered. Messages are
 * persisted against an anonymous owner-token HASH (RODO); ratings feed curation.
 *
 * Thin component: state + persistence of the user turn, then delegates the AI
 * work to the Action (per CLAUDE.md / BACKEND_CONVENTIONS).
 *
 * @see resources/views/livewire/chat.blade.php
 */

namespace App\Livewire;

use App\Actions\AiGate;
use App\Actions\AskDocs;
use App\Actions\RedactPii;
use App\Enums\MessageRole;
use App\Enums\Rating;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Chat extends Component
{
    public const OWNER_COOKIE = 'kings_chat_owner';

    /**
     * Rendered conversation bubbles.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $messages = [];

    public ?int $conversationId = null;

    #[Validate('required|string|min:2|max:2000')]
    public string $question = '';

    public function mount(): void
    {
        $conversation = $this->existingConversation();

        if ($conversation === null) {
            $this->messages[] = $this->greeting();

            return;
        }

        $this->conversationId = $conversation->id;

        $history = $conversation->messages()->oldest()->get();

        $this->messages = $history->isEmpty()
            ? [$this->greeting()]
            : $history->map(fn (Message $message): array => $this->toBubble($message))->all();
    }

    public function sendMessage(AskDocs $askDocs, RedactPii $redactPii, AiGate $aiGate): void
    {
        $this->validate();

        // Public endpoint → throttle (cost/abuse protection).
        if (! RateLimiter::attempt('chat-send:'.request()->ip(), 10, fn () => true, 60)) {
            $this->addError('question', 'Zbyt wiele pytań — odczekaj chwilę.');

            return;
        }

        // Kill-switch + daily budget — block before spending anything.
        if ($reason = $aiGate->blockedReason()) {
            $this->addError('question', $reason);

            return;
        }

        // PII redacted before storage (SCOPE_V1: raw question is NOT kept).
        $redacted = $redactPii->handle(trim($this->question));
        $conversation = $this->ensureConversation();

        $userMessage = $conversation->messages()->create([
            'role' => MessageRole::User,
            'content' => $redacted,
            'normalized_question_hash' => hash('sha256', Str::lower($redacted)),
        ]);
        $this->messages[] = $this->toBubble($userMessage);
        $this->reset('question');

        $result = $askDocs->handle($userMessage, (string) Str::uuid());
        $this->messages[] = $this->toBubble($result['message'], $result['sources'], $result['suggestions']);

        $this->dispatch('chat-updated');
    }

    /**
     * Ask a suggested question (starter / recovery chip) — set it and send.
     */
    public function ask(string $question): void
    {
        $this->question = $question;

        app()->call([$this, 'sendMessage']);
    }

    public function rate(int $messageId, string $rating): void
    {
        $value = Rating::tryFrom($rating);

        if ($value === null) {
            return;
        }

        $message = Message::query()
            ->where('conversation_id', $this->conversationId)
            ->where('role', MessageRole::Assistant->value)
            ->find($messageId);

        if ($message === null) {
            return;
        }

        // Clicking the active rating again clears it.
        $message->rating = $message->rating === $value ? null : $value;
        $message->save();

        foreach ($this->messages as $index => $bubble) {
            if (($bubble['id'] ?? null) === $message->id) {
                $this->messages[$index]['rating'] = $message->rating?->value;
                break;
            }
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function hashToken(string $token): string
    {
        return hash('sha256', (string) config('chat.owner_token_pepper').$token);
    }

    private function existingConversation(): ?Conversation
    {
        $token = request()->cookie(self::OWNER_COOKIE);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return Conversation::where('owner_token_hash', $this->hashToken($token))->latest('id')->first();
    }

    private function ensureConversation(): Conversation
    {
        if ($this->conversationId !== null) {
            return Conversation::findOrFail($this->conversationId);
        }

        $token = request()->cookie(self::OWNER_COOKIE);

        if (! is_string($token) || $token === '') {
            $token = Str::random(40);
            Cookie::queue(cookie(self::OWNER_COOKIE, $token, 60 * 24 * 365));
        }

        $conversation = Conversation::firstOrCreate(['owner_token_hash' => $this->hashToken($token)]);
        $this->conversationId = $conversation->id;

        return $conversation;
    }

    /**
     * @return array<string, mixed>
     */
    private function greeting(): array
    {
        return [
            'id' => null,
            'role' => MessageRole::Assistant->value,
            'text' => 'Witaj! Zadaj pytanie o panel KINGS — odpowiem wyłącznie na podstawie dokumentacji i wskażę źródło.',
            'time' => now()->format('H:i'),
            'sources' => [],
            'suggestions' => array_values((array) config('chat.suggestions', [])),
            'rating' => null,
        ];
    }

    /**
     * @param  list<array{answer_unit_id: string, title: string, canonical_url: string}>|null  $sources
     * @param  list<string>  $suggestions
     * @return array<string, mixed>
     */
    private function toBubble(Message $message, ?array $sources = null, array $suggestions = []): array
    {
        $isAssistant = $message->role === MessageRole::Assistant;

        return [
            'id' => $message->id,
            'role' => $message->role->value,
            'text' => $message->content,
            'time' => $message->created_at?->format('H:i') ?? now()->format('H:i'),
            'sources' => $sources ?? ($isAssistant ? app(AskDocs::class)->sourcesFor($message) : []),
            'suggestions' => $suggestions,
            'rating' => $message->rating?->value,
        ];
    }

    public function render()
    {
        return view('livewire.chat');
    }
}
