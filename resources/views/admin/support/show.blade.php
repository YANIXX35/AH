@extends('layouts.app')

@section('title', 'Ticket support | Admin')
@section('page_title', 'Ticket support')

@push('styles')
<style>
    .admin-support-thread {
        max-height: 58vh;
        overflow-y: auto;
    }
    .admin-support-msg {
        border-radius: .75rem;
        padding: .7rem .8rem;
        max-width: 85%;
        border: 1px solid rgba(0,0,0,.06);
    }
    .admin-support-msg.staff {
        background: #eef5ff;
        border-color: rgba(59,125,221,.22);
    }
    .admin-support-msg.client {
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
    <div class="mb-3">
        <a href="{{ route('admin.support.tickets.index') }}" class="text-muted small text-decoration-none">← Retour à la file support</a>
        <h1 class="h4 mt-2 mb-1">{{ $ticket->subject }}</h1>
        <p class="small text-muted mb-0">Client: {{ $ticket->user?->name }} ({{ $ticket->user?->email }})</p>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.support.tickets.assign', $ticket) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-8">
                    <label class="form-label small mb-1">Attribuer à</label>
                    <select name="assigned_to_user_id" class="form-select form-select-sm @error('assigned_to_user_id') is-invalid @enderror" required>
                        @foreach($assignableUsers as $u)
                            <option value="{{ $u->id }}" @selected((int) ($ticket->assigned_to_user_id ?? 0) === (int) $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                    @error('assigned_to_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 d-grid">
                    <button class="btn btn-sm btn-primary" type="submit">Attribuer le ticket</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <span class="small text-muted">Conversation client</span>
            <span class="small text-muted" id="admin-support-live-indicator">Temps reel (actualisation 2s)</span>
        </div>
        <div class="card-body admin-support-thread" id="admin-support-thread">
            @forelse($ticket->messages as $msg)
                <div class="mb-3 d-flex {{ $msg->is_staff_reply ? 'justify-content-end' : 'justify-content-start' }}" data-message-id="{{ $msg->id }}">
                    <div class="admin-support-msg {{ $msg->is_staff_reply ? 'staff' : 'client' }}">
                        <div class="d-flex justify-content-between align-items-center mb-1 gap-2">
                            <strong class="small {{ $msg->is_staff_reply ? 'text-primary' : 'text-dark' }}">
                                @if($msg->is_staff_reply)
                                    {{ $msg->user?->name ?? 'Staff' }} (support)
                                @else
                                    {{ $msg->user?->name ?? 'Client' }}
                                @endif
                            </strong>
                            <span class="text-muted small">{{ $msg->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="small text-body" style="white-space: pre-wrap;">{{ $msg->body }}</div>
                        @if($msg->is_staff_reply)
                            <div class="support-meta mt-1">
                                @php($state = $msg->deliveryState())
                                <span data-receipt-for="{{ $msg->id }}">{{ $state === 'read' ? '✓✓ Lu par client' : ($state === 'delivered' ? '✓✓ Recu par client' : '✓ Envoye') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-muted small">Aucun message pour l’instant.</div>
            @endforelse
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.support.tickets.messages.store', $ticket) }}">
                @csrf
                <label class="form-label">Répondre au client</label>
                <div class="emoji-bar mb-1" id="admin-support-emoji-bar">
                    <button type="button" data-emoji="🙂">🙂</button>
                    <button type="button" data-emoji="👍">👍</button>
                    <button type="button" data-emoji="🙏">🙏</button>
                    <button type="button" data-emoji="✅">✅</button>
                    <button type="button" data-emoji="🔥">🔥</button>
                    <button type="button" data-emoji="🎉">🎉</button>
                </div>
                <textarea name="message" rows="4" class="form-control @error('message') is-invalid @enderror" required placeholder="Votre réponse support">{{ old('message') }}</textarea>
                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="text-end mt-2">
                    <button type="submit" class="btn btn-primary btn-sm">Envoyer la réponse</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const threadEl = document.getElementById('admin-support-thread');
        const indicatorEl = document.getElementById('admin-support-live-indicator');
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
            if (state === 'read') return '✓✓ Lu par client';
            if (state === 'delivered') return '✓✓ Recu par client';
            return '✓ Envoye';
        };

        const appendMessage = (message) => {
            const messageId = Number(message.id || 0);
            if (!messageId || seenMessageIds.has(messageId)) {
                return;
            }
            seenMessageIds.add(messageId);

            const wrapper = document.createElement('div');
            wrapper.className = `mb-3 d-flex ${message.is_staff_reply ? 'justify-content-end' : 'justify-content-start'}`;
            wrapper.setAttribute('data-message-id', String(messageId));
            wrapper.innerHTML = `
                <div class="admin-support-msg ${message.is_staff_reply ? 'staff' : 'client'}">
                    <div class="d-flex justify-content-between align-items-center mb-1 gap-2">
                        <strong class="small ${message.is_staff_reply ? 'text-primary' : 'text-dark'}">${escapeHtml(message.author || 'Message')}</strong>
                        <span class="text-muted small">${escapeHtml(message.created_at || '')}</span>
                    </div>
                    <div class="small text-body" style="white-space: pre-wrap;">${escapeHtml(message.body || '')}</div>
                    ${message.is_staff_reply ? `<div class="support-meta mt-1"><span data-receipt-for="${messageId}">${escapeHtml(toDeliveryLabel(message.delivery_state || 'sent'))}</span></div>` : ''}
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
                const response = await fetch(`{{ route('admin.support.tickets.feed', $ticket) }}?after_id=${lastMessageId}`, {
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
                    indicatorEl.textContent = 'Connexion temps reel instable';
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
                await fetch(`{{ route('admin.support.tickets.read', $ticket) }}`, {
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
                source = new EventSource(`{{ route('admin.support.tickets.stream', $ticket) }}?after_id=${lastMessageId}`);

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

        const emojiBar = document.getElementById('admin-support-emoji-bar');
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

        const replyForm = document.querySelector('form[action="{{ route('admin.support.tickets.messages.store', $ticket) }}"]');
        if (replyForm) {
            replyForm.addEventListener('submit', function () {
                const submitButton = replyForm.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.setAttribute('disabled', 'disabled');
                    submitButton.textContent = 'Envoi...';
                }
            });
        }
    })();
</script>
@endpush
