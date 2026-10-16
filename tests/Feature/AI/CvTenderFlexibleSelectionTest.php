<?php

use App\Models\Conversation;
use App\Models\CvBatch;
use App\Models\CvBatchItem;
use App\Models\User;
use App\Services\AI\Providers\AIProvider;
use App\Services\AI\Providers\MockAIProvider;
use App\Services\AI\ToolRegistry;
use App\Services\AI\Tools\Tool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(AIProvider::class, new MockAIProvider());
});

function registerCvFolderToolForTest(array $items): void
{
    $tool = new class($items) implements Tool {
        public function __construct(private array $items) {}

        public function name(): string
        {
            return 'sharepoint_list_folder';
        }

        public function description(): string
        {
            return 'Fake SharePoint folder reader for CV selection tests.';
        }

        public function execute(array $arguments): mixed
        {
            return [
                'success' => true,
                'folder' => (string) ($arguments['query'] ?? 'Format CV Rapid'),
                'items' => $this->items,
                'truncated' => false,
            ];
        }
    };

    $registry = new ToolRegistry();
    $registry->register($tool);
    app()->instance(ToolRegistry::class, $registry);
}

function cvFolderTestItems(): array
{
    return [
        [
            'itemId' => 'sp-septian',
            'driveId' => 'drive-1',
            'name' => 'CV Septian Indra.docx',
            'type' => 'file',
            'extension' => 'docx',
            'webUrl' => 'https://example.invalid/septian',
        ],
        [
            'itemId' => 'sp-budi',
            'driveId' => 'drive-1',
            'name' => 'Budi Santoso.docx',
            'type' => 'file',
            'extension' => 'docx',
            'webUrl' => 'https://example.invalid/budi',
        ],
        [
            'itemId' => 'sp-andi',
            'driveId' => 'drive-1',
            'name' => 'Andi Pratama.docx',
            'type' => 'file',
            'extension' => 'docx',
            'webUrl' => 'https://example.invalid/andi',
        ],
    ];
}

test('a completed batch does not block a new request and old batch output remains untouched', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $conversation = Conversation::create([
        'user_id' => $user->id,
        'title' => 'CV Tender Automation',
    ]);

    $templatePath = 'cv-templates/' . $user->id . '/template.docx';
    Storage::disk('local')->put($templatePath, 'test-template');

    $oldBatch = CvBatch::create([
        'user_id' => $user->id,
        'conversation_id' => $conversation->id,
        'template_path' => $templatePath,
        'template_original_name' => 'Tender-Template.docx',
        'source_folder' => 'Format CV Rapid',
        'status' => 'completed',
        'total_files' => 1,
        'processed_files' => 1,
        'successful_files' => 1,
        'failed_files' => 0,
    ]);

    $oldItem = $oldBatch->items()->create([
        'source_item_id' => 'old-source-id',
        'drive_id' => 'drive-old',
        'source_filename' => 'CV Lama.docx',
        'source_path' => 'Format CV Rapid/CV Lama.docx',
        'status' => 'success',
        'output_path' => 'cv-outputs/batch-' . $oldBatch->id . '/CV-lama.docx',
    ]);

    registerCvFolderToolForTest(cvFolderTestItems());

    $this->actingAs($user)
        ->post(route('ai.chat.send', $conversation), [
            'content' => 'Konversi CV Septian Indra, Budi Santoso, dan Andi Pratama menggunakan template tender yang saya unggah.',
        ])
        ->assertRedirect(route('ai.chat.show', $conversation));

    $newBatch = CvBatch::query()
        ->where('conversation_id', $conversation->id)
        ->latest('id')
        ->firstOrFail();

    expect($newBatch->id)->not->toBe($oldBatch->id)
        ->and($newBatch->status)->toBe('awaiting_confirmation')
        ->and($newBatch->total_files)->toBe(3)
        ->and($oldBatch->fresh()->status)->toBe('completed')
        ->and($oldItem->fresh()->status)->toBe('success');

    expect($newBatch->items()->pluck('source_filename')->all())
        ->toContain('CV Septian Indra.docx')
        ->toContain('Budi Santoso.docx')
        ->toContain('Andi Pratama.docx');
});

