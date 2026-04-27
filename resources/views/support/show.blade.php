@extends('layouts.app')

@section('title', $ticket->subject . ' | Support | ' . config('app.name'))
@section('page_title', 'Conversation')

@push('styles')
<style>
    .support-thread {
        max-height: 58vh;
        overflow-y: auto;
    }
    .support-msg {
        border-radius: .75rem;
        padding: .7rem .8rem;
        max-width: 85%;
        border: 1px solid rgba(0,0,0,.06);
    }
    .support-msg.staff {
        background: #eef5ff;
        border-color: rgba(59,125,221,.22);
    }
    .support-msg.user {
        background: #f8f9fa;
    }
    .support-meta {
        font-size: .72rem;
        color: #6c757d;
    }
    .emoji-bar button {
        border: 0;
        background: transparent;
        font-size: 1.1rem;
        line-height: 1;
        padding: .2rem .25rem;
        cursor: pointer;
    }
</style>
@endpush

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <a href="{{ route('support.tickets') }}" class="text-muted small text-decoration-none">← Mes demandes</a>
            <h1 class="h4 mt-2 mb-1">{{ $ticket->subject }}</h1>
            @php
                $st = match ($ticket->status) {
                    'open' => ['Ouvert', 'success'],
                    'in_progress' => ['En cours', 'warning'],
                    'closed' => ['Clos', 'secondary'],
                    default => [$ticket->status, 'light'],
                };
            @endphp
            <span class="badge bg-{{ $st[1] }}">{{ $st[0] }}</span>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <span class="small text-muted">Fil de discussion</span>
            <span class="small text-muted" id="support-live-indicator">Temps reel (actualisation 2s)</span>
        </div>
        <div class="card-body support-thread" id="support-thread">
            @foreach($ticket->messages as $msg)
                <div class="mb-3 d-flex {{ $msg->is_staff_reply ? 'justify-content-start' : 'justify-content-end' }}" data-message-id="{{ $msg->id }}">
                    <div class="support-msg {{ $msg->is_staff_reply ? 'staff' : 'user' }}">
                        <div class="d-flex justify-content-between align-items-center mb-1 gap-2">
                            <strong class="small {{ $msg->is_staff_reply ? 'text-primary' : 'text-dark' }}">
                                @if($msg->is_staff_reply)
                                    Équipe {{ config('app.name') }}
                                @else
                                    {{ $msg->user?->name ?? 'Vous' }}
                                @endif
                            </strong>
                            <span class="text-muted small">{{ $msg->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="text-body small" style="white-space: pre-wrap;">{{ $msg->body }}</div>
                        @if(! $msg->is_staff_reply)
                            <div class="support-meta mt-1">
                                @php($state = $msg->deliveryState())
                                <span data-receipt-for="{{ $msg->id }}">{{ $state === 'read' ? '✓✓ Lu' : ($state === 'delivered' ? '✓✓ Recu' : '✓ Envoye') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if($ticket->status !== 'closed')
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('support.tickets.messages.store', $ticket) }}">
                    @csrf
                    <label class="form-label">Ajouter un message</label>
                    <div class="emoji-bar mb-1" id="support-emoji-bar">
                        <button type="button" data-emoji="🙂">🙂</button>
                        <button type="button" data-emoji="👍">👍</button>
                        <button type="button" data-emoji="🙏">🙏</button>
                        <button type="button" data-emoji="✅">✅</button>
                        <button type="button" data-emoji="🔥">🔥</button>
                        <button type="button" data-emoji="🎉">🎉</button>
                    </div>
                    <textarea name="message" rows="4" class="form-control @error('message') is-invalid @enderror" required placeholder="Votre réponse">{{ old('message') }}</textarea>
                    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="mt-2 text-end">
                        <button type="submit" class="btn btn-primary btn-sm">Envoyer</button>
                    </div>
                </form>
            </div>
        </div>
    @else
        <p class="text-muted small">Ce fil est clos. <a href="{{ route('support.tickets.create') }}">Ouvrir une nouvelle demande</a>.</p>
    @endif
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const threadEl = document.getElementById('support-thread');
        const indicatorEl = document.getElementById('support-live-indicator');
        const messageInput = document.querySelector('textarea[name="message"]');
        if (!threadEl) {
            return;
        }

        let lastMessageId = Array.from(threadEl.querySelectorAll('[data-message-id]'))
            .map((el) => Number(el.getAttribute('data-message-id') || '0'))
            .reduce((max, current) => Math.max(max, current), 0);
        const seenMessageIds = new Set(
            Array.from(threadEl.querySelectorAll('[data-message-id]'))
                .map((el) => Number(el.getAttribute('data-message-id') || '0'))
                .filter((id) => id > 0)
        );

        threadEl.scrollTop = threadEl.scrollHeight;

        const escapeHtml = (value) => String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;');

        const toDeliveryLabel = (state) => {
            if (state === 'read') return '✓✓ Lu';
            if (state === 'delivered') return '✓✓ Recu';
            return '✓ Envoye';
        };

        const appendMessage = (message) => {
            const messageId = Number(message.id || 0);
            if (!messageId || seenMessageIds.has(messageId)) {
                return;
            }
            seenMessageIds.add(messageId);

            const wrapper = document.createElement('div');
            wrapper.className = `mb-3 d-flex ${message.is_staff_reply ? 'justify-content-start' : 'justify-content-end'}`;
            wrapper.setAttribute('data-message-id', String(messageId));
            wrapper.innerHTML = `
                <div class="support-msg ${message.is_staff_reply ? 'staff' : 'user'}">
                    <div class="d-flex justify-content-between align-items-center mb-1 gap-2">
                        <strong class="small ${message.is_staff_reply ? 'text-primary' : 'text-dark'}">${escapeHtml(message.author || 'Message')}</strong>
                        <span class="text-muted small">${escapeHtml(message.created_at || '')}</span>
                    </div>
                    <div class="text-body small" style="white-space: pre-wrap;">${escapeHtml(message.body || '')}</div>
                    ${message.is_staff_reply ? '' : `<div class="support-meta mt-1"><span data-receipt-for="${messageId}">${escapeHtml(toDeliveryLabel(message.delivery_state || 'sent'))}</span></div>`}
                </div>
            `;
            threadEl.appendChild(wrapper);
            threadEl.scrollTop = threadEl.scrollHeight;
        };

        const applyReceiptUpdates = (receipts) => {
            receipts.forEach((receipt) => {
                const id = Number(receipt.id || 0);
                if (!id) return;
                const el = threadEl.querySelector(`[data-receipt-for="${id}"]`);
                if (el) {
                    el.textContent = toDeliveryLabel(receipt.delivery_state || 'sent');
                }
            });
        };

        const refreshFeed = async () => {
            try {
                const response = await fetch(`{{ route('support.tickets.feed', $ticket) }}?after_id=${lastMessageId}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                const messages = Array.isArray(payload.messages) ? payload.messages : [];
                const receipts = Array.isArray(payload.receipts) ? payload.receipts : [];
                messages.forEach((message) => {
                    appendMessage(message);
                    lastMessageId = Math.max(lastMessageId, Number(message.id || 0));
                });
                applyReceiptUpdates(receipts);

                if (indicatorEl) {
                    indicatorEl.textContent = messages.length > 0
                        ? `${messages.length} nouveau(x) message(s)`
                        : 'Temps reel (actualisation 2s)';
                }
            } catch (error) {
                if (indicatorEl) {
                    indicatorEl.textContent = 'Rafraîchissement temporairement indisponible';
                }
            }
        };

        let pollTimer = null;
        const startPolling = () => {
            if (pollTimer) {
                return;
            }
            pollTimer = window.setInterval(refreshFeed, 2000);
        };

        const markRead = async () => {
            if (document.visibilityState !== 'visible') {
                return;
            }
            try {
                await fetch(`{{ route('support.tickets.read', $ticket) }}`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    credentials: 'same-origin',
                });
            } catch (e) {
                // noop
            }
        };

        if (window.EventSource) {
            let source = null;
            const connectSse = () => {
                if (source) {
                    source.close();
                }
                source = new EventSource(`{{ route('support.tickets.stream', $ticket) }}?after_id=${lastMessageId}`);

                source.addEventListener('messages', (event) => {
                    const payload = JSON.parse(event.data || '{}');
                    const messages = Array.isArray(payload.messages) ? payload.messages : [];
                    const receipts = Array.isArray(payload.receipts) ? payload.receipts : [];
                    messages.forEach((message) => {
                        appendMessage(message);
                        lastMessageId = Math.max(lastMessageId, Number(message.id || 0));
                    });
                    applyReceiptUpdates(receipts);
                    if (indicatorEl) {
                        indicatorEl.textContent = messages.length > 0
                            ? `${messages.length} nouveau(x) message(s)`
                            : 'Temps reel SSE actif';
                    }
                    markRead();
                });

                source.addEventListener('receipts', (event) => {
                    const payload = JSON.parse(event.data || '{}');
                    const receipts = Array.isArray(payload.receipts) ? payload.receipts : [];
                    applyReceiptUpdates(receipts);
                });

                source.onerror = () => {
                    if (indicatorEl) {
                        indicatorEl.textContent = 'SSE indisponible, fallback polling 2s';
                    }
                    source.close();
                    startPolling();
                    window.setTimeout(connectSse, 5000);
                };
            };

            connectSse();
        } else {
            startPolling();
        }

        document.addEventListener('visibilitychange', markRead);
        markRead();

        const emojiBar = document.getElementById('support-emoji-bar');
        if (emojiBar && messageInput) {
            emojiBar.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLButtonElement)) {
                    return;
                }
                const emoji = target.getAttribute('data-emoji');
                if (!emoji) return;
                const start = messageInput.selectionStart || messageInput.value.length;
                const end = messageInput.selectionEnd || messageInput.value.length;
                messageInput.value = messageInput.value.slice(0, start) + emoji + messageInput.value.slice(end);
                const pos = start + emoji.length;
                messageInput.setSelectionRange(pos, pos);
                messageInput.focus();
            });
        }

        const messageForm = document.querySelector('form[action="{{ route('support.tickets.messages.store', $ticket) }}"]');
        if (messageForm) {
            messageForm.addEventListener('submit', function () {
                const submitButton = messageForm.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.setAttribute('disabled', 'disabled');
                    submitButton.textContent = 'Envoi...';
                }
            });
        }
    })();
</script>
@endpush
