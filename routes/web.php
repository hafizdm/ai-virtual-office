<?php

use App\Http\Controllers\AI\ChatController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

    /*
    |--------------------------------------------------------------------------
    | AI Virtual Office - Chat
    |--------------------------------------------------------------------------
    */

    Route::get('/ai/chat', [ChatController::class, 'index'])
        ->name('ai.chat.index');

    Route::post('/ai/chat/conversations', [ChatController::class, 'storeConversation'])
        ->name('ai.chat.store');

    Route::get('/ai/chat/{conversation}', [ChatController::class, 'show'])
        ->name('ai.chat.show');

    Route::delete('/ai/chat/{conversation}', [ChatController::class, 'destroy'])
        ->name('ai.chat.destroy');

    Route::post('/ai/chat/{conversation}/messages', [ChatController::class, 'send'])
        ->name('ai.chat.send');
});

require __DIR__.'/auth.php';

Route::get('/debug/php', function () {
    return response()->json([
        'sapi' => PHP_SAPI,
        'max_execution_time' => ini_get('max_execution_time'),
        'loaded_php_ini' => php_ini_loaded_file(),
        'curl_timeout_test' => 'ok',
    ]);
});