test('a named CV request selects only the requested candidate', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $conversation = Conversation::create([
        'user_id' => $user->id,
        'title' => 'CV Tender Automation',
    ]);

    $templatePath = 'cv-templates/' . $user->id . '/template.docx';
    Storage::disk('local')->put($templatePath, 'test-template');

    CvBatch::create([
        'user_id' => $user->id,
        'conversation_id' => $conversation->id,
        'template_path' => $templatePath,
        'template_original_name' => 'Tender-Template.docx',
        'source_folder' => null,
        'status' => 'draft',
    ]);

    registerCvFolderToolForTest(cvFolderTestItems());

    $this->actingAs($user)
        ->post(route('ai.chat.send', $conversation), [
            'content' => 'Konversi CV Septian Indra menggunakan template tender.',
        ])
        ->assertRedirect(route('ai.chat.show', $conversation));

    $batch = CvBatch::query()
        ->where('conversation_id', $conversation->id)
        ->latest('id')
        ->firstOrFail();

    expect($batch->status)->toBe('awaiting_confirmation')
        ->and($batch->total_files)->toBe(1)
        ->and($batch->items()->pluck('source_filename')->all())
        ->toBe(['CV Septian Indra.docx']);
});

test('a requested candidate that cannot be found does not save a partial list', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $conversation = Conversation::create([
        'user_id' => $user->id,
        'title' => 'CV Tender Automation',
    ]);

    $templatePath = 'cv-templates/' . $user->id . '/template.docx';
    Storage::disk('local')->put($templatePath, 'test-template');

    $originalBatch = CvBatch::create([
        'user_id' => $user->id,
        'conversation_id' => $conversation->id,
        'template_path' => $templatePath,
        'template_original_name' => 'Tender-Template.docx',
        'source_folder' => 'Format CV Rapid',
        'status' => 'completed',
    ]);

    registerCvFolderToolForTest(cvFolderTestItems());

    $this->actingAs($user)
        ->post(route('ai.chat.send', $conversation), [
            'content' => 'Konversi CV Septian Indra dan Nama Tidak Ada menggunakan template tender.',
        ])
        ->assertRedirect(route('ai.chat.show', $conversation));

    expect(CvBatch::query()->where('conversation_id', $conversation->id)->count())->toBe(1)
        ->and($originalBatch->fresh()->status)->toBe('completed')
        ->and($originalBatch->items()->count())->toBe(0);

    $assistantMessage = $conversation->messages()
        ->where('role', 'assistant')
        ->latest('id')
        ->firstOrFail();

    expect($assistantMessage->content)->toContain('nama tidak ada')
        ->and($assistantMessage->content)->toContain('Tidak ada daftar yang disimpan');
});


test('a criteria request selects only CVs whose extracted text contains the requested phrase', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $conversation = Conversation::create([
        'user_id' => $user->id,
        'title' => 'CV Tender Automation',
    ]);

    $templatePath = 'cv-templates/' . $user->id . '/template.docx';
    Storage::disk('local')->put($templatePath, 'test-template');

    CvBatch::create([
        'user_id' => $user->id,
        'conversation_id' => $conversation->id,
        'template_path' => $templatePath,
        'template_original_name' => 'Tender-Template.docx',
        'source_folder' => null,
        'status' => 'draft',
    ]);

    registerCvFolderToolForTest(cvFolderTestItems());

    $reader = Mockery::mock(\App\Services\AI\CV\SharePointCvReader::class);
    $reader->shouldReceive('readDocx')->once()->with('drive-1', 'sp-septian')
        ->andReturn(['text' => 'Experience: Electrical Engineer with site experience']);
    $reader->shouldReceive('readDocx')->once()->with('drive-1', 'sp-budi')
        ->andReturn(['text' => 'Experience: Civil Engineer']);
    $reader->shouldReceive('readDocx')->once()->with('drive-1', 'sp-andi')
        ->andReturn(['text' => 'Experience: Mechanical Engineer']);
    $this->app->instance(\App\Services\AI\CV\SharePointCvReader::class, $reader);

    $this->actingAs($user)
        ->post(route('ai.chat.send', $conversation), [
            'content' => 'Konversi seluruh CV Electrical Engineer yang memenuhi kriteria tender.',
        ])
        ->assertRedirect(route('ai.chat.show', $conversation));

    $batch = CvBatch::query()
        ->where('conversation_id', $conversation->id)
        ->latest('id')
        ->firstOrFail();

    expect($batch->status)->toBe('awaiting_confirmation')
        ->and($batch->total_files)->toBe(1)
        ->and($batch->items()->pluck('source_filename')->all())
        ->toBe(['CV Septian Indra.docx']);
});
