@php
    $geminiKey = (string) config('services.gemini.key', env('GEMINI_API_KEY', ''));
    $hfToken = (string) config('services.huggingface.token', '');
    $hfAssistantEnabled = $geminiKey !== '' || $hfToken !== '';
    $hfModel = $geminiKey !== '' 
        ? 'Google Gemini 1.5/2.5 Flash' 
        : (string) config('services.huggingface.model', 'meta-llama/Llama-3.1-8B-Instruct');
    $hasCriticalAlerts = (($unreadNotificationsCount ?? 0) > 0);
    $currentRouteName = request()->route()?->getName() ?? '';
    $authUser = auth()->user();
    $isPlatformAdmin = (bool) ($authUser?->is_platform_admin ?? false);
    $isAccountant = (bool) ($authUser?->is_accountant ?? false);
    $assistantTitle = $isPlatformAdmin ? 'Assistant IA Admin' : 'Assistant IA Finance';
    $assistantIntro = $isPlatformAdmin
        ? 'Bonjour 👋 Je suis ton copilote admin. Pose une question sur alertes, SLA/SLO, risques ou priorités.'
        : 'Bonjour 👋 Je peux analyser ta comptabilité et proposer des actions concrètes pour améliorer ton chiffre d’affaires.';
    $assistantPlaceholder = $isPlatformAdmin
        ? 'Ex: Que dois-je traiter en priorité ?'
        : 'Ex: Comment améliorer mon chiffre d’affaires ce mois-ci ?';
@endphp

