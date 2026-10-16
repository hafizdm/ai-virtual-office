<?php
namespace App\Http\Controllers\AI;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\CvBatch;
use App\Models\Message;
use App\Services\AI\AIService;
use App\Services\AI\ToolRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use App\Services\AI\CV\CvTenderProcessor;
use App\Jobs\ProcessCvBatchItem;
use ZipArchive;
class ChatController extends Controller
{
    public function __construct(
        private AIService $aiService,
        private CvTenderProcessor $cvTenderProcessor
    ) {
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
        $cvBatch = CvBatch::query()
            ->where('user_id', Auth::id())
            ->where('conversation_id', $conversation->id)
            ->with('items')
            ->latest('id')
            ->first();
        return view('ai.chat.show', [
            'conversation' => $conversation,
            'conversations' => $conversations,
            'cvBatch' => $cvBatch,
        ]);
    }
    
    public function send(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($conversation);
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:10000'],
            'template' => ['nullable', 'file', 'mimes:docx', 'max:10240'],
        ]);
        $templatePath = null;
        // Simpan template jika pengguna melampirkan file.
        if ($request->hasFile('template')) {
            $file = $validated['template'];
            if (strtolower($file->getClientOriginalExtension()) !== 'docx') {
                return back()
                    ->withInput()
                    ->withErrors([
                        'template' => 'Template harus berupa file Word (.docx).',
                    ]);
            }
            $templatePath = $file->storeAs(
                'cv-templates/' . Auth::id(),
                Str::uuid() . '.docx',
                'local'
            );
            if (! $templatePath) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'template' => 'Template gagal disimpan. Silakan coba lagi.',
                    ]);
            }
            try {
                CvBatch::create([
                    'user_id' => Auth::id(),
                    'conversation_id' => $conversation->id,
                    'template_path' => $templatePath,
                    'template_original_name' => $file->getClientOriginalName(),
                    'source_folder' => null,
                    'status' => 'draft',
                ]);
            } catch (Throwable $exception) {
                Storage::disk('local')->delete($templatePath);
                report($exception);
                return back()
                    ->withInput()
                    ->withErrors([
                        'template' => 'Data template gagal disimpan. Silakan coba lagi.',
                    ]);
            }
        }
        $messages = $conversation->messages()
            ->orderBy('id')
            ->get();
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $validated['content'],
        ]);
        try {
            // Tangani alur CV secara deterministik agar daftar file tersimpan
            // ke CvBatch dan tidak hanya ditampilkan sebagai teks oleh AI.
            $response = $this->handleCvTenderChatMessage(
                $conversation,
                $validated['content']
            );
            // Pesan biasa tetap menggunakan GeneralAgent seperti sebelumnya.
            if ($response === null) {
                $response = $this->aiService->chat(
                    $messages,
                    $validated['content']
                );
            }
        } catch (Throwable $exception) {
            report($exception);
            return redirect()
                ->route('ai.chat.show', $conversation)
                ->withInput()
                ->with(
                    'ai_error',
                    'AI atau proses SharePoint sedang mengalami gangguan. '
                    . 'Silakan coba lagi beberapa saat.'
                );
        }
        if ($templatePath !== null) {
            $response = "Template CV berhasil diterima dan disimpan sebagai draft. "
                . "Langkah berikutnya adalah memilih folder sumber CV di SharePoint "
                . "sebelum memulai pemrosesan.\n\n"
                . $response;
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
    /**
     * Orchestrate the prompt-driven CV flow without relying on the LLM
     * to persist batch state. Returns null for ordinary chat messages.
     */
    private function handleCvTenderChatMessage(
        Conversation $conversation,
        string $message
    ): ?string {
        $normalized = mb_strtolower(trim($message));
        $batch = CvBatch::query()
            ->where('user_id', Auth::id())
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->first();
        // A clear confirmation is accepted only when a saved list is awaiting approval.
        if ($batch && $batch->status === 'awaiting_confirmation') {
            $isConfirmation = preg_match(
                '/\b(setuju|saya setuju|konfirmasi|saya konfirmasi|lanjutkan|silakan proses|ya,? lanjut|iya,? lanjut|ok,? lanjut)\b/u',
                $normalized
            ) === 1;
            $isRejection = preg_match(
                '/\b(tidak|jangan|batal|belum setuju|ubah daftar)\b/u',
                $normalized
            ) === 1;
            if ($isRejection) {
                return 'Baik, CV belum dikonfirmasi dan belum ada file yang diproses. '
                    . 'Jika daftar perlu diubah, sebutkan folder sumber yang benar.';
            }
            if ($isConfirmation) {
                if (! $batch->items()->exists()) {
                    throw new \RuntimeException('Daftar CV kosong; konfirmasi tidak dapat diproses.');
                }
                $batch->update([
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                ]);
                try {
                    $this->dispatchCvBatch($batch);
                    return 'Konfirmasi berhasil disimpan. Pemrosesan ' . (int) $batch->total_files
                        . ' CV telah dimasukkan ke antrean dan berjalan di background. '
                        . 'Anda tidak perlu menunggu halaman ini terbuka. '
                        . 'Status dan hasil akan diperbarui saat worker memproses setiap CV.';
                } catch (Throwable $exception) {
                    report($exception);
                    $batch->update([
                        'status' => 'failed',
                        'finished_at' => now(),
                    ]);
                    return 'Konfirmasi tersimpan, tetapi batch gagal dimasukkan ke antrean. '
                        . 'Silakan periksa log Laravel untuk detailnya.';
                }
            }
        }
        $isCvRequest = preg_match(
            '/\b(cv|curriculum vitae)\b/u',
            $normalized
        ) === 1 && preg_match(
            '/\b(tender|format|ubah|konversi|convert|standar|standardisasi|template|rapikan)\b/u',
            $normalized
        ) === 1;
        if (! $isCvRequest) {
            return null;
        }
        if (! $batch || ! $batch->template_path || ! Storage::disk('local')->exists($batch->template_path)) {
            return 'Saya belum menemukan template CV DOCX yang tersimpan pada percakapan ini. '
                . 'Silakan unggah template tender melalui tombol lampiran terlebih dahulu, lalu kirim kembali permintaan konversi CV.';
        }
        if ($batch->status === 'confirmed') {
            return 'Batch CV ini sudah dikonfirmasi dan berstatus confirmed. '
                . 'Jika antrean belum dijalankan, jalankan worker Laravel: php artisan queue:work database --timeout=300 --tries=1';
        }
        if ($batch->status !== 'draft') {
            return 'Batch CV saat ini berstatus ' . strtoupper((string) $batch->status)
                . '. Saya tidak akan membuat daftar baru atau memproses file tanpa alur konfirmasi yang sesuai.';
        }
        $sourceFolder = 'Format CV Rapid';
        if (preg_match('/\bfolder\s+["“]([^"”]+)["”]/u', $message, $matches) === 1) {
            $sourceFolder = trim($matches[1]);
        }
        $tool = app(ToolRegistry::class)->get('sharepoint_list_folder');
        if (! $tool) {
            throw new \RuntimeException('Tool sharepoint_list_folder belum terdaftar.');
        }
        $result = $tool->execute(['query' => $sourceFolder]);
        if (! is_array($result) || ! ($result['success'] ?? false)) {
            $reason = is_array($result)
                ? (string) ($result['message'] ?? 'Folder tidak ditemukan.')
                : 'Respons SharePoint tidak valid.';
            return 'Saya belum dapat membaca folder SharePoint "' . $sourceFolder . '". ' . $reason;
        }
        $docxFiles = collect($result['items'] ?? [])
            ->filter(fn (array $item): bool =>
                ($item['type'] ?? null) === 'file'
                && strtolower((string) ($item['extension'] ?? pathinfo((string) ($item['name'] ?? ''), PATHINFO_EXTENSION))) === 'docx'
                && is_string($item['itemId'] ?? null)
                && ($item['itemId'] ?? '') !== ''
                && is_string($item['driveId'] ?? null)
                && ($item['driveId'] ?? '') !== ''
            )
            ->values();
        if ($docxFiles->isEmpty()) {
            return 'Folder SharePoint berhasil dibaca, tetapi tidak ditemukan file DOCX yang memiliki ID file dan drive yang diperlukan. Tidak ada file yang diproses.';
        }
        $batch->items()->delete();
        foreach ($docxFiles as $item) {
            $batch->items()->create([
                'source_item_id' => $item['itemId'],
                'drive_id' => $item['driveId'],
                'source_filename' => $item['name'] ?? 'Nama CV tidak tersedia',
                'source_path' => trim((string) ($result['folder'] ?? $sourceFolder), '/') . '/' . ($item['name'] ?? ''),
                'source_web_url' => $item['webUrl'] ?? null,
                'status' => 'pending',
            ]);
        }
        $batch->update([
            'source_folder' => $result['folder'] ?? $sourceFolder,
            'status' => 'awaiting_confirmation',
            'total_files' => $docxFiles->count(),
            'processed_files' => 0,
            'successful_files' => 0,
            'failed_files' => 0,
            'confirmed_at' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);
        $lines = [
            'Saya menemukan ' . $docxFiles->count() . ' file CV DOCX di folder SharePoint: '
                . ($result['folder'] ?? $sourceFolder) . '.',
            '',
        ];
        foreach ($docxFiles as $index => $item) {
            $lines[] = ($index + 1) . '. ' . ($item['name'] ?? 'Nama CV tidak tersedia');
        }
        $lines[] = '';
        $lines[] = 'Template yang akan digunakan: ' . $batch->template_original_name . '.';
        $lines[] = 'Apakah Anda menyetujui daftar ini? Balas "Saya setuju, lanjutkan" untuk mengonfirmasi. '
            . 'Belum ada CV yang diproses.';
        return implode("\n", $lines);
    }
    /**
     * Resolve a SharePoint folder and save its DOCX files for user confirmation.
     */
    public function selectCvSourceFolder(
        Request $request,
        Conversation $conversation,
        CvBatch $batch
    ) {
        $this->authorizeConversation($conversation);
        $this->authorizeCvBatch($batch, $conversation);
        abort_unless($batch->status === 'draft', 409, 'Batch CV ini tidak lagi berstatus draft.');
        $validated = $request->validate([
            'source_folder' => ['required', 'string', 'max:500'],
        ]);
        try {
            $tool = app(ToolRegistry::class)->get('sharepoint_list_folder');
            if (! $tool) {
                throw new \RuntimeException('Tool pembaca folder SharePoint belum terdaftar.');
            }
            $result = $tool->execute([
                'query' => $validated['source_folder'],
            ]);
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors([
                'source_folder' => 'Folder SharePoint gagal dibaca. Periksa koneksi Microsoft Graph dan nama folder.',
            ]);
        }
        if (! is_array($result) || ! ($result['success'] ?? false)) {
            return back()->withErrors([
                'source_folder' => is_array($result)
                    ? ($result['message'] ?? 'Folder SharePoint tidak ditemukan.')
                    : 'Respons folder SharePoint tidak valid.',
            ]);
        }
        $docxFiles = collect($result['items'] ?? [])
            ->filter(fn (array $item): bool =>
                ($item['type'] ?? null) === 'file'
                && strtolower((string) ($item['extension'] ?? '')) === 'docx'
                && is_string($item['itemId'] ?? null)
                && ($item['itemId'] ?? '') !== ''
                && is_string($item['driveId'] ?? null)
                && ($item['driveId'] ?? '') !== ''
            )
            ->values();
        if ($docxFiles->isEmpty()) {
            return back()->withErrors([
                'source_folder' => 'Folder ditemukan, tetapi tidak ada file DOCX yang dapat diproses.',
            ]);
        }
        try {
            $batch->items()->delete();
            foreach ($docxFiles as $item) {
                $batch->items()->create([
                    'source_item_id' => $item['itemId'],
                    'drive_id' => $item['driveId'],
                    'source_filename' => $item['name'],
                    'source_path' => trim($result['folder'] ?? $validated['source_folder'], '/') . '/' . $item['name'],
                    'source_web_url' => $item['webUrl'] ?? null,
                    'status' => 'pending',
                ]);
            }
            $batch->update([
                'source_folder' => $result['folder'] ?? $validated['source_folder'],
                'status' => 'awaiting_confirmation',
                'total_files' => $docxFiles->count(),
                'processed_files' => 0,
                'successful_files' => 0,
                'failed_files' => 0,
                'confirmed_at' => null,
                'started_at' => null,
                'finished_at' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors([
                'source_folder' => 'Daftar CV ditemukan, tetapi gagal disimpan ke database.',
            ]);
        }
        return redirect()
            ->route('ai.chat.show', $conversation)
            ->with('success', 'Folder berhasil dibaca. Periksa daftar CV di bawah dan konfirmasikan sebelum pemrosesan.');
    }
    /**
     * Record the user's confirmation. This does not process or modify any CV yet.
     */
    public function confirmCvBatch(
        Conversation $conversation,
        CvBatch $batch
    ) {
        $this->authorizeConversation($conversation);
        $this->authorizeCvBatch($batch, $conversation);

        abort_unless(
            $batch->status === 'awaiting_confirmation' && $batch->items()->exists(),
            409,
            'Batch CV belum siap dikonfirmasi atau sudah pernah diproses.'
        );

        $batch->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        try {
            $this->dispatchCvBatch($batch);

            return redirect()
                ->route('ai.chat.show', $conversation)
                ->with(
                    'success',
                    "Batch CV ({$batch->total_files} file) berhasil dimasukkan ke antrean. "
                    . 'Pemrosesan berjalan di background; jalankan queue worker untuk memulai.'
                );
        } catch (Throwable $exception) {
            report($exception);

            $batch->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);

            return redirect()
                ->route('ai.chat.show', $conversation)
                ->withErrors([
                    'source_folder' => 'Batch gagal dimasukkan ke antrean. Periksa log Laravel untuk detailnya.',
                ]);
        }
    }

    /**
     * Queue each CV independently so a long Ollama/SharePoint operation
     * never holds the user's HTTP request open.
     */
    private function dispatchCvBatch(CvBatch $batch): void
    {
        $templatePath = (string) $batch->template_path;
        if ($templatePath === '' || ! Storage::disk('local')->exists($templatePath)) {
            throw new \RuntimeException('Template CV tidak ditemukan.');
        }

        $items = $batch->items()->get();
        if ($items->isEmpty()) {
            throw new \RuntimeException('Batch tidak memiliki file CV untuk diproses.');
        }

        $outputDirectory = 'cv-outputs/batch-' . $batch->id;
        Storage::disk('local')->makeDirectory($outputDirectory);

        $batch->update([
            'status' => 'processing',
            'started_at' => now(),
            'finished_at' => null,
            'total_files' => $items->count(),
            'processed_files' => 0,
            'successful_files' => 0,
            'failed_files' => 0,
        ]);

        foreach ($items as $item) {
            $item->update([
                'status' => 'pending',
                'started_at' => null,
                'finished_at' => null,
                'error_message' => null,
                'output_path' => null,
                'output_filename' => null,
            ]);
        }

        foreach ($items as $item) {
            ProcessCvBatchItem::dispatch($item->id);
        }
    }


    
    public function downloadCvBatchZip(
        Conversation $conversation,
        CvBatch $batch
    ) {
        $this->authorizeConversation($conversation);
        $this->authorizeCvBatch($batch, $conversation);

        abort_unless(
            in_array($batch->status, ['completed', 'completed_with_errors'], true),
            409,
            'Batch CV belum selesai diproses.'
        );

        $successfulItems = $batch->items()
            ->where('status', 'success')
            ->whereNotNull('output_path')
            ->get();

        abort_if(
            $successfulItems->count() < 2,
            404,
            'Minimal dua CV berhasil diperlukan untuk membuat ZIP.'
        );

        $zipDirectory = storage_path('app/private/cv-zip-temp');

        if (! is_dir($zipDirectory)) {
            mkdir($zipDirectory, 0755, true);
        }

        $zipPath = $zipDirectory . '/CV-Tender-Batch-' . $batch->id
            . '-' . uniqid() . '.zip';

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('File ZIP gagal dibuat.');
        }

        $addedFiles = 0;

        try {
            foreach ($successfulItems as $item) {
                $relativePath = (string) $item->output_path;

                if (! Storage::disk('local')->exists($relativePath)) {
                    continue;
                }

                $absolutePath = Storage::disk('local')->path($relativePath);
                $filename = basename($relativePath);

                if ($zip->addFile($absolutePath, $filename)) {
                    $addedFiles++;
                }
            }

            $zip->close();
        } catch (Throwable $exception) {
            $zip->close();

            if (is_file($zipPath)) {
                @unlink($zipPath);
            }

            throw $exception;
        }

        if ($addedFiles < 2 || ! is_file($zipPath)) {
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }

            abort(404, 'Tidak cukup file CV yang tersedia untuk diunduh.');
        }

        return response()->download(
            $zipPath,
            'CV-Tender-Batch-' . $batch->id . '.zip',
            ['Content-Type' => 'application/zip']
        )->deleteFileAfterSend(true);
    }


    private function authorizeCvBatch(CvBatch $batch, Conversation $conversation): void
    {
        abort_unless(
            $batch->user_id === Auth::id()
            && (int) $batch->conversation_id === (int) $conversation->id,
            404
        );
    }
    /**
     * Endpoint upload lama dipertahankan agar route yang sudah ada
     * tidak langsung rusak. UI baru mengirim template melalui send().
     */
    public function uploadCvTemplate(
        Request $request,
        Conversation $conversation
    ) {
        $this->authorizeConversation($conversation);
        $validated = $request->validate([
            'template' => [
                'required',
                'file',
                'mimes:docx',
                'max:10240',
            ],
        ]);
        $file = $validated['template'];
        if (strtolower($file->getClientOriginalExtension()) !== 'docx') {
            return back()->withErrors([
                'template' => 'Template harus berupa file Word (.docx).',
            ]);
        }
        $storedName = Str::uuid() . '.docx';
        $path = $file->storeAs(
            'cv-templates/' . Auth::id(),
            $storedName,
            'local'
        );
        if (! $path) {
            return back()->withErrors([
                'template' => 'Template gagal disimpan. Silakan coba lagi.',
            ]);
        }
        try {
            CvBatch::create([
                'user_id' => Auth::id(),
                'conversation_id' => $conversation->id,
                'template_path' => $path,
                'template_original_name' => $file->getClientOriginalName(),
                'source_folder' => null,
                'status' => 'draft',
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            report($exception);
            return back()->withErrors([
                'template' => 'Data template gagal disimpan. Silakan coba lagi.',
            ]);
        }
        return redirect()
            ->route('ai.chat.show', $conversation)
            ->with(
                'success',
                'Template CV berhasil diunggah. '
                . 'Silakan lanjutkan dengan memilih folder sumber CV.'
            );
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
