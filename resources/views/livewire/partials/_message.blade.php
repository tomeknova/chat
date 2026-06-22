{{-- resources/views/livewire/partials/_message.blade.php --}}
{{--
    Single chat message bubble.

    Expects:
      $message   = ['id','role','text','time','sources','suggestions','rating']
                   sources     = list of ['answer_unit_id','title','canonical_url']
                   suggestions = list of question strings (starter / recovery chips)
      $bubbleKey = stable key for Livewire diffing
    @see App\Livewire\Chat
--}}
@php($isUser = ($message['role'] ?? 'assistant') === 'user')
@php($id = $message['id'] ?? null)
@php($rating = $message['rating'] ?? null)
@php($sources = $message['sources'] ?? [])
@php($suggestions = $message['suggestions'] ?? [])

<div wire:key="msg-{{ $bubbleKey }}" class="message-group {{ $isUser ? 'sent' : 'received' }}">
    <div class="message-content">
        <div class="message-bubble">
            @if ($isUser)
                {!! nl2br(e($message['text'])) !!}
            @else
                {{-- Doc Markdown → HTML; raw HTML in docs is escaped (html_input=escape). --}}
                <div class="answer-content">{!! \Illuminate\Support\Str::markdown($message['text'], ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>
            @endif

            @if (! $isUser && ! empty($sources))
                <div class="mt-2 d-flex flex-column gap-1">
                    @foreach ($sources as $source)
                        <a href="{{ $source['canonical_url'] }}" target="_blank" rel="noopener"
                            class="d-inline-flex align-items-center gap-1 small">
                            <i class="bi bi-link-45deg"></i> Źródło: {{ $source['title'] ?? 'dokumentacja' }}
                        </a>
                    @endforeach
                </div>
            @endif

            @if (! $isUser && ! empty($suggestions))
                <div class="suggestion-chips mt-2 d-flex flex-wrap gap-2">
                    @foreach ($suggestions as $suggestion)
                        <button type="button" class="suggestion-chip"
                            wire:click='ask(@js($suggestion))' wire:loading.attr="disabled">
                            {{ $suggestion }}
                        </button>
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
                        x-on:click="$el.classList.remove('pop'); void $el.offsetWidth; $el.classList.add('pop')"
                        class="rating-btn btn btn-sm btn-link p-0 {{ $rating === 'up' ? 'is-active' : '' }}"
                        title="Pomocne"
                        wire:click="rate({{ $id }}, 'up')"
                        wire:loading.attr="disabled">
                        <i class="bi {{ $rating === 'up' ? 'bi-hand-thumbs-up-fill' : 'bi-hand-thumbs-up' }}"></i>
                    </button>
                    <button type="button"
                        x-on:click="$el.classList.remove('pop'); void $el.offsetWidth; $el.classList.add('pop')"
                        class="rating-btn btn btn-sm btn-link p-0 {{ $rating === 'down' ? 'is-active' : '' }}"
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