<style>
    .admin-global-chat-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .25);
        z-index: 1052;
        opacity: 1;
        transition: opacity .18s ease;
    }
    .admin-global-chat-backdrop.is-hidden {
        opacity: 0;
        pointer-events: none;
    }
    .admin-global-chat-launcher {
        position: fixed;
        right: 22px;
        bottom: 22px;
        z-index: 1055;
        border: 0;
        border-radius: 999px;
        width: 58px;
        height: 58px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 .45rem 1.2rem rgba(59, 125, 221, .35);
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .admin-global-chat-launcher:hover {
        transform: translateY(-2px);
        box-shadow: 0 .7rem 1.4rem rgba(59, 125, 221, .4);
    }
    .admin-global-chat-launcher.is-hidden {
        display: none;
    }
    .admin-global-chat-window {
        position: fixed;
        right: 22px;
        bottom: 92px;
        z-index: 1054;
        width: min(410px, calc(100vw - 28px));
        height: min(72vh, 620px);
        border-radius: .95rem;
        overflow: hidden;
        box-shadow: 0 .9rem 2rem rgba(0, 0, 0, .22);
        background: #fff;
        border: 1px solid rgba(0,0,0,.08);
        opacity: 1;
        transform: translateY(0) scale(1);
        transform-origin: right bottom;
        transition: opacity .18s ease, transform .18s ease;
    }
    .admin-global-chat-window.is-hidden {
        opacity: 0;
        transform: translateY(8px) scale(.98);
        pointer-events: none;
    }
    .admin-global-chat-head {
        padding: .8rem .95rem;
        background: #fff;
        border-bottom: 1px solid #eef1f4;
    }
    .admin-global-chat-title {
        display: flex;
        align-items: center;
        gap: .4rem;
        font-weight: 600;
    }
    .admin-global-chat-online-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: #20c997;
        box-shadow: 0 0 0 3px rgba(32, 201, 151, .15);
    }
    .admin-global-chat-body {
        height: calc(100% - 170px);
        overflow-y: auto;
        background: linear-gradient(180deg, #f8fafc 0%, #f6f8fb 100%);
        padding: .75rem .8rem;
    }
    .admin-global-chat-row {
        display: flex;
        margin-bottom: .65rem;
        gap: .45rem;
        animation: adminGlobalBubbleIn .18s ease;
    }
    .admin-global-chat-row.user {
        justify-content: flex-end;
    }
    .admin-global-chat-avatar {
        width: 28px;
        height: 28px;
        border-radius: 999px;
        background: #3b7ddd;
        color: #fff;
        font-size: .72rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }
    .admin-global-chat-row.user .admin-global-chat-avatar {
        background: #6c757d;
    }
    .admin-global-chat-bubble-wrap {
        max-width: 82%;
    }
    .admin-global-chat-bubble {
        display: inline-block;
        border-radius: .75rem;
        padding: .5rem .7rem;
        font-size: .88rem;
        line-height: 1.35;
        white-space: pre-wrap;
        word-break: break-word;
        border: 1px solid rgba(0, 0, 0, .08);
        background: #fff;
        color: #2b3035;
    }
    .admin-global-chat-row.user .admin-global-chat-bubble {
        background: #3b7ddd;
        color: #fff;
        border-color: #3b7ddd;
        border-top-right-radius: .25rem;
    }
    .admin-global-chat-row.assistant .admin-global-chat-bubble {
        border-top-left-radius: .25rem;
    }
    .admin-global-chat-row.system .admin-global-chat-bubble {
        background: rgba(255,193,7,.18);
        border-color: rgba(255,193,7,.45);
        color: #6b5500;
    }
    .admin-global-chat-time {
        display: block;
        font-size: .68rem;
        color: #8b93a1;
        margin-top: .16rem;
    }
    .admin-global-chat-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .4rem;
    }
    .admin-global-chat-status {
        font-size: .68rem;
        font-weight: 600;
        color: #6c757d;
    }
    .admin-global-chat-row.user .admin-global-chat-status {
        color: rgba(255, 255, 255, .85);
    }
    .admin-global-chat-row.user .admin-global-chat-time {
        text-align: right;
    }
    .admin-global-chat-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 19px;
        height: 19px;
        border-radius: 999px;
        padding: 0 .35rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .68rem;
        font-weight: 700;
        background: #dc3545;
        color: #fff;
        border: 2px solid #fff;
    }
    .admin-global-chat-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        padding: .45rem .7rem .35rem;
        border-top: 1px solid #eef1f4;
        background: #fff;
    }
    .admin-global-chat-chip {
        border: 1px solid rgba(0,0,0,.12);
        background: #fff;
        border-radius: 999px;
        font-size: .74rem;
        padding: .2rem .55rem;
        color: #495057;
        transition: border-color .15s ease, color .15s ease, background .15s ease;
    }
    .admin-global-chat-chip:hover {
        border-color: rgba(59, 125, 221, .5);
        color: #3b7ddd;
        background: #f5f9ff;
    }
    .admin-global-chat-input {
        border-top: 1px solid #eef1f4;
        padding: .55rem .65rem;
        background: #fff;
    }
    .admin-global-chat-input textarea {
        resize: none;
    }
    .admin-global-typing-dots span {
        animation: adminGlobalDots 1.15s infinite;
        display: inline-block;
        opacity: .4;
    }
    .admin-global-typing-dots span:nth-child(2) { animation-delay: .2s; }
    .admin-global-typing-dots span:nth-child(3) { animation-delay: .4s; }
    @keyframes adminGlobalDots {
        0%, 100% { opacity: .3; transform: translateY(0); }
        50% { opacity: 1; transform: translateY(-2px); }
    }
    @keyframes adminGlobalBubbleIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .gemini-chat-spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        vertical-align: text-bottom;
        border: 0.15em solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        animation: gemini-chat-spinner-anim .75s linear infinite;
    }
    @keyframes gemini-chat-spinner-anim {
        to { transform: rotate(360deg); }
    }
    @media (max-width: 992px) {
        .admin-global-chat-window {
            width: min(400px, calc(100vw - 20px));
            right: 10px;
            bottom: 80px;
            height: min(60vh, 520px);
        }
        .admin-global-chat-launcher {
            right: 12px;
            bottom: 14px;
            width: 50px;
            height: 50px;
        }
    }
    @media (max-width: 576px) {
        /* Chatbot compact sur mobile — panneau bas */
        body.admin-chat-open {
            overflow: hidden;
        }
        .admin-global-chat-window {
            right: 0;
            left: 0;
            bottom: 0;
            width: 100vw;
            height: 50vh;          /* Compact sur mobile ; dvh ci-dessous prend le relais si le clavier est ouvert */
            height: 50dvh;
            max-height: 420px;
            min-height: 280px;
            border-radius: 1rem 1rem 0 0;
            border-left: 0;
            border-right: 0;
            border-bottom: 0;
            transform-origin: center bottom;
        }
        .admin-global-chat-window.is-hidden {
            transform: translateY(24px) scale(1);
        }
        /* Launcher plus petit et repositionné */
        .admin-global-chat-launcher {
            right: 12px;
            bottom: 12px;
            width: 46px;
            height: 46px;
        }
        /* Body du chat réduit */
        .admin-global-chat-body {
            height: calc(100% - 160px);
        }
        /* Chips en scroll horizontal */
        .admin-global-chat-actions {
            overflow-x: auto;
            white-space: nowrap;
            flex-wrap: nowrap;
            scrollbar-width: thin;
            padding: 0.35rem 0.65rem;
        }
        /* Bulles plus larges */
        .admin-global-chat-bubble-wrap {
            max-width: 90%;
        }
        /* Header compact */
        .admin-global-chat-head {
            padding: 0.55rem 0.85rem;
        }
        .admin-global-chat-head small {
            display: none; /* Masquer le sous-titre du modèle IA */
        }
        /* Boutons header réduits */
        .admin-global-chat-head .btn-sm {
            padding: 0.2rem 0.5rem;
            font-size: 0.72rem;
        }
        /* Zone input compacte */
        .admin-global-chat-input {
            padding: 0.4rem 0.5rem;
        }
        .admin-global-chat-input textarea {
            font-size: 15px !important;
            rows: 1;
        }
    }

