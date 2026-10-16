<?php

namespace App\Services\AI\CV;

use RuntimeException;

class CvTenderProcessor
{
    public function __construct(
        private readonly SharePointCvReader $reader,
        private readonly CvExperienceExtractor $extractor,
        private readonly CvTemplateGenerator $generator,
    ) {
    }

    /**
     * Memproses satu CV dari SharePoint menjadi DOCX.
     *
     * Informasi hanya diambil jika ditemukan dalam sumber.
     * Hasil ekstraksi tetap berstatus needs_review.
     */
    public function process(
        string $driveId,
        string $itemId,
        string $templatePath,
        string $outputPath,
        string $sourceFilename,
    ): array {
        if (!is_file($templatePath)) {
            throw new RuntimeException(
                'Template CV tidak ditemukan: ' . $templatePath
            );
        }

        // 1. Baca CV sumber dari SharePoint.
        $cv = $this->reader->readDocx($driveId, $itemId);

        $sourceText = trim((string) ($cv['text'] ?? ''));

        if (!($cv['success'] ?? false) || $sourceText === '') {
            throw new RuntimeException(
                'Isi CV sumber tidak berhasil dibaca.'
            );
        }

        $experiences = $cv['structured_experiences'] ?? [];

        if ($experiences === []) {
            throw new RuntimeException(
                'Pengalaman kerja tidak ditemukan dalam CV sumber.'
            );
        }

        // 2. Ekstrak proyek dan tanggung jawab per pengalaman.
        $extractedExperiences = [];

        foreach ($experiences as $experience) {
            $extractedExperiences[] = $this->extractor->extract(
                $experience
            );
        }

        // 3. Ambil informasi profil yang memiliki label jelas
        //    dalam teks CV sumber. Jangan mengarang informasi.
       
        $fullName = $this->extractLabeledField(
            $sourceText,
            [
                'full name',
                'candidate name',
                'name',
                'nama lengkap',
                'nama kandidat',
                'nama',
            ]
        );

        if (
            $fullName === '[PERLU DILENGKAPI]'
            || trim($fullName) === ''
        ) {
            $fullName = $this->candidateNameFromFilename(
                $sourceFilename
            );
        }


       
        $dateOfBirth = $this->extractLabeledField(
            $sourceText,
            [
                'date of birth',
                'place and date of birth',
                'place & date of birth',
                'birth date',
                'tanggal lahir',
                'tempat tanggal lahir',
                'tempat, tanggal lahir',
                'dob',
            ]
        );

        $structuredEducation = $cv['structured_education'] ?? [];

        $education = $this->formatStructuredEducation($structuredEducation);

        if ($education === '') {
            $education = $this->extractLabeledField(
                $sourceText,
                [
                    'education',
                    'educational background',
                    'academic background',
                    'educational qualification',
                    'qualification',
                    'pendidikan',
                    'riwayat pendidikan',
                ]
            );
        }

        $role = $this->extractLabeledField(
            $sourceText,
            [
                'role',
                'position',
                'proposed position',
                'position applied for',
                'position offered',
                'tender position',
                'posisi yang diusulkan',
                'posisi tender',
            ]
        );

        $language = $this->extractLabeledField(
            $sourceText,
            [
                'language',
                'languages',
                'language proficiency',
                'bahasa',
                'kemampuan bahasa',
            ]
        );

       // 4. Experience & Background hanya diisi jika sumber
        //    benar-benar mempunyai bagian ringkasan sejenis.
        $experienceBackground = $this->extractSummarySection(
            $sourceText
        );

        // 5. Susun pengalaman proyek untuk template.
        $projectLines = [];

        foreach ($extractedExperiences as $experience) {
            $period = trim((string) ($experience['period'] ?? ''));
            $company = trim((string) ($experience['company'] ?? ''));
            $position = trim((string) ($experience['position'] ?? ''));

            $projectLines[] = 'Period: ' . (
                $period !== '' ? $period : '[PERLU DILENGKAPI]'
            );

            $projectLines[] = 'Company: ' . (
                $company !== '' ? $company : '[PERLU DILENGKAPI]'
            );

            $projectLines[] = 'Position: ' . (
                $position !== '' ? $position : '[PERLU DILENGKAPI]'
            );

            $projectLines[] = 'Projects:';

            $projects = $experience['projects'] ?? [];

            if ($projects === []) {
                $projectLines[] = '[PERLU DILENGKAPI]';
            } else {
                foreach ($projects as $project) {
                    $projectLines[] = '- ' . $project;
                }
            }

            $projectLines[] = 'Job Description:';

            $responsibilities = $experience['responsibilities'] ?? [];

            if ($responsibilities === []) {
                $projectLines[] = '[PERLU DILENGKAPI]';
            } else {
                foreach ($responsibilities as $responsibility) {
                    $projectLines[] = '- ' . $responsibility;
                }
            }

            // Pemisah antarpengalaman.
            // Generator akan menangani format dan jarak paragraf.
            $projectLines[] = '';
        }

        $data = [
            'full_name' => $fullName,
            'title' => '[PERLU DILENGKAPI]',
            'date_of_birth' => $dateOfBirth,
            'education' => $education,
            'role' => $role,
            'language' => $language,
            'experience_background' => $experienceBackground,
            'main_project_experiences' => implode(
                "\n",
                $projectLines
            ),
        ];

        // 6. Hasilkan DOCX menggunakan generator yang sudah ada.
        $generatedPath = $this->generator->generate(
            $templatePath,
            $outputPath,
            $data
        );

        // 7. Siapkan catatan untuk pemeriksaan manual.
        $reviewItems = [];

        foreach ($extractedExperiences as $index => $experience) {
            $reviewItems[] = [
                'experience_number' => $index + 1,
                'period' => $experience['period'] ?? '',
                'company' => $experience['company'] ?? '',
                'position' => $experience['position'] ?? '',
                'extraction_status' =>
                    $experience['extraction_status'] ?? 'needs_review',
                'review_notes' => $experience['review_notes'] ?? '',
                'source_text' => $experience['raw_project_text'] ?? '',
                'projects' => $experience['projects'] ?? [],
                'responsibilities' =>
                    $experience['responsibilities'] ?? [],
            ];
        }

        $profileFields = [
            'date_of_birth' => $dateOfBirth,
            'education' => $education,
            'role' => $role,
            'language' => $language,
        ];

        $missingFields = [];

        foreach ($profileFields as $field => $value) {
            if ($value === '[PERLU DILENGKAPI]') {
                $missingFields[] = $field;
            }
        }

        return [
            'success' => true,
            'source_filename' => $sourceFilename,
            'output_path' => $generatedPath,
            'candidate_name' => $fullName,
            'source_characters' => $cv['characters'] ?? 0,
            'experience_count' => count($extractedExperiences),
            'extraction_status' => 'needs_review',
            'experiences' => $reviewItems,
            'missing_fields' => $missingFields,
            'message' =>
                'DOCX berhasil dibuat. Periksa hasil ekstraksi dan '
                . 'lengkapi informasi yang belum tersedia sebelum '
                . 'menggunakan CV untuk tender.',
        ];
    }

