{{-- resources/views/livewire/chat.blade.php --}}
{{--
    Livewire: Chat

    Public assistant chat — bubbles + input. Styled by resources/scss/sections/_chat.scss
    (EliteAdmin bubbles painted with Landia variables).

    @see App\Livewire\Chat
--}}
<div class="chat-window">

    {{-- Header --}}
    <div class="chat-header">
        <span class="chat-header-title"><i class="bi bi-robot"></i> Asystent dokumentacji</span>
        <div class="chat-header-actions">
            {{-- Instruction switch: only when more than one instruction is available. --}}
            @if (count($profiles) > 1)
                <div class="chat-profile-switch" role="group" aria-label="Wybór instrukcji dokumentacji">
                    @foreach ($profiles as $tab)
                        <button type="button"
                            class="chat-profile-btn @if ($tab['active']) is-active @endif"
                            aria-pressed="{{ $tab['active'] ? 'true' : 'false' }}"
                            wire:click="switchProfile('{{ $tab['name'] }}')"
                            wire:loading.attr="disabled" wire:target="switchProfile">
                            @if ($tab['active'])<i class="bi bi-check-lg" aria-hidden="true"></i> @endif{{ $tab['label'] }}
                        </button>
                    @endforeach
                </div>
            @endif
            <button type="button" class="chat-reset-btn"
                wire:click="resetChat"
                wire:confirm="Zacząć nową rozmowę? Bieżąca zostanie zapisana w historii."
                wire:loading.attr="disabled" wire:target="resetChat">
                <i class="bi bi-arrow-clockwise"></i> Nowa rozmowa
            </button>
        </div>
    </div>

    {{-- Messages --}}
    <div class="chat-messages"
        x-data
        x-init="$el.scrollTop = $el.scrollHeight"
        @chat-updated.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
        <div class="message-date-divider"><span>Dzisiaj</span></div>

        @foreach ($messages as $message)
            @include('livewire.partials._message', [
                'message' => $message,
                'bubbleKey' => $message['id'] ?? 'seed-'.$loop->index,
            ])
        @endforeach
    </div>

    {{-- Input --}}
    <div class="chat-input">
        <form class="chat-input-wrapper" wire:submit="sendMessage">
            <div class="chat-input-field">
                <textarea
                    rows="1"
                    placeholder="Zadaj pytanie o dokumentację KINGS..."
                    wire:model="question"
                    @keydown.enter.prevent="$event.shiftKey || $wire.sendMessage()"></textarea>
            </div>
            <button type="submit" class="chat-send-btn" title="Wyślij" wire:loading.attr="disabled">
                <i class="bi bi-send"></i>
            </button>
        </form>
        @error('question')
            <p class="text-danger small mt-2 mb-0 px-2">{{ $message }}</p>
        @enderror
    </div>

</div>
