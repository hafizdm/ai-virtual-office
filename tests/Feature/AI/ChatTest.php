
<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\AI\Providers\AIProvider;
use App\Services\AI\Providers\MockAIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\AI\ToolAccess;
use App\Services\AI\ToolRegistry;
use App\Services\AI\Tools\MockTool;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(
        AIProvider::class,
        new MockAIProvider()
    );
});

test('authenticated user can open the chat page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('ai.chat.index'))
        ->assertOk();
});

test('user can send a message and receive a mock response', function () {
    $user = User::factory()->create();

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'title' => 'New Conversation',
    ]);

    $this->actingAs($user)
        ->post(route('ai.chat.send', $conversation), [
            'content' => 'Halo, siapa kamu?',
        ])
        ->assertRedirect(route('ai.chat.show', $conversation));

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Halo, siapa kamu?',
    ]);

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
    ]);
});


test('user cannot open another users conversation', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $conversation = Conversation::create([
        'user_id' => $userB->id,
        'title' => 'Private Conversation',
    ]);

    $this->actingAs($userA)
        ->get(route('ai.chat.show', $conversation))
        ->assertForbidden();
});

test('user cannot send a message to another users conversation', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $conversation = Conversation::create([
        'user_id' => $userB->id,
        'title' => 'Private Conversation',
    ]);

    $this->actingAs($userA)
        ->post(route('ai.chat.send', $conversation), [
            'content' => 'Coba akses percakapan user lain',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('messages', [
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Coba akses percakapan user lain',
    ]);
});


test('chat endpoint executes an explicit tool command', function () {
    $user = User::factory()->create();

    $registry = new ToolRegistry();
    $registry->register(new MockTool());

    $access = new ToolAccess();
    $access->allow('mock_tool');

    $this->app->instance(ToolRegistry::class, $registry);
    $this->app->instance(ToolAccess::class, $access);

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'title' => 'New Conversation',
    ]);

    $this->actingAs($user)
        ->post(route('ai.chat.send', $conversation), [
            'content' => 'Jalankan mock tool dengan pesan Hello Virtual Office',
        ])
        ->assertRedirect(route('ai.chat.show', $conversation));

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => json_encode([
            'success' => true,
            'tool' => 'mock_tool',
            'message' => 'Hello Virtual Office',
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
});


