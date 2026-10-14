
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAPID AI Office</title>

    <style>
        :root {
            --navy: #111827;
            --navy-light: #1f2937;
            --orange: #f97316;
            --orange-hover: #ea580c;
            --page: #f4f6fa;
            --muted: #64748b;
            --border: #e5e7eb;
        }

        * { box-sizing: border-box; }

        html, body {
            width: 100%;
            height: 100%;
            margin: 0;
        }

        body {
            font-family: Inter, -apple-system, BlinkMacSystemFont,
                "Segoe UI", Arial, sans-serif;
            background: var(--page);
            color: #1e293b;
            font-size: 14px;
        }

        button { font: inherit; }

        .app {
            display: flex;
            width: 100%;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
        }

        .sidebar {
            display: flex;
            flex: 0 0 280px;
            flex-direction: column;
            min-width: 0;
            padding: 23px 16px 14px;
            background: var(--navy);
            color: white;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px;
            margin-bottom: 28px;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 43px;
            height: 43px;
            border-radius: 12px;
            background: var(--orange);
            color: white;
            font-size: 18px;
            font-weight: 800;
        }

        .brand-copy { min-width: 0; }

        .brand-name { font-size: 16px; font-weight: 750; }

        .brand-caption {
            margin-top: 4px;
            color: #94a3b8;
            font-size: 9px;
            letter-spacing: .8px;
            text-transform: uppercase;
        }

        .new-chat {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            width: 100%;
            min-height: 45px;
            padding: 10px;
            margin-bottom: 25px;
            border: 1px solid #fb923c;
            border-radius: 10px;
            background: var(--orange);
            color: white;
            cursor: pointer;
            font-weight: 650;
        }

        .new-chat:hover { background: var(--orange-hover); }
        .new-chat svg { width: 18px; height: 18px; }

        .sidebar-heading {
            margin: 0 9px 12px;
            color: #94a3b8;
            font-size: 10px;
            font-weight: 750;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .conversation-list {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: #374151 transparent;
        }

        .conversation-row {
            display: flex;
            align-items: center;
            gap: 3px;
            width: 100%;
            min-width: 0;
            margin-bottom: 5px;
            padding: 3px;
            border: 1px solid transparent;
            border-radius: 9px;
        }

        .conversation-row:hover { background: var(--navy-light); }

        .conversation-item {
            display: block;
            flex: 1;
            min-width: 0;
            padding: 9px 7px;
            color: #cbd5e1;
            text-decoration: none;
            font-size: 12px;
            line-height: 1.6;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .conversation-item:hover { color: white; }

        .delete-conversation-form {
            flex: 0 0 31px;
            width: 31px;
            margin: 0;
        }

        .delete-conversation-button {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 32px;
            padding: 0;
            border: none;
            border-radius: 7px;
            background: transparent;
            color: #94a3b8;
            cursor: pointer;
            opacity: 0;
        }

        .delete-conversation-button svg { width: 16px; height: 16px; }

        .conversation-row:hover .delete-conversation-button,
        .delete-conversation-button:focus-visible {
            opacity: 1;
        }

        .delete-conversation-button:hover {
            background: #374151;
            color: #fdba74;
        }

        .empty-conversations {
            padding: 12px 10px;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.8;
        }

        .sidebar-footer {
            flex-shrink: 0;
            padding-top: 14px;
            margin-top: 14px;
            border-top: 1px solid #293548;
        }

        .account {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            padding: 9px 5px 12px;
        }

        .account-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 38px;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #374151;
            color: white;
            font-weight: 750;
        }

        .account-info { flex: 1; min-width: 0; }

        .account-name, .account-email {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .account-name {
            color: #f8fafc;
            font-size: 12px;
            font-weight: 650;
        }

        .account-email {
            margin-top: 4px;
            color: #94a3b8;
            font-size: 10px;
        }

        .logout-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-height: 38px;
            padding: 9px 12px;
            border: 1px solid #374151;
            border-radius: 8px;
            background: transparent;
            color: #cbd5e1;
            cursor: pointer;
            font-size: 12px;
        }

        .logout-button:hover { background: #293548; color: white; }
        .logout-button svg { width: 16px; height: 16px; }

        .main {
            display: flex;
            flex: 1;
            flex-direction: column;
            min-width: 0;
            height: 100%;
            overflow: hidden;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-shrink: 0;
            min-height: 70px;
            padding: 12px 30px;
            border-bottom: 1px solid var(--border);
            background: white;
        }

        .topbar-label {
            margin-bottom: 4px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 750;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .topbar-title { color: #0f172a; font-size: 15px; font-weight: 700; }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            flex-shrink: 0;
            padding: 7px 10px;
            border: 1px solid #d1fae5;
            border-radius: 20px;
            background: #ecfdf5;
            color: #047857;
            font-size: 10px;
            font-weight: 650;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #10b981;
        }

        .content {
            display: flex;
            flex: 1;
            align-items: center;
            justify-content: center;
            min-height: 0;
            overflow-y: auto;
            padding: 30px 22px;
        }

        .welcome {
            width: 100%;
            max-width: 650px;
            text-align: center;
        }

        .welcome-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 68px;
            height: 68px;
            margin: 0 auto 23px;
            border: 1px solid #ffedd5;
            border-radius: 20px;
            background: #fff7ed;
            color: var(--orange);
        }

        .welcome-icon svg { width: 34px; height: 34px; }

        .eyebrow {
            margin-bottom: 12px;
            color: var(--orange-hover);
            font-size: 11px;
            font-weight: 750;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .welcome h1 {
            margin: 0 0 15px;
            color: #0f172a;
            font-size: clamp(25px, 3vw, 36px);
            line-height: 1.25;
            letter-spacing: -1px;
        }

        .welcome p {
            max-width: 530px;
            margin: 0 auto;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.9;
        }

        .status-large {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 25px;
            padding: 9px 13px;
            border: 1px solid #d1fae5;
            border-radius: 20px;
            background: #ecfdf5;
            color: #047857;
            font-size: 11px;
            font-weight: 650;
        }

        .success-message {
            margin-top: 18px;
            padding: 12px;
            border: 1px solid #a7f3d0;
            border-radius: 10px;
            background: #ecfdf5;
            color: #047857;
        }

        /* MODAL KONFIRMASI HAPUS */

        .delete-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgb(15 23 42 / 58%);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity .22s, visibility .22s;
        }

        .delete-modal-overlay.is-visible {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .delete-modal {
            width: 100%;
            max-width: 460px;
            padding: 25px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: white;
            box-shadow: 0 25px 80px rgb(15 23 42 / 25%);
            transform: translateY(12px) scale(.97);
            transition: transform .22s;
        }

        .delete-modal-overlay.is-visible .delete-modal {
            transform: translateY(0) scale(1);
        }

        .delete-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }

        .delete-modal-title {
            margin: 0;
            color: #111827;
            font-size: 21px;
            font-weight: 750;
        }

        .delete-modal-close {
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 9px;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            font-size: 23px;
        }

        .delete-modal-close:hover { background: #f1f5f9; }

        .delete-modal-description {
            margin: 0 0 15px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.7;
        }

        .delete-modal-chat {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            min-width: 0;
            padding: 14px;
            margin-bottom: 16px;
            border: 1px solid #fed7aa;
            border-radius: 11px;
            background: #fff7ed;
        }

        .delete-modal-chat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 37px;
            height: 37px;
            border-radius: 10px;
            background: #ffedd5;
            color: var(--orange);
        }

        .delete-modal-chat-icon svg { width: 19px; height: 19px; }
        .delete-modal-chat-info { flex: 1; min-width: 0; }

        .delete-modal-chat-label {
            margin-bottom: 5px;
            color: #9a3412;
            font-size: 10px;
            font-weight: 750;
            letter-spacing: .7px;
            text-transform: uppercase;
        }

        .delete-modal-chat-title {
            color: #7c2d12;
            font-size: 13px;
            font-weight: 650;
            line-height: 1.6;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .delete-modal-warning {
            margin: 0;
            color: #64748b;
            font-size: 12px;
            line-height: 1.8;
        }

        .delete-modal-actions {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 24px;
            padding-top: 17px;
            border-top: 1px solid var(--border);
        }

        .delete-modal-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 0 17px;
            border-radius: 9px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
        }

        .delete-modal-button:disabled { cursor: wait; opacity: .7; }

        .delete-modal-cancel {
            border: 1px solid #d1d5db;
            background: white;
            color: #374151;
        }

        .delete-modal-cancel:hover { background: #f3f4f6; }

        .delete-modal-confirm {
            border: 1px solid var(--orange);
            background: var(--orange);
            color: white;
        }

        .delete-modal-confirm:hover {
            border-color: var(--orange-hover);
            background: var(--orange-hover);
        }

        .delete-modal-confirm svg { width: 16px; height: 16px; }
        body.modal-open { overflow: hidden; }

        @media (hover: none) {
            .delete-conversation-button { opacity: 1; }
        }

        @media (max-width: 900px) {
            .sidebar { flex-basis: 245px; padding-right: 12px; padding-left: 12px; }
            .topbar { padding-right: 20px; padding-left: 20px; }
        }

        @media (max-width: 600px) {
            .sidebar { flex-basis: 180px; padding: 16px 8px 10px; }
            .brand { gap: 7px; padding: 0 3px; margin-bottom: 20px; }
            .brand-logo { width: 32px; height: 32px; font-size: 14px; }
            .brand-name { font-size: 11px; }
            .brand-caption { font-size: 7px; letter-spacing: .3px; }
            .new-chat { gap: 4px; padding: 8px 4px; font-size: 10px; }
            .sidebar-heading { margin-right: 4px; margin-left: 4px; font-size: 9px; }
            .conversation-item { padding-right: 3px; padding-left: 4px; font-size: 10px; }
            .delete-conversation-form { flex-basis: 25px; width: 25px; }
            .delete-conversation-button { width: 25px; height: 30px; }
            .topbar { min-height: 62px; padding: 10px 12px; }
            .topbar-title { font-size: 12px; }
            .status { gap: 4px; padding: 6px; font-size: 9px; }
            .content { padding: 20px 12px; }
            .welcome h1 { font-size: 24px; }
            .welcome p { font-size: 12px; }
            .account { gap: 6px; }
            .account-avatar { flex-basis: 29px; width: 29px; height: 29px; font-size: 11px; }
            .account-name { font-size: 10px; }
            .account-email { font-size: 8px; }
            .logout-button { font-size: 10px; }
            .delete-modal { padding: 20px; }
            .delete-modal-title { font-size: 19px; }
            .delete-modal-actions { flex-direction: column-reverse; }
            .delete-modal-button { width: 100%; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; }
        }
    </style>
</head>

<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">R</div>
            <div class="brand-copy">
                <div class="brand-name">RAPID AI Office</div>
                <div class="brand-caption">Intelligent Workplace</div>
            </div>
        </div>

        <form method="POST" action="{{ route('ai.chat.store') }}">
            @csrf
            <button type="submit" class="new-chat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                New Conversation
            </button>
        </form>

        <div class="sidebar-heading">Recent Chats</div>

        <div class="conversation-list">
            @forelse ($conversations as $conversation)
                <div class="conversation-row">
                    <a href="{{ route('ai.chat.show', $conversation) }}"
                       class="conversation-item"
                       title="{{ $conversation->title ?: 'Untitled Conversation' }}">
                        {{ $conversation->title ?: 'Untitled Conversation' }}
                    </a>

                    <form method="POST"
                          action="{{ route('ai.chat.destroy', $conversation) }}"
                          class="delete-conversation-form"
                          data-chat-title="{{ $conversation->title ?: 'Untitled Conversation' }}">
                        @csrf
                        @method('DELETE')

                        <button type="submit"
                                class="delete-conversation-button"
                                title="Hapus percakapan"
                                aria-label="Hapus percakapan">
                            <svg viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round"
                                aria-hidden="true">
                                <path d="M3 6h18"/>
                                <path d="M8 6V4h8v2"/>
                                <path d="m19 6-1 14H6L5 6"/>
                                <path d="M10 11v5M14 11v5"/>
                            </svg>
                        </button>
                    </form>
                </div>
            @empty
                <div class="empty-conversations">
                    Belum ada percakapan.<br><br>
                    Klik <strong>New Conversation</strong> untuk memulai.
                </div>
            @endforelse
        </div>

        <div class="sidebar-footer">
            @auth
                <div class="account">
                    <div class="account-avatar">
                        {{ mb_strtoupper(mb_substr(auth()->user()->name ?? 'U', 0, 1)) }}
                    </div>
                    <div class="account-info">
                        <div class="account-name">{{ auth()->user()->name ?? 'User' }}</div>
                        <div class="account-email">{{ auth()->user()->email ?? '' }}</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="logout-button">
                        <svg viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" stroke-linejoin="round"
                            aria-hidden="true">
                            <path d="M10 17l5-5-5-5"/>
                            <path d="M15 12H3"/>
                            <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>
                        </svg>
                        Logout
                    </button>
                </form>
            @endauth
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <div class="topbar-label">AI Workspace</div>
                <div class="topbar-title">AI Assistant</div>
            </div>
            <div class="status"><span class="status-dot"></span> AI Assistant</div>
        </header>

        <section class="content">
            <div class="welcome">
                <div class="welcome-icon">
                    <svg viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="1.7"
                        stroke-linecap="round" stroke-linejoin="round"
                        aria-hidden="true">
                        <rect x="4" y="7" width="16" height="13" rx="3"/>
                        <path d="M9 7V4h6v3M9 12h.01M15 12h.01M9 16h6"/>
                    </svg>
                </div>

                <div class="eyebrow">PT Rapid Infrastruktur Indonesia</div>
                <h1>Welcome to RAPID AI Office</h1>
                <p>
                    Asisten kerja digital untuk membantu Anda mencari dan
                    membaca dokumen SharePoint yang tersedia. Mulai percakapan
                    baru atau pilih riwayat di sebelah kiri untuk melanjutkan
                    pekerjaan Anda.
                </p>

                <div class="status-large">
                    <span class="status-dot"></span>
                    Workspace ready
                </div>

                @if (session('success'))
                    <div class="success-message" role="status">
                        {{ session('success') }}
                    </div>
                @endif
            </div>
        </section>
    </main>
</div>

<!-- Modal hapus -->
<div class="delete-modal-overlay" id="delete-modal-overlay"
     aria-hidden="true">
    <section class="delete-modal" role="dialog" aria-modal="true"
             aria-labelledby="delete-modal-title"
             aria-describedby="delete-modal-description">
        <div class="delete-modal-header">
            <h2 class="delete-modal-title" id="delete-modal-title">
                Hapus percakapan?
            </h2>
            <button type="button" class="delete-modal-close"
                    id="delete-modal-close" aria-label="Tutup popup">&times;</button>
        </div>

        <p class="delete-modal-description" id="delete-modal-description">
            Anda akan menghapus percakapan berikut:
        </p>

        <div class="delete-modal-chat">
            <div class="delete-modal-chat-icon">
                <svg viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round"
                    aria-hidden="true">
                    <path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8
                    8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7
                    a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6
                    8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z"/>
                </svg>
            </div>
            <div class="delete-modal-chat-info">
                <div class="delete-modal-chat-label">Judul percakapan</div>
                <div class="delete-modal-chat-title" id="delete-modal-chat-title"></div>
            </div>
        </div>

        <p class="delete-modal-warning">
            Seluruh pesan dalam percakapan ini akan dihapus secara permanen.
            Tindakan ini tidak dapat dibatalkan.
        </p>

        <div class="delete-modal-actions">
            <button type="button"
                    class="delete-modal-button delete-modal-cancel"
                    id="delete-modal-cancel">Batal</button>
            <button type="button"
                    class="delete-modal-button delete-modal-confirm"
                    id="delete-modal-confirm">
                <svg viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round"
                    aria-hidden="true">
                    <path d="M3 6h18"/>
                    <path d="M8 6V4h8v2"/>
                    <path d="m19 6-1 14H6L5 6"/>
                    <path d="M10 11v5M14 11v5"/>
                </svg>
                Hapus percakapan
            </button>
        </div>
    </section>
</div>

<script>
(() => {
    const overlay = document.getElementById('delete-modal-overlay');
    const titleElement = document.getElementById('delete-modal-chat-title');
    const closeButton = document.getElementById('delete-modal-close');
    const cancelButton = document.getElementById('delete-modal-cancel');
    const confirmButton = document.getElementById('delete-modal-confirm');

    if (!overlay || !titleElement || !closeButton ||
        !cancelButton || !confirmButton) return;

    let selectedForm = null;
    let previousFocus = null;
    let closeTimer = null;

    function openModal(form) {
        if (closeTimer) clearTimeout(closeTimer);

        selectedForm = form;
        previousFocus = document.activeElement;
        titleElement.textContent =
            form.dataset.chatTitle || 'Untitled Conversation';

        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        closeButton.focus();
    }

    function closeModal() {
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        selectedForm = null;

        if (closeTimer) clearTimeout(closeTimer);

        closeTimer = setTimeout(() => {
            if (previousFocus && previousFocus.isConnected) {
                previousFocus.focus();
            }
            previousFocus = null;
            closeTimer = null;
        }, 230);
    }

    document.querySelectorAll('.delete-conversation-form')
        .forEach(form => {
            form.addEventListener('submit', event => {
                event.preventDefault();
                openModal(form);
            });
        });

    closeButton.addEventListener('click', closeModal);
    cancelButton.addEventListener('click', closeModal);

    overlay.addEventListener('click', event => {
        if (event.target === overlay) closeModal();
    });

    document.addEventListener('keydown', event => {
        if (!overlay.classList.contains('is-visible')) return;

        if (event.key === 'Escape') closeModal();

        if (event.key === 'Tab') {
            const focusable = [closeButton, cancelButton, confirmButton];
            if (event.shiftKey && document.activeElement === focusable[0]) {
                event.preventDefault();
                focusable[2].focus();
            } else if (!event.shiftKey &&
                       document.activeElement === focusable[2]) {
                event.preventDefault();
                focusable[0].focus();
            }
        }
    });

    confirmButton.addEventListener('click', () => {
        if (!selectedForm) return;

        const form = selectedForm;
        confirmButton.disabled = true;
        confirmButton.textContent = 'Menghapus...';
        form.submit();
    });
})();
</script>
</body>
</html>
