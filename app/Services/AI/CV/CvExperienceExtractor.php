<?php

namespace App\Services\AI\CV;

use App\Services\AI\Providers\AIProvider;
use Illuminate\Support\Collection;
use JsonException;
use Throwable;

class CvExperienceExtractor
{
    public function __construct(
        private readonly AIProvider $provider
    ) {
    }

    /**
     * Menganalisis satu pengalaman kerja pada satu waktu.
     *
     * Data sumber tetap dipertahankan. Hasil AI belum dianggap
     * terverifikasi sampai ditinjau terhadap CV asli.
     */
    public function extract(array $experience): array
    {
        $rawProjectText = trim(
            (string) ($experience['raw_project_text'] ?? '')
        );

        if ($rawProjectText === '') {
            return $this->fallback(
                $experience,
                'Teks proyek dan tanggung jawab tidak tersedia.'
            );
        }

        $systemPrompt = <<<'PROMPT'
Anda adalah asisten ekstraksi data CV untuk tender engineering.

Tugas Anda mengidentifikasi informasi yang benar-benar tertulis
dalam teks pengalaman kerja yang diberikan.

Aturan:
1. Jangan mengarang nama proyek, tanggung jawab, pencapaian,
   angka, tanggal, perusahaan, atau jabatan.
2. Pisahkan nama proyek dari uraian tanggung jawab jika
   pembedaan tersebut didukung oleh teks.
3. Jika hubungan antara proyek dan tanggung jawab tidak jelas,
   masukkan tanggung jawab tersebut sebagai tanggung jawab umum.
4. Jangan menyimpulkan tanggung jawab hanya berdasarkan jabatan.
5. Pertahankan istilah teknis, angka, dan nama sebagaimana sumber.
6. Jika informasi tidak dapat diidentifikasi dengan yakin,
   jangan menebak. Jelaskan pada review_notes.
7. Kembalikan JSON valid saja. Jangan gunakan Markdown atau
   pagar kode.

Format wajib:
{
  "projects": ["nama proyek dari sumber"],
  "responsibilities": ["uraian tanggung jawab dari sumber"],
  "review_notes": "catatan ketidakpastian atau informasi yang perlu ditinjau"
}

Gunakan array kosong jika informasi tidak ditemukan.
PROMPT;

        $input = [
            'period' => $experience['period'] ?? '',
            'company' => $experience['company'] ?? '',
            'position' => $experience['position'] ?? '',
            'source_text' => $rawProjectText,
        ];

        $message = "Ekstrak pengalaman kerja berikut.\n"
            . "Semua informasi harus berasal dari source_text.\n\n"
            . json_encode(
                $input,
                JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
            );

        try {
            $response = $this->provider->chat(
                $systemPrompt,
                new Collection(),
                $message
            );

            $data = $this->decodeResponse($response);

            if (
                !is_array($data)
                || !is_array($data['projects'] ?? null)
                || !is_array($data['responsibilities'] ?? null)
            ) {
                return $this->fallback(
                    $experience,
                    'Respons AI tidak sesuai struktur JSON yang diharapkan.'
                );
            }

            $projects = $this->cleanStringList($data['projects']);
            $responsibilities = $this->cleanStringList(
                $data['responsibilities']
            );

            return array_merge($experience, [
                'projects' => $projects,
                'responsibilities' => $responsibilities,
                'review_notes' => trim(
                    (string) ($data['review_notes'] ?? '')
                ),
                'extraction_status' => 'needs_review',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->fallback(
                $experience,
                'Ekstraksi AI gagal: ' . $exception->getMessage()
            );
        }
    }

    private function decodeResponse(string $response): ?array
    {
        $response = trim($response);

        $response = preg_replace(
            '/^```(?:json)?\s*|\s*```$/i',
            '',
            $response
        ) ?? $response;

        try {
            $data = json_decode(
                $response,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            return is_array($data) ? $data : null;
        } catch (JsonException) {
            return null;
        }
    }

    private function cleanStringList(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }

            $item = trim($item);

            if ($item !== '') {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
    }

    private function fallback(
        array $experience,
        string $reason
    ): array {
        return array_merge($experience, [
            'projects' => [],
            'responsibilities' => [],
            'review_notes' => $reason,
            'extraction_status' => 'needs_review',
        ]);
    }
}
