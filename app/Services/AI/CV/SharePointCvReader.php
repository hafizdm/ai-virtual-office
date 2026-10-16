<?php

namespace App\Services\AI\CV;

use App\Services\MicrosoftGraphService;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;
use Throwable;

class SharePointCvReader
{
    public function __construct(
        private readonly MicrosoftGraphService $graph
    ) {
    }

    
    /**
     * Mengambil daftar file DOCX dari folder SharePoint.
     */
    public function listDocxFiles(
        string $driveId,
        string $folderItemId
    ): array {
        if (trim($driveId) === '' || trim($folderItemId) === '') {
            throw new RuntimeException(
                'Drive ID dan folder ID SharePoint wajib diisi.'
            );
        }

        $endpoint = 'drives/' . rawurlencode($driveId)
            . '/items/' . rawurlencode($folderItemId)
            . '/children?$select=id,name,file,folder,size,lastModifiedDateTime,webUrl&$top=200';

        $items = $this->graph->getAllPages($endpoint);
        $files = [];

        foreach ($items as $item) {
            $name = $item['name'] ?? '';
            $itemId = $item['id'] ?? '';

            // Hanya ambil file DOCX, bukan folder.
            if (
                ! isset($item['file'])
                || ! is_string($name)
                || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'docx'
                || ! is_string($itemId)
                || $itemId === ''
            ) {
                continue;
            }

            $files[] = [
                'id' => $itemId,
                'name' => $name,
                'size' => $item['size'] ?? null,
                'last_modified' => $item['lastModifiedDateTime'] ?? null,
                'web_url' => $item['webUrl'] ?? null,
            ];
        }

        return $files;
    }



    public function readDocx(string $driveId, string $itemId): array
    {
        $bytes = $this->graph->downloadFile($driveId, $itemId);

        if ($bytes === '') {
            throw new RuntimeException(
                'File CV yang diunduh dari SharePoint kosong.'
            );
        }

        if (strlen($bytes) > 10 * 1024 * 1024) {
            throw new RuntimeException(
                'Ukuran CV melebihi batas pembacaan 10 MB.'
            );
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'rapid-cv-');

        if ($tempPath === false) {
            throw new RuntimeException(
                'Gagal membuat file sementara untuk membaca CV.'
            );
        }

        try {
            if (file_put_contents($tempPath, $bytes) === false) {
                throw new RuntimeException(
                    'Gagal menyimpan CV sementara untuk dibaca.'
                );
            }

            $phpWord = IOFactory::load($tempPath, 'Word2007');

            $lines = [];
            $tables = [];

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if ($element instanceof Table) {
                        $tables[] = $this->extractTable($element);
                        continue;
                    }

                    $this->collectElementText($element, $lines);
                }
            }

            $textParts = $lines;

            foreach ($tables as $table) {
                foreach ($table as $row) {
                    $cells = array_values(array_filter(
                        array_map(
                            fn ($cell) => $this->normalizeCellText(
                                (string) $cell
                            ),
                            $row
                        ),
                        static fn ($cell) => $cell !== ''
                    ));

                    if ($cells !== []) {
                        $textParts[] = implode(' | ', $cells);
                    }
                }
            }

            $text = trim(implode("\n", array_filter(
                array_map(
                    static fn ($line) => trim((string) $line),
                    $textParts
                ),
                static fn ($line) => $line !== ''
            )));

            if ($text === '') {
                throw new RuntimeException(
                    'DOCX berhasil diunduh, tetapi teks tidak berhasil diekstrak.'
                );
            }

