{{-- resources/views/livewire/partials/_message.blade.php --}}
{{--
    Single chat message bubble.

    Expects:
      $message   = ['id','role','text','time','sources','rating']
                   sources = list of ['answer_unit_id','canonical_url']
      $bubbleKey = stable key for Livewire diffing
    @see App\Livewire\Chat
--}}
@php($isUser = ($message['role'] ?? 'assistant') === 'user')
@php($id = $message['id'] ?? null)
@php($rating = $message['rating'] ?? null)
@php($sources = $message['sources'] ?? [])

<div wire:key="msg-{{ $bubbleKey }}" class="message-group {{ $isUser ? 'sent' : 'received' }}">
    <div class="message-content">
        <div class="message-bubble">
            {{-- Assistant content is rendered escaped (never raw model/doc HTML). --}}
            {!! nl2br(e($message['text'])) !!}

            @if (! $isUser && ! empty($sources))
                <div class="mt-2 d-flex flex-column gap-1">
                    @foreach ($sources as $source)
                        <a href="{{ $source['canonical_url'] }}" target="_blank" rel="noopener"
                            class="d-inline-flex align-items-center gap-1 small">
                            <i class="bi bi-link-45deg"></i> Źródło w dokumentacji
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($isUser)
            <span class="message-time">{{ $message['time'] }}</span>
        @else
            <div class="d-flex align-items-center gap-2 px-2">
                <span class="message-time">{{ $message['time'] }}</span>

                @if ($id)
                    <button type="button"
                        class="btn btn-sm btn-link p-0 {{ $rating === 'up' ? 'text-primary' : 'text-secondary' }}"
                        title="Pomocne"
                        wire:click="rate({{ $id }}, 'up')"
                        wire:loading.attr="disabled">
                        <i class="bi {{ $rating === 'up' ? 'bi-hand-thumbs-up-fill' : 'bi-hand-thumbs-up' }}"></i>
                    </button>
                    <button type="button"
                        class="btn btn-sm btn-link p-0 {{ $rating === 'down' ? 'text-danger' : 'text-secondary' }}"
                        title="Niepomocne"
                        wire:click="rate({{ $id }}, 'down')"
                        wire:loading.attr="disabled">
                        <i class="bi {{ $rating === 'down' ? 'bi-hand-thumbs-down-fill' : 'bi-hand-thumbs-down' }}"></i>
                    </button>
                @endif
            </div>
        @endif
    </div>
</div>