    /**
     * Mengambil nilai setelah label yang dikenal.
     * Jika label tidak ditemukan, gunakan placeholder.
     */

    private function extractLabeledField(
        string $text,
        array $labels
    ): string {
        $normalizedText = html_entity_decode(
            $text,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $normalizedText = str_replace(
            ["\r\n", "\r"],
            "\n",
            $normalizedText
        );

        $lines = preg_split('/\n/u', $normalizedText) ?: [];

        // Gabungkan baris label yang terpecah oleh pembaca DOCX.
        $normalizedLines = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = trim($lines[$i]);

            
            if ($line === '') {
                continue;
            }

            // Prioritaskan label dan nilai yang sudah berada
            // pada baris yang sama sebelum menggabungkan baris lain.
            foreach ($labels as $label) {
                $pattern = '/^\s*'
                    . preg_quote($label, '/')
                    . '\s*(?::|-|–|—)\s*(.+?)\s*$/iu';

                if (preg_match($pattern, $line, $matches)) {
                    $value = trim($matches[1]);

                    if ($value !== '') {
                        return $value;
                    }
                }
            }

            // Jika belum ditemukan, baru coba menangani
            // label yang terpecah oleh pembaca DOCX.
            $combined = $line;


            for ($j = $i + 1; $j < min($i + 6, $count); $j++) {
                $next = trim($lines[$j]);

                if ($next === '') {
                    continue;
                }

                $combined .= ' ' . $next;

                foreach ($labels as $label) {
                    $labelPattern = '/^\s*'
                        . preg_quote($label, '/')
                        . '\s*(?::|-|–|—)\s*(.*)$/iu';

                    if (preg_match($labelPattern, $combined, $matches)) {
                        $value = trim($matches[1]);

                        if ($value !== '') {
                            return $value;
                        }
                    }
                }

                // Jangan gabungkan melewati label baru.
                if (preg_match('/^\s*[^:]{1,50}\s*:\s*$/u', $next)) {
                    break;
                }
            }

            $normalizedLines[] = $line;
        }

        // Pencarian biasa untuk label dan nilai pada baris yang sama.
        foreach ($normalizedLines as $line) {
            foreach ($labels as $label) {
                $pattern = '/^\s*'
                    . preg_quote($label, '/')
                    . '\s*(?::|-|–|—)\s*(.+?)\s*$/iu';

                if (preg_match($pattern, $line, $matches)) {
                    $value = trim($matches[1]);

                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return '[PERLU DILENGKAPI]';
    }
   /**
     * Mengambil bagian ringkasan hanya jika judul bagiannya
     * memang ditemukan di CV sumber.
     *
     * Tidak menggunakan daftar riwayat pekerjaan sebagai
     * pengganti ringkasan kandidat.
     */

    private function formatStructuredEducation(array $education): string
    {
        if ($education === []) {
            return '';
        }

        $entries = [];

        foreach ($education as $item) {
            $degree = trim((string) ($item['degree'] ?? ''));
            $institution = trim((string) ($item['institution'] ?? ''));
            $place = trim((string) ($item['place'] ?? ''));
            $major = trim((string) ($item['major'] ?? ''));
            $year = trim((string) ($item['graduation_year'] ?? ''));

            $parts = array_filter(
                [$degree, $major, $institution, $place, $year],
                static fn ($value) => $value !== ''
            );

            if ($parts !== []) {
                $entries[] = implode(' | ', $parts);
            }
        }

        return implode("\n", $entries);
    }

    

    private function extractSummarySection(string $text): string
    {
        $text = html_entity_decode(
            $text,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $lines = preg_split('/\R/u', $text) ?: [];

        $normalize = static function (string $value): string {
            $value = mb_strtolower(trim($value));
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

            return trim($value, " \t\n\r\0\x0B:");
        };

        // Cari awal bagian ringkasan.
        $startIndex = null;
        $lineCount = count($lines);

        for ($i = 0; $i < $lineCount; $i++) {
            $current = $normalize((string) $lines[$i]);

            if (in_array($current, [
                'experience & background',
                'experience and background',
                'professional summary',
                'executive summary',
                'career summary',
                'profile summary',
                'professional profile',
                'ringkasan profil',
                'profil profesional',
            ], true)) {
                $startIndex = $i + 1;
                break;
            }

            // Judul "Summary of Experience" dapat terpecah menjadi beberapa baris.
            if ($current === 'summary') {
                $fragments = '';

                for ($j = $i + 1; $j < min($i + 5, $lineCount); $j++) {
                    $fragments .= preg_replace(
                        '/\s+/u',
                        '',
                        $normalize((string) $lines[$j])
                    ) ?? '';

                    if (str_contains($fragments, 'ofexperience')) {
                        $startIndex = $j + 1;
                        break 2;
                    }
                }
            }
        }

        if ($startIndex === null) {
            return '';
        }

        // Ambil ringkasan sampai bagian dokumen berikutnya.
        $summaryLines = [];

        $endTitles = [
            'main project experiences',
            'project experiences',
            'work experience',
            'professional experience',
            'employment history',
            'education',
            'educational background',
            'academic background',
            'training',
            'languages',
            'language',
            'skills',
            'certification',
            'certifications',
            'references',
        ];

        for ($i = $startIndex; $i < $lineCount; $i++) {
            $line = trim((string) $lines[$i]);

            if ($line === '') {
                continue;
            }

            if (in_array($normalize($line), $endTitles, true)) {
                break;
            }

            if (preg_match('/^no\s*\|\s*(graduate|year)\s*\|/iu', $line)) {
                break;
            }

            $summaryLines[] = $line;
        }

        /*
        * Tahap 1:
        * Gabungkan fragmen yang jelas berasal dari pemecahan teks Word.
        * Baris yang tidak cocok dengan aturan tetap dipertahankan.
        */
        $cleanLines = [];
        $count = count($summaryLines);

        for ($i = 0; $i < $count; $i++) {
            $line = trim($summaryLines[$i]);

            if ($line === '') {
                continue;
            }

            // "1" + "7" + "years (2004 - Now)" menjadi
            // "17 years (2004 - Now)".
            if (
                preg_match('/^[1-9]$/u', $line)
                && isset($summaryLines[$i + 1], $summaryLines[$i + 2])
                && preg_match('/^\d$/u', trim($summaryLines[$i + 1]))
                && preg_match(
                    '/^years?\b/iu',
                    trim($summaryLines[$i + 2])
                )
            ) {
                $line .= trim($summaryLines[++$i]);
                $line .= ' ' . trim($summaryLines[++$i]);
            } elseif (
                preg_match('/^\d{1,2}$/u', $line)
                && isset($summaryLines[$i + 1])
                && preg_match('/^years?\b/iu', trim($summaryLines[$i + 1]))
            ) {
                // "2" + "years as Project Advisor..." menjadi satu baris.
                $line .= ' ' . trim($summaryLines[++$i]);
            }

            // Tahun terpecah, misalnya "1997-200" + "4" + ")".
            if (
                preg_match('/(?:^|[^\d])\d{3}$/u', $line)
                && isset($summaryLines[$i + 1])
                && preg_match('/^\d$/u', trim($summaryLines[$i + 1]))
                && preg_match('/\d{4}$/u', $line . trim($summaryLines[$i + 1]))
            ) {
                $line .= trim($summaryLines[++$i]);

                if (
                    isset($summaryLines[$i + 1])
                    && trim($summaryLines[$i + 1]) === ')'
                ) {
                    $line .= trim($summaryLines[++$i]);
                }
            }

            // Gabungkan "Kaltim" + "4 Project, 2000-2002".
            if (
                preg_match('/^Kaltim$/iu', $line)
                && isset($summaryLines[$i + 1])
                && preg_match(
                    '/^\d+\s+Project\b/iu',
                    trim($summaryLines[$i + 1])
                )
            ) {
                $line .= ' ' . trim($summaryLines[++$i]);
            }

            // Gabungkan nama proyek yang terpecah menjadi tiga baris:
            // "CDU Revamp" + "Balongan" + "Project, 2019 - 2021".
            if (
                preg_match('/\b(CDU Revamp|Cement)$/iu', $line)
                && isset($summaryLines[$i + 1], $summaryLines[$i + 2])
                && preg_match(
                    '/^(Balongan|Tonasa|Tuban)$/iu',
                    trim($summaryLines[$i + 1])
                )
                && preg_match(
                    '/^Project\b/iu',
                    trim($summaryLines[$i + 2])
                )
            ) {
                $line .= ' ' . trim($summaryLines[++$i]);
                $line .= ' ' . trim($summaryLines[++$i]);
            }

            // Jika baris berikutnya dimulai dengan ", 20", sambungkan
            // ke entri proyek sebelumnya sebelum merapikan tahun.
            if (
                isset($summaryLines[$i + 1])
                && preg_match('/^,\s*20$/u', trim($summaryLines[$i + 1]))
                && isset($summaryLines[$i + 2])
                && preg_match('/^\d{2}$/u', trim($summaryLines[$i + 2]))
            ) {
                $line .= ', 20' . trim($summaryLines[$i + 2]);
                $i += 2;

                // Tangani tanda pisah dan tahun kedua yang terpecah.
                if (
                    isset($summaryLines[$i + 1])
                    && preg_match('/^[–-]$/u', trim($summaryLines[$i + 1]))
                    && isset($summaryLines[$i + 2])
                    && preg_match('/^20$/u', trim($summaryLines[$i + 2]))
                    && isset($summaryLines[$i + 3])
                    && preg_match('/^\d{2}$/u', trim($summaryLines[$i + 3]))
                ) {
                    $line .= '–20' . trim($summaryLines[$i + 3]);
                    $i += 3;
                }
            }

            $cleanLines[] = $line;
        }

        /*
        * Tahap 2:
        * Rapikan tanda baca dan spasi tanpa menggabungkan seluruh
        * blok pengalaman menjadi satu paragraf.
        */
        foreach ($cleanLines as &$cleanLine) {
            $cleanLine = preg_replace(
                '/\s+([,.;:)])/u',
                '$1',
                $cleanLine
            ) ?? $cleanLine;

            $cleanLine = preg_replace(
                '/\s*[–-]\s*/u',
                '–',
                $cleanLine
            ) ?? $cleanLine;
        }

        unset($cleanLine);

        return trim(implode("\n", $cleanLines));
    }




    private function candidateNameFromFilename(
        string $filename
    ): string {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        $name = preg_replace(
            '/^CV[\s_-]*/iu',
            '',
            $name
        ) ?? $name;

        $name = trim($name);

        return $name !== ''
            ? $name
            : '[PERLU DILENGKAPI]';
    }
}
