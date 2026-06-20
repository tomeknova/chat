<?php

/**
 * Livewire Component: Chat
 *
 * Path: app/Livewire/Chat.php
 *
 * Public single-page assistant chat. User asks a question, the assistant
 * answers strictly from the KINGS docs and returns a link. Rating (👍/👎)
 * feeds the curation loop.
 *
 * Business logic (the actual AI call) lives in an Action — this component
 * stays thin and only orchestrates state + view (per CLAUDE.md).
 *
 * @see resources/views/livewire/chat.blade.php
 */

namespace App\Livewire;

use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Chat extends Component
{
    // =========================================================================
    // PROPERTIES
    // =========================================================================

    /**
     * Rendered conversation. Each item:
     * ['role' => 'user'|'assistant', 'text' => string, 'time' => string,
     *  'link' => ?string, 'covered' => ?bool].
     */
    public array $messages = [];

    /**
     * Current question bound to the input.
     */
    #[Validate('required|string|min:2|max:2000')]
    public string $question = '';

    // =========================================================================
    // LIFECYCLE
    // =========================================================================

    /**
     * Seed the opening assistant message.
     */
    public function mount(): void
    {
        $this->messages[] = [
            'role' => 'assistant',
            'text' => 'Witaj! Zadaj pytanie o panel KINGS — odpowiem wyłącznie na podstawie dokumentacji i wskażę link.',
            'time' => now()->format('H:i'),
            'link' => null,
            'covered' => true,
        ];
    }

    // =========================================================================
    // ACTIONS
    // =========================================================================

    /**
     * Handle a submitted question.
     */
    public function sendMessage(): void
    {
        $this->validate();

        // Throttle the public endpoint to protect against cost/abuse (CLAUDE.md).
        $key = 'chat-send:'.request()->ip();
        if (! RateLimiter::attempt($key, 10, fn () => true, 60)) {
            $this->addError('question', 'Zbyt wiele pytań — odczekaj chwilę.');

            return;
        }

        $question = trim($this->question);

        // Append the user's message.
        $this->messages[] = [
            'role' => 'user',
            'text' => $question,
            'time' => now()->format('H:i'),
            'link' => null,
            'covered' => null,
        ];

        // TODO[v1]: call App\Actions\AskDocs (OpenRouter, structured {answer, link, covered}).
        // Placeholder reply until the AI Action is wired.
        $this->messages[] = [
            'role' => 'assistant',
            'text' => 'Podgląd interfejsu — odpowiedź AI podłączymy w v1 (AskDocs → OpenRouter).',
            'time' => now()->format('H:i'),
            'link' => null,
            'covered' => false,
        ];

        $this->reset('question');

        // Tell the view to scroll the message list to the bottom.
        $this->dispatch('chat-updated');
    }

    // =========================================================================
    // RENDER
    // =========================================================================

    public function render()
    {
        return view('livewire.chat');
    }
}