            return [
                'success' => true,
                'characters' => mb_strlen($text),
                'text' => mb_substr($text, 0, 30000),
                'tables' => $tables,
                'structured_education' => $this->buildStructuredEducation(
                    $tables
                ),
                'structured_experiences' => $this->buildStructuredExperiences(
                    $tables
                ),
            ];
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Gagal membaca isi DOCX dari SharePoint: '
                . $exception->getMessage(),
                previous: $exception
            );
        } finally {
            @unlink($tempPath);
        }
    }

    private function extractTable(Table $table): array
    {
        $rows = [];

        foreach ($table->getRows() as $row) {
            $cells = [];

            foreach ($row->getCells() as $cell) {
                $cellLines = [];

                foreach ($cell->getElements() as $element) {
                    if ($element instanceof Table) {
                        $nestedRows = $this->extractTable($element);

                        foreach ($nestedRows as $nestedRow) {
                            $cellLines[] = implode(' | ', $nestedRow);
                        }

                        continue;
                    }

                    $this->collectElementText($element, $cellLines);
                }

                $cells[] = $this->cleanCellText($cellLines);
            }

            if (array_filter(
                $cells,
                static fn ($value) => $value !== ''
            ) !== []) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    
private function collectElementText(mixed $element, array &$lines): void
{
    if (!is_object($element) || $element instanceof Table) {
        return;
    }

    // Gabungkan semua text run dalam satu container/paragraf.
    if (method_exists($element, 'getElements')) {
        $text = $this->collectInlineText($element);

        if (trim($text) !== '') {
            $lines[] = $text;
        }

        return;
    }

    if (method_exists($element, 'getText')) {
        $value = $element->getText();

        if (is_string($value) && trim($value) !== '') {
            $lines[] = $value;
        }
    }
}

/**
 * Mengambil teks dari elemen bersarang tanpa memecah setiap text run
 * menjadi baris terpisah.
 */
private function collectInlineText(mixed $element): string
{
    if (!is_object($element) || $element instanceof Table) {
        return '';
    }

    if (method_exists($element, 'getText')) {
        $value = $element->getText();

        if (is_string($value)) {
            return $value;
        }
    }

    if (method_exists($element, 'getElements')) {
        $text = '';

        foreach ($element->getElements() as $child) {
            $text .= $this->collectInlineText($child);
        }

        return $text;
    }

    return '';
}


    private function cleanCellText(array $lines): string
    {
        $cleanLines = [];
        $previousLine = null;

        foreach ($lines as $line) {
            $line = html_entity_decode(
                trim((string) $line),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );

            $line = preg_replace('/\s+/u', ' ', $line) ?? $line;

            if ($line === '' || $line === ',') {
                continue;
            }

            if ($line === $previousLine) {
                continue;
            }

            $cleanLines[] = $line;
            $previousLine = $line;
        }

        return implode("\n", $cleanLines);
    }

    /**
     * Normalisasi teks sel untuk pemetaan data.
     * Penggabungan angka hanya dilakukan pada kolom tahun pendidikan.
     */
    private function normalizeCellText(string $value): string
    {
        $value = html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $lines = preg_split('/\R/u', $value) ?: [];
        $result = [];
        $previous = null;

        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);

            if ($line === '' || $line === ',') {
                continue;
            }

            if ($line === $previous) {
                continue;
            }

            $result[] = $line;
            $previous = $line;
        }

        return implode(' ', $result);
    }

    /**
     * Mengambil data pendidikan dari tabel berdasarkan nama header.
     */
    private function buildStructuredEducation(array $tables): array
    {
        foreach ($tables as $table) {
            if (!isset($table[0]) || !is_array($table[0])) {
                continue;
            }

            $header = array_map(
                fn ($cell) => mb_strtolower(
                    $this->normalizeCellText((string) $cell)
                ),
                $table[0]
            );

            $indexes = [];

            foreach ($header as $index => $cell) {
                if (in_array($cell, ['graduate', 'degree', 'qualification'], true)) {
                    $indexes['degree'] = $index;
                } elseif (
                    str_contains($cell, 'university')
                    || str_contains($cell, 'institution')
                    || str_contains($cell, 'school')
                ) {
                    $indexes['institution'] = $index;
                } elseif ($cell === 'place' || str_contains($cell, 'location')) {
                    $indexes['place'] = $index;
                } elseif (str_contains($cell, 'major') || str_contains($cell, 'field of study')) {
                    $indexes['major'] = $index;
                } elseif (str_contains($cell, 'graduation') && str_contains($cell, 'year')) {
                    $indexes['graduation_year'] = $index;
                }
            }

            if (
                !isset($indexes['degree'])
                || !isset($indexes['institution'])
                || !isset($indexes['major'])
            ) {
                continue;
            }

            $education = [];

            foreach (array_slice($table, 1) as $row) {
                $degree = $this->cellAt($row, $indexes['degree']);
                $institution = $this->cellAt($row, $indexes['institution']);
                $place = isset($indexes['place'])
                    ? $this->cellAt($row, $indexes['place'])
                    : '';
                $major = $this->cellAt($row, $indexes['major']);
                $graduationYear = isset($indexes['graduation_year'])
                    ? $this->cellAt($row, $indexes['graduation_year'])
                    : '';

                // Gabungkan spasi di antara digit hanya pada kolom tahun.
                $graduationYear = preg_replace(
                    '/(?<=\d)\s+(?=\d)/u',
                    '',
                    $graduationYear
                ) ?? $graduationYear;

                if (
                    $degree === ''
                    && $institution === ''
                    && $major === ''
                ) {
                    continue;
                }

                $education[] = [
                    'degree' => $degree,
                    'institution' => $institution,
                    'place' => $place,
                    'major' => $major,
                    'graduation_year' => $graduationYear,
                    'extraction_status' => 'needs_review',
                ];
            }

            if ($education !== []) {
                return $education;
            }
        }

        return [];
    }

    /**
     * Mencari tabel pengalaman berdasarkan header.
     */
    private function buildStructuredExperiences(array $tables): array
    {
        $experiences = [];

        foreach ($tables as $table) {
            if (!isset($table[0]) || !is_array($table[0])) {
                continue;
            }

            $header = array_map(
                fn ($cell) => mb_strtolower(
                    $this->normalizeCellText((string) $cell)
                ),
                $table[0]
            );

            $indexes = [];

            foreach ($header as $index => $cell) {
                if (in_array($cell, ['year', 'period'], true)) {
                    $indexes['period'] = $index;
                } elseif ($cell === 'project' || str_contains($cell, 'project')) {
                    $indexes['project'] = $index;
                } elseif (str_contains($cell, 'company') && str_contains($cell, 'position')) {
                    $indexes['company_position'] = $index;
                }
            }

            if (
                !isset($indexes['period'])
                || !isset($indexes['project'])
                || !isset($indexes['company_position'])
            ) {
                continue;
            }

            foreach (array_slice($table, 1) as $row) {
                $period = $this->cellAt($row, $indexes['period']);
                $projectText = $this->cellAt($row, $indexes['project']);
                $companyPositionText = $this->cellAt(
                    $row,
                    $indexes['company_position']
                );

                if (
                    $period === ''
                    && $projectText === ''
                    && $companyPositionText === ''
                ) {
                    continue;
                }

                [$company, $position] = $this->splitCompanyAndPosition(
                    $companyPositionText
                );

                $experiences[] = [
                    'period' => $period,
                    'company' => $company,
                    'position' => $position,
                    'raw_project_text' => $projectText,
                    'projects' => [],
                    'responsibilities' => [],
                    'extraction_status' => 'needs_review',
                ];
            }

            if ($experiences !== []) {
                break;
            }
        }

        return $experiences;
    }

    private function cellAt(array $row, int $index): string
    {
        return $this->normalizeCellText((string) ($row[$index] ?? ''));
    }

    /**
     * Memisahkan perusahaan dan jabatan berdasarkan slash pertama.
     */
    private function splitCompanyAndPosition(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return ['', ''];
        }

        $parts = explode('/', $value, 2);

        $company = trim($parts[0] ?? '');
        $position = trim($parts[1] ?? '');

        return [$company, $position];
    }
}