</style>

<div id="adminGlobalChatBackdrop" class="admin-global-chat-backdrop is-hidden" aria-hidden="true"></div>

<button id="adminGlobalChatLauncher" type="button" class="btn btn-primary admin-global-chat-launcher" title="{{ $assistantTitle }}">
    <i data-feather="message-circle" style="width:22px;height:22px;"></i>
    <span id="adminGlobalChatBadge" class="admin-global-chat-badge">{{ $hasCriticalAlerts ? '!' : 'i' }}</span>
</button>

<div id="adminGlobalChatWindow" class="admin-global-chat-window is-hidden" aria-live="polite" aria-label="{{ $assistantTitle }}">
    <div class="admin-global-chat-head d-flex justify-content-between align-items-center">
        <div class="min-w-0" style="overflow: hidden;">
            <div class="admin-global-chat-title text-truncate">
                <span class="admin-global-chat-online-dot"></span>
                <span>{{ $assistantTitle }}</span>
            </div>
            <small class="text-muted text-truncate d-block">
                @if($hfAssistantEnabled)
                    {{ $hfModel }}
                @else
                    Inactif (Clé GEMINI_API_KEY manquante)
                @endif
            </small>
        </div>
        <div class="d-flex gap-1 flex-shrink-0 ms-2">
            <button id="adminGlobalChatClearBtn" type="button" class="btn btn-sm btn-light border" title="Vider la conversation">
                <i data-feather="trash-2" style="width:14px;height:14px;"></i>
            </button>
            <button id="adminGlobalChatMinimizeBtn" type="button" class="btn btn-sm btn-light border" title="Minimiser">
                <i data-feather="minus" style="width:14px;height:14px;"></i>
            </button>
            <button id="adminGlobalChatCloseBtn" type="button" class="btn btn-sm btn-light border" title="Fermer">
                <i data-feather="x" style="width:14px;height:14px;"></i>
            </button>
        </div>
    </div>
    <div id="adminGlobalChatMessages" class="admin-global-chat-body"></div>
    <div id="adminGlobalChatActions" class="admin-global-chat-actions"></div>
    <div class="admin-global-chat-input">
        <div class="input-group">
            <textarea id="adminGlobalChatInput" class="form-control" rows="2" placeholder="{{ $assistantPlaceholder }}" @disabled(!$hfAssistantEnabled)></textarea>
            <button id="adminGlobalChatSendBtn" class="btn btn-primary" type="button" @disabled(!$hfAssistantEnabled)>Envoyer</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const enabled = @json((bool) $hfAssistantEnabled);
    const isPlatformAdmin = @json($isPlatformAdmin);
    const isAccountant = @json($isAccountant);
    const introMessage = @json($assistantIntro);
    const routeName = @json($currentRouteName);
    const launcher = document.getElementById('adminGlobalChatLauncher');
    const badge = document.getElementById('adminGlobalChatBadge');
    const backdrop = document.getElementById('adminGlobalChatBackdrop');
    const chatWindow = document.getElementById('adminGlobalChatWindow');
    const closeBtn = document.getElementById('adminGlobalChatCloseBtn');
    const minimizeBtn = document.getElementById('adminGlobalChatMinimizeBtn');
    const clearBtn = document.getElementById('adminGlobalChatClearBtn');
    const actionsBox = document.getElementById('adminGlobalChatActions');
    const input = document.getElementById('adminGlobalChatInput');
    const sendBtn = document.getElementById('adminGlobalChatSendBtn');
    const box = document.getElementById('adminGlobalChatMessages');
    const csrf = @json(csrf_token());
    const endpoint = @json(route('ai.business.chat'));
    const storageKey = 'admin-global-hf-chat-history-v2';
    const MAX_HISTORY = 14;

    const escapeHtml = (text) => String(text).replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const nowIso = () => new Date().toISOString();
    const formatTime = (iso) => {
        const date = iso ? new Date(iso) : new Date();
        if (Number.isNaN(date.getTime())) return '';
        return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    };

    const readHistory = () => {
        try {
            const raw = localStorage.getItem(storageKey);
            if (!raw) return [];
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed)
                ? parsed.filter((m) => m && (m.role === 'user' || m.role === 'assistant') && typeof m.content === 'string')
                    .map((m) => ({
                        role: m.role,
                        content: m.content,
                        ts: m.ts || nowIso(),
                        status: (m.status === 'vu' || m.status === 'envoye') ? m.status : (m.role === 'user' ? 'envoye' : null)
                    }))
                : [];
        } catch (_) {
            return [];
        }
    };

    const writeHistory = (history) => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(history.slice(-MAX_HISTORY)));
        } catch (_) {}
    };

    const createRow = (role, text, ts, status) => {
        const row = document.createElement('div');
        row.className = 'admin-global-chat-row ' + role;
        const avatarLabel = role === 'user' ? 'Moi' : 'IA';
        const statusNode = role === 'user'
            ? '<span class="admin-global-chat-status">' + escapeHtml(status === 'vu' ? 'vu' : 'envoyé') + '</span>'
            : '';
        row.innerHTML =
            '<span class="admin-global-chat-avatar">' + avatarLabel + '</span>' +
            '<div class="admin-global-chat-bubble-wrap">' +
                '<span class="admin-global-chat-bubble">' + escapeHtml(text) + '</span>' +
                '<div class="admin-global-chat-meta">' +
                    '<small class="admin-global-chat-time">' + escapeHtml(formatTime(ts)) + '</small>' +
                    statusNode +
                '</div>' +
            '</div>';
        return row;
    };

    let history = readHistory();

    const renderHistory = () => {
        if (!box) return;
        box.innerHTML = '';
        if (history.length === 0) {
            box.appendChild(createRow('assistant', introMessage, nowIso(), null));
            if (!enabled) {
                const disabledRow = document.createElement('div');
                disabledRow.className = 'admin-global-chat-row system';
                disabledRow.innerHTML =
                    '<span class="admin-global-chat-avatar">i</span>' +
                    '<div class="admin-global-chat-bubble-wrap">' +
                        '<span class="admin-global-chat-bubble">Configure <code>HUGGINGFACE_TOKEN</code> dans <code>.env</code> pour activer le chat.</span>' +
                    '</div>';
                box.appendChild(disabledRow);
            }
        } else {
            history.forEach((m) => box.appendChild(createRow(m.role, m.content, m.ts, m.status || null)));
        }
        box.scrollTop = box.scrollHeight;
    };

    const promptCatalog = {
        default: [
            { label: 'Plan CA 30j', prompt: "Donne-moi un plan en 5 étapes pour augmenter mon chiffre d'affaires sur 30 jours." },
            { label: 'Analyse comptable', prompt: 'Analyse ma comptabilité récente et détecte les points de blocage.' },
            { label: 'Actions prioritaires', prompt: 'Propose 3 actions prioritaires, avec KPI et délai.' },
        ],
        accounting: [
            { label: 'Marge & charges', prompt: 'Analyse les charges et propose comment améliorer la marge.' },
            { label: 'Trésorerie', prompt: 'Analyse ma trésorerie et donne un plan d’amélioration de cashflow.' },
            { label: 'Facturation', prompt: 'Comment augmenter mes encaissements sans augmenter mes risques ?' },
        ],
        accountant: [
            { label: 'Diagnostic client', prompt: 'Fais un diagnostic financier du dossier client actif.' },
            { label: 'Plan CA client', prompt: "Propose un plan concret pour améliorer le chiffre d'affaires du client." },
            { label: 'Risques comptables', prompt: 'Quels sont les risques comptables prioritaires et les corrections ?' },
        ],
        ops: [
            { label: 'SLA/SLO', prompt: 'Analyse les SLA/SLO et donne les points de rupture.' },
            { label: 'Incidents', prompt: 'Priorise les incidents avec impact élevé.' },
            { label: 'Runbook', prompt: 'Quel runbook lancer maintenant avec justification ?' },
        ],
        dashboard: [
            { label: 'Heatmap', prompt: 'Interprète la heatmap des risques et propose 3 actions.' },
            { label: 'Timeline', prompt: 'Résume la timeline incidents sur les 24 dernières heures.' },
            { label: 'Actions jour', prompt: 'Optimise la liste des actions du jour par urgence.' },
        ],
        scoring: [
            { label: 'Seuils clés', prompt: 'Quels seuils scoring dois-je ajuster en priorité ?' },
            { label: 'Impact décision', prompt: 'Quel impact métier si je durcis les seuils de décision ?' },
            { label: 'Cohérence', prompt: 'Vérifie la cohérence globale des poids de scoring.' },
        ],
    };

    const getPromptGroup = () => {
        if (isPlatformAdmin) {
            if (routeName.startsWith('admin.ops')) return promptCatalog.ops;
            if (routeName.startsWith('admin.scoring')) return promptCatalog.scoring;
            if (routeName === 'admin' || routeName.startsWith('admin.dashboard')) return promptCatalog.dashboard;
            return promptCatalog.default;
        }
        if (isAccountant) {
            return promptCatalog.accountant;
        }
        if (routeName.startsWith('accounting') || routeName.startsWith('treasury')) {
            return promptCatalog.accounting;
        }
        if (routeName.startsWith('admin.ops')) return promptCatalog.ops;
        if (routeName.startsWith('admin.scoring')) return promptCatalog.scoring;
        if (routeName === 'admin' || routeName.startsWith('admin.dashboard')) return promptCatalog.dashboard;
        return promptCatalog.default;
    };

    const renderPromptChips = () => {
        if (!actionsBox) return;
        actionsBox.innerHTML = '';
        getPromptGroup().forEach((item) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'admin-global-chat-chip';
            btn.setAttribute('data-prompt', item.prompt);
            btn.textContent = item.label;
            actionsBox.appendChild(btn);
        });
    };

    const openChat = () => {
        if (!chatWindow) return;
        chatWindow.classList.remove('is-hidden');
        if (backdrop) backdrop.classList.remove('is-hidden');
        if (launcher) launcher.classList.add('is-hidden');
        document.body.classList.add('admin-chat-open');
        renderHistory();
        if (badge) badge.style.display = 'none';
        if (input) input.focus();
    };

    const closeChat = () => {
        if (!chatWindow) return;
        chatWindow.classList.add('is-hidden');
        if (backdrop) backdrop.classList.add('is-hidden');
        if (launcher) launcher.classList.remove('is-hidden');
        document.body.classList.remove('admin-chat-open');
    };

    if (launcher && chatWindow) {
        launcher.addEventListener('click', function () {
            if (chatWindow.classList.contains('is-hidden')) {
                openChat();
            } else {
                closeChat();
            }
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeChat);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && chatWindow && !chatWindow.classList.contains('is-hidden')) {
            closeChat();
        }
    });

    if (closeBtn) closeBtn.addEventListener('click', closeChat);

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            history = [];
            writeHistory(history);
            renderHistory();
        });
    }

    if (minimizeBtn) {
        minimizeBtn.addEventListener('click', closeChat);
    }

    if (actionsBox) {
        actionsBox.addEventListener('click', function (event) {
            const chip = event.target.closest('.admin-global-chat-chip');
            if (!chip) return;
            if (!input) return;
            input.value = chip.getAttribute('data-prompt') || '';
            input.focus();
        });
    }

    if (!enabled) {
        renderPromptChips();
        renderHistory();
        return;
    }

    const markLastUserMessageAsRead = () => {
        for (let i = history.length - 1; i >= 0; i -= 1) {
            if (history[i].role === 'user') {
                history[i].status = 'vu';
                return;
            }
        }
    };

    const appendMessage = (role, text, ts, status) => {
        const row = createRow(role, text, ts, status || null);
        box.appendChild(row);
        box.scrollTop = box.scrollHeight;
        return row;
    };

    const send = async () => {
        const message = (input?.value || '').trim();
        if (!message || !box || !sendBtn) return;

        const userTs = nowIso();
        appendMessage('user', message, userTs, 'envoye');
        history.push({ role: 'user', content: message, ts: userTs, status: 'envoye' });
        writeHistory(history);
        input.value = '';
        sendBtn.disabled = true;
        if (input) input.disabled = true;

        const pendingTs = nowIso();
        const placeholder = appendMessage('assistant', 'L\'assistant prépare une réponse...', pendingTs, null);
        const bubble = placeholder.querySelector('.admin-global-chat-bubble');
        if (bubble) {
            bubble.innerHTML = '<div style="display: flex; align-items: center; gap: 8px;">' +
                '<span class="gemini-chat-spinner" style="color: #3b7ddd; flex-shrink: 0;"></span>' +
                '<span>L\'assistant prépare une réponse...</span>' +
                '</div>';
        }

        // Minuteurs UX de progression pour tenir l'utilisateur informé en direct
        const progressTimer1 = setTimeout(() => {
            if (bubble && sendBtn.disabled) {
                bubble.innerHTML = '<div style="display: flex; align-items: center; gap: 8px;">' +
                    '<span class="gemini-chat-spinner" style="color: #fd7e14; flex-shrink: 0;"></span>' +
                    '<span>Nouvelle tentative en cours (forte sollicitation)...</span>' +
                    '</div>';
            }
        }, 4000);

        const progressTimer2 = setTimeout(() => {
            if (bubble && sendBtn.disabled) {
                bubble.innerHTML = '<div style="display: flex; align-items: center; gap: 8px;">' +
                    '<span class="gemini-chat-spinner" style="color: #dc3545; flex-shrink: 0;"></span>' +
                    '<span>Recherche d\'un modèle alternatif disponible...</span>' +
                    '</div>';
            }
        }, 12000);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    message,
                    history: history.slice(-10).map((m) => ({ role: m.role, content: m.content }))
                })
            });

            const json = await response.json();
            if (!response.ok || !json.ok) throw new Error(json.error || 'Le service IA est momentanément indisponible.');

            const answer = String(json.answer || '');
            const answerTs = nowIso();
            if (bubble) {
                bubble.textContent = answer;
            }
            const timeEl = placeholder.querySelector('.admin-global-chat-time');
            if (timeEl) {
                timeEl.textContent = formatTime(answerTs);
            }
            markLastUserMessageAsRead();
            history.push({ role: 'assistant', content: answer, ts: answerTs, status: null });
            writeHistory(history);
            renderHistory();
        } catch (error) {
            if (bubble) {
                bubble.textContent = String(error?.message || error);
                bubble.style.color = '#dc3545';
                bubble.style.backgroundColor = '#fff5f5';
                bubble.style.borderColor = '#ffc9c9';
            }
        } finally {
            clearTimeout(progressTimer1);
            clearTimeout(progressTimer2);
            sendBtn.disabled = false;
            if (input) {
                input.disabled = false;
                input.focus();
            }
            box.scrollTop = box.scrollHeight;
        }
    };

    if (sendBtn) sendBtn.addEventListener('click', send);
    if (input) {
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                send();
            }
        });
    }

    renderPromptChips();

    if (window.feather) {
        window.feather.replace();
    }
});
</script>
@endpush

