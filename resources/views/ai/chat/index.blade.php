<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>AI Virtual Office</title>

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

        /* Sidebar */
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

        .conversation-item:hover {
            background: #1f2937;
            color: white;
        }

        .empty-conversations {
            color: #9ca3af;
            font-size: 13px;
            line-height: 1.5;
        }

        /* Main content */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
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

        .content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
        }

        .welcome {
            text-align: center;
            max-width: 600px;
        }

        .welcome h1 {
            margin-bottom: 10px;
            font-size: 32px;
        }

        .welcome p {
            color: #6b7280;
            line-height: 1.6;
        }

        .status {
            display: inline-block;
            margin-top: 15px;
            padding: 7px 12px;
            border-radius: 20px;
            background: #ecfdf5;
            color: #047857;
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 220px;
            }

            .welcome h1 {
                font-size: 26px;
            }
        }
    </style>
</head>

<body>

<div class="app">

    {{-- Sidebar --}}
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

            @forelse ($conversations as $conversation)

                <a
                    href="{{ route('ai.chat.show', $conversation) }}"
                    class="conversation-item"
                >
                    {{ $conversation->title ?: 'Untitled Conversation' }}
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


    {{-- Main --}}
    <main class="main">

        <header class="topbar">
            AI Assistant
        </header>

        <section class="content">

            <div class="welcome">

                <h1>
                    Welcome to AI Virtual Office
                </h1>

                <p>
                    Ini adalah tahap awal AI Virtual Office kita.
                    Untuk sementara, AI masih menggunakan mock response
                    sehingga belum membutuhkan API AI berbayar.
                </p>

                <div class="status">
                    ● System is running
                </div>

            </div>

        </section>

    </main>

</div>

</body>
</html>