<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ChatController extends Controller
{
    public function __construct(private AIService $aiService)
    {
    }

    public function index()
    {
        $conversations = Conversation::where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('ai.chat.index', compact('conversations'));
    }

    public function storeConversation()
    {
        $conversation = Conversation::create([
            'user_id' => Auth::id(),
            'title' => 'New Conversation',
        ]);

        return redirect()->route('ai.chat.show', $conversation);
    }

    public function show(Conversation $conversation)
    {
        $this->authorizeConversation($conversation);

        $conversations = Conversation::where('user_id', Auth::id())
            ->latest()
            ->get();

        $conversation->load('messages');

        return view('ai.chat.show', [
            'conversation' => $conversation,
            'conversations' => $conversations,
        ]);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($conversation);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:10000'],
        ]);

        $messages = $conversation->messages()
            ->orderBy('id')
            ->get();

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $validated['content'],
        ]);

        try {
            $response = $this->aiService->chat(
                $messages,
                $validated['content']
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('ai.chat.show', $conversation)
                ->withInput()
                ->with(
                    'ai_error',
                    'AI sedang mengalami gangguan atau beban tinggi. '
                    . 'Silakan coba lagi beberapa saat.'
                );
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $response,
        ]);

        if ($conversation->title === 'New Conversation') {
            $conversation->update([
                'title' => str($validated['content'])
                    ->limit(50)
                    ->toString(),
            ]);
        }

        return redirect()->route('ai.chat.show', $conversation);
    }

    public function destroy(Conversation $conversation)
    {
        $this->authorizeConversation($conversation);

        $conversation->delete();

        return redirect()
            ->route('ai.chat.index')
            ->with('success', 'Percakapan berhasil dihapus.');
    }

    private function authorizeConversation(Conversation $conversation): void
    {
        abort_unless(
            $conversation->user_id === Auth::id(),
            403
        );
    }
}