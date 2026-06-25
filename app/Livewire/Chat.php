<?php

/**
 * Livewire Component: Chat
 *
 * Public single-page assistant. The user asks; App\Actions\AskDocs answers
 * STRICTLY from approved docs (grounded answer-unit selection — never free
 * text) and the validated units + source links are rendered. Messages are
 * persisted against an anonymous owner-token HASH (RODO); ratings feed curation.
 *
 * Multi-instruction (Faza 2): every conversation belongs to ONE corpus profile
 * (kings5-docs / clams-docs). The active profile is applied per-request from the
 * AUTHORITATIVE Conversation.profile (not the public property) and the switch
 * starts a fresh conversation (window cleared, old conversation kept as history).
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
use App\AskDocs\QuestionNormalizer;
use App\Enums\CorpusProfile;
use App\Enums\MessageRole;
use App\Enums\Rating;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
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

    #[Locked]
    public ?int $conversationId = null;

    /** Active corpus profile — PRESENTATIONAL only; source of truth = Conversation.profile. */
    #[Locked]
    public string $profile = '';

    #[Validate('required|string|min:2|max:2000')]
    public string $question = '';

    /**
     * Runs on every request (before mount on the first one). Re-applies the
     * active profile from the authoritative conversation so the retriever, source
     * links and starters use the right instruction (audit: boot, not only mount).
     */
    public function boot(): void
    {
        $this->activateConversationProfile();
    }

    public function mount(): void
    {
        $conversation = $this->existingConversation();

        if ($conversation === null) {
            $this->profile = (string) config('corpus.default');
            $this->activateConversationProfile();
            $this->messages[] = $this->greeting();

            return;
        }

        $this->conversationId = $conversation->id;
        // boot() ran before conversationId was known → re-apply for this conversation.
        $this->activateConversationProfile();

        $history = $conversation->messages()->oldest()->get();

        if ($history->isEmpty()) {
            $this->messages = [$this->greeting()];

            return;
        }

        $this->messages = $history->map(fn (Message $message): array => $this->toBubble($message))->all();
        $this->attachStartersToLastAssistant();
    }

    public function sendMessage(AskDocs $askDocs, RedactPii $redactPii, AiGate $aiGate, QuestionNormalizer $normalizer): void
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

        // Active instruction's corpus must be available — abort BEFORE creating a
        // generation / calling the provider (audit: no work on an unavailable profile).
        if (! $this->artifactAvailable($this->profile)) {
            $this->addError('question', 'Ta instrukcja jest chwilowo niedostępna. Spróbuj ponownie później.');

            return;
        }

        // PII redacted before storage (SCOPE_V1: raw question is NOT kept).
        $redacted = $redactPii->handle(trim($this->question));
        $conversation = $this->ensureConversation();

        $userMessage = $conversation->messages()->create([
            'profile' => $conversation->profile,
            'role' => MessageRole::User,
            'content' => $redacted,
            'normalized_question_hash' => $normalizer->hash($redacted),
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

    /**
     * Switch the active instruction. Starts a FRESH conversation in the new
     * profile (window cleared); the old conversation stays in the DB as history.
     */
    public function switchProfile(string $name): void
    {
        if ($name === $this->profile) {
            return;
        }

        // Never trust the client value: must be a known, enabled, available profile.
        if (! $this->isProfileAvailable($name)) {
            return;
        }

        $this->startNewConversation($name);
    }

    /**
     * "Nowa rozmowa": clear the window + start a fresh conversation in the SAME
     * profile. Non-destructive — the previous conversation (feedback/generations)
     * stays in the DB. Hard delete is a separate, deliberate action (admin/RODO).
     */
    public function resetChat(): void
    {
        $this->startNewConversation($this->profile ?: (string) config('corpus.default'));
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
    // PROFILE
    // =========================================================================

    /**
     * Apply the active profile from the AUTHORITATIVE conversation (or the default
     * for a fresh visitor) — overriding the corpus.* config for this request only.
     */
    private function activateConversationProfile(): void
    {
        $name = $this->conversationId !== null
            ? Conversation::find($this->conversationId)?->profile?->value
            : ($this->profile ?: null);

        $name ??= (string) config('corpus.default');

        $this->profile = $name;
        $this->applyProfile($name);
    }

    /**
     * Point the corpus.* keys at one profile for this request (retriever file,
     * source link base, starters, greeting). Data-driven from config('corpus.profiles').
     */
    private function applyProfile(string $name): void
    {
        $profile = config("corpus.profiles.$name") ?? config('corpus.profiles.'.config('corpus.default'));

        if (! is_array($profile)) {
            return;
        }

        config([
            'corpus.active_profile' => $name,
            'corpus.output_path' => $this->artifactPath($name),
            'corpus.base_url' => $profile['base_url'] ?? '',
            'corpus.suggestions' => $profile['suggestions'] ?? [],
            'corpus.greeting' => $profile['greeting'] ?? '',
        ]);
    }

    /**
     * Create a fresh conversation in $name, clear the window, load its greeting.
     */
    private function startNewConversation(string $name): void
    {
        $conversation = Conversation::create([
            'owner_token_hash' => $this->hashToken($this->ownerToken()),
            'profile' => $name,
        ]);

        $this->conversationId = $conversation->id;
        $this->profile = $name;
        $this->applyProfile($name);

        $this->messages = [$this->greeting()];
        $this->reset('question');
        $this->resetErrorBag();
        $this->dispatch('chat-updated');
    }

    /**
     * Available = known enum value + present in config + enabled + readable artifact.
     */
    private function isProfileAvailable(string $name): bool
    {
        if (CorpusProfile::tryFrom($name) === null) {
            return false;
        }

        $profile = config("corpus.profiles.$name");

        if (! is_array($profile) || ! ($profile['enabled'] ?? false)) {
            return false;
        }

        return $this->artifactAvailable($name);
    }

    /**
     * Readable corpus artifact for the profile (legacy corpus.json fallback is
     * allowed ONLY for kings5-docs — clams never resolves to KINGS content).
     */
    private function artifactAvailable(string $name): bool
    {
        if (is_file($this->artifactPath($name)) && is_readable($this->artifactPath($name))) {
            return true;
        }

        if ($name === CorpusProfile::Kings5Docs->value) {
            $legacy = rtrim((string) config('corpus.output_dir'), '/').'/corpus.json';

            return is_file($legacy) && is_readable($legacy);
        }

        return false;
    }

    private function artifactPath(string $name): string
    {
        return rtrim((string) config('corpus.output_dir'), '/').'/corpus-'.$name.'.json';
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function hashToken(string $token): string
    {
        return hash('sha256', (string) config('chat.owner_token_pepper').$token);
    }

    /** Read the owner token from the cookie, creating + queueing one if absent. */
    private function ownerToken(): string
    {
        $token = request()->cookie(self::OWNER_COOKIE);

        if (! is_string($token) || $token === '') {
            $token = Str::random(40);
            Cookie::queue(cookie(self::OWNER_COOKIE, $token, 60 * 24 * 365));
        }

        return $token;
    }

    /** Active conversation = the latest one owned by this visitor (cookie token). */
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

        $conversation = Conversation::create([
            'owner_token_hash' => $this->hashToken($this->ownerToken()),
            'profile' => $this->profile ?: (string) config('corpus.default'),
        ]);
        $this->conversationId = $conversation->id;

        return $conversation;
    }

    /**
     * Returning users skip the welcome bubble, so attach the starter suggestions
     * to the last assistant bubble (when it has none) — they still get guidance.
     */
    private function attachStartersToLastAssistant(): void
    {
        $starters = $this->starters();
        if ($starters === []) {
            return;
        }

        for ($i = count($this->messages) - 1; $i >= 0; $i--) {
            if (($this->messages[$i]['role'] ?? null) !== MessageRole::Assistant->value) {
                continue;
            }
            if (empty($this->messages[$i]['suggestions'])) {
                $this->messages[$i]['suggestions'] = $starters;
            }

            return;
        }
    }

    /**
     * Starter chips for the ACTIVE profile (falls back to the generic config).
     *
     * @return list<string>
     */
    private function starters(): array
    {
        return array_values((array) config('corpus.suggestions', (array) config('chat.suggestions', [])));
    }

    /**
     * @return array<string, mixed>
     */
    private function greeting(): array
    {
        $text = (string) config('corpus.greeting');

        return [
            'id' => null,
            'role' => MessageRole::Assistant->value,
            'text' => $text !== '' ? $text : 'Witaj! Zadaj pytanie o dokumentację — odpowiem wyłącznie na jej podstawie i wskażę źródło.',
            'time' => now()->format('H:i'),
            'sources' => [],
            'suggestions' => $this->starters(),
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
        return view('livewire.chat', ['profiles' => $this->profileTabs()]);
    }

    /**
     * Enabled + available instruction tabs for the header switch (data-driven —
     * unavailable/disabled profiles are simply not shown).
     *
     * @return list<array{name: string, label: string, active: bool}>
     */
    private function profileTabs(): array
    {
        $tabs = [];

        foreach ((array) config('corpus.profiles') as $name => $cfg) {
            if (! is_array($cfg) || ! ($cfg['enabled'] ?? false) || ! $this->artifactAvailable((string) $name)) {
                continue;
            }

            $tabs[] = [
                'name' => (string) $name,
                'label' => (string) ($cfg['label'] ?? $name),
                'active' => $name === $this->profile,
            ];
        }

        return $tabs;
    }
}
