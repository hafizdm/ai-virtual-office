<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>{{ $conversation->title ?? 'AI Virtual Office' }}</title>

<style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: #f5f7fb;
        color: #1f2937;
    }

    .app {
        display: flex;
        height: 100vh;
    }

    .sidebar {
        width: 280px;
        background: #111827;
        color: white;
        padding: 20px;
        display: flex;
        flex-direction: column;
    }

    .brand {
        font-size: 20px;
        font-weight: bold;
        margin-bottom: 25px;
    }

    .new-chat {
        display: block;
        width: 100%;
        padding: 11px 14px;
        border: 1px solid #374151;
        border-radius: 8px;
        background: #1f2937;
        color: white;
        text-decoration: none;
        text-align: center;
        margin-bottom: 20px;
        cursor: pointer;
    }

    .new-chat:hover {
        background: #374151;
    }

    .conversation-list {
        flex: 1;
        overflow-y: auto;
    }

    .conversation-item {
        display: block;
        padding: 10px 12px;
        border-radius: 7px;
        color: #d1d5db;
        text-decoration: none;
        margin-bottom: 5px;
        font-size: 14px;
    }

    .conversation-item:hover,
    .conversation-item.active {
        background: #1f2937;
        color: white;
    }

    .empty-conversations {
        color: #9ca3af;
        font-size: 13px;
        line-height: 1.5;
    }

    .main {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .topbar {
        height: 65px;
        background: white;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        padding: 0 25px;
        font-weight: 600;
    }

    .messages {
        flex: 1;
        overflow-y: auto;
        padding: 30px;
    }

    .ai-error {
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #9a3412;
        padding: 12px 15px;
        border-radius: 9px;
        margin-bottom: 20px;
        line-height: 1.5;
        font-size: 14px;
    }

    .message {
        display: flex;
        margin-bottom: 20px;
    }

    .message.user {
        justify-content: flex-end;
    }

    .message.assistant {
        justify-content: flex-start;
    }

    .message-content {
        max-width: 70%;
        padding: 12px 16px;
        border-radius: 12px;
        line-height: 1.5;
        white-space: pre-wrap;
    }

    .message.user .message-content {
        background: #2563eb;
        color: white;
        border-bottom-right-radius: 3px;
    }

    .message.assistant .message-content {
        background: white;
        border: 1px solid #e5e7eb;
        border-bottom-left-radius: 3px;
    }

    .message-role {
        font-size: 11px;
        font-weight: bold;
        margin-bottom: 5px;
        opacity: 0.7;
        text-transform: uppercase;
    }

    .composer {
        background: white;
        border-top: 1px solid #e5e7eb;
        padding: 18px 25px;
    }

    .composer form {
        display: flex;
        gap: 10px;
    }

    .composer textarea {
        flex: 1;
        resize: none;
        min-height: 48px;
        max-height: 150px;
        padding: 13px 15px;
        border: 1px solid #d1d5db;
        border-radius: 9px;
        font-family: inherit;
        font-size: 14px;
        outline: none;
    }

    .composer textarea:focus {
        border-color: #2563eb;
    }

    .send-button {
        border: none;
        background: #2563eb;
        color: white;
        padding: 0 22px;
        border-radius: 9px;
        cursor: pointer;
        font-weight: 600;
    }

    .send-button:hover {
        background: #1d4ed8;
    }

    .empty-chat {
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #6b7280;
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 220px;
        }

        .message-content {
            max-width: 85%;
        }

        .messages {
            padding: 20px;
        }

        .composer {
            padding: 15px;
        }
    }
</style>


</head>

<body>

<div class="app">


<aside class="sidebar">

    <div class="brand">
        AI Virtual Office
    </div>

    <form method="POST" action="{{ route('ai.chat.store') }}">
        @csrf

        <button type="submit" class="new-chat">
            + New Conversation
        </button>
    </form>

    <div class="conversation-list">

        @forelse ($conversations as $item)

            <a
                href="{{ route('ai.chat.show', $item) }}"
                class="conversation-item {{ $item->id === $conversation->id ? 'active' : '' }}"
            >
                {{ $item->title ?: 'Untitled Conversation' }}
            </a>

        @empty

            <div class="empty-conversations">
                Belum ada conversation.
                <br><br>
                Klik <strong>+ New Conversation</strong> untuk memulai.
            </div>

        @endforelse

    </div>

</aside>

<main class="main">

    <header class="topbar">
        {{ $conversation->title ?: 'New Conversation' }}
    </header>

    <section class="messages">

        @if (session('ai_error'))

            <div class="ai-error">
                <strong>AI sedang sibuk.</strong>
                <br>
                {{ session('ai_error') }}
            </div>

        @endif

        @forelse ($conversation->messages as $message)

            <div class="message {{ $message->role }}">

                <div class="message-content">

                    <div class="message-role">
                        {{ $message->role }}
                    </div>

                    {{ $message->content }}

                </div>

            </div>

        @empty

            <div class="empty-chat">

                <div>
                    <h2>Start a conversation</h2>

                    <p>
                        Kirim pesan pertama Anda kepada AI Virtual Office.
                    </p>
                </div>

            </div>

        @endforelse

    </section>

    <div class="composer">

        <form
            method="POST"
            action="{{ route('ai.chat.send', $conversation) }}"
        >

            @csrf

            <textarea
                name="content"
                placeholder="Type your message..."
                required
            ></textarea>

            <button type="submit" class="send-button">
                Send
            </button>

        </form>

    </div>

</main>


</div>

</body>
</html>
