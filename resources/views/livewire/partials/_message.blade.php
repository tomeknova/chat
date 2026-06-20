{{-- resources/views/livewire/partials/_message.blade.php --}}
{{--
    Single chat message bubble. Reused for every message in the conversation.

    Expects: $message = ['role','text','time','link','covered']
    @see App\Livewire\Chat
--}}
@php($isUser = ($message['role'] ?? 'assistant') === 'user')

<div class="message-group {{ $isUser ? 'sent' : 'received' }}">
    <div class="message-content">
        <div class="message-bubble">
            {{ $message['text'] }}

            @if (! $isUser && ! empty($message['link']))
                <a href="{{ $message['link'] }}" class="d-inline-flex align-items-center gap-1 mt-2 small">
                    <i class="bi bi-link-45deg"></i> Źródło w dokumentacji
                </a>
            @endif
        </div>

        @if ($isUser)
            <span class="message-time">{{ $message['time'] }}</span>
        @else
            <div class="d-flex align-items-center gap-2 px-2">
                <span class="message-time">{{ $message['time'] }}</span>
                <button type="button" class="btn btn-sm btn-link p-0 text-secondary" title="Pomocne">
                    <i class="bi bi-hand-thumbs-up"></i>
                </button>
                <button type="button" class="btn btn-sm btn-link p-0 text-secondary" title="Niepomocne">
                    <i class="bi bi-hand-thumbs-down"></i>
                </button>
            </div>
        @endif
    </div>
</div>
