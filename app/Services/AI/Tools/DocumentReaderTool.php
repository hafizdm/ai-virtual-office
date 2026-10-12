<?php

namespace App\Services\AI\Tools;

use App\Models\SharePointDocument;
use App\Services\MicrosoftGraphService;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory as ExcelIOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

class DocumentReaderTool implements Tool
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;
    private const MAX_TEXT_LENGTH = 20000;

    // Safety limits for spreadsheet extraction.
    private const MAX_SHEET_ROWS = 500;
    private const MAX_SHEET_COLUMNS = 50;

    public function __construct(
        private MicrosoftGraphService $graph
    ) {
    }

    public function name(): string
    {
        return 'sharepoint_read_document';
    }

    public function description(): string
    {
        return 'Read and extract text from an indexed PDF, DOCX, or XLSX document in the HR-GARII SharePoint Documents library. XLSX reading supports multiple worksheets. Use a document filename or item ID.';
    }

    public function execute(array $arguments): mixed
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        $itemId = trim((string) ($arguments['item_id'] ?? ''));

        if ($query === '' && $itemId === '') {
            throw new InvalidArgumentException(
                'Provide a document query or item_id.'
            );
        }

        $supportedExtensions = ['pdf', 'docx', 'xlsx'];

        $documentQuery = SharePointDocument::query()
            ->whereIn('extension', $supportedExtensions);

        if ($itemId !== '') {
            $documentQuery->where('item_id', $itemId);
        } else {
            $documentQuery->where(
                'name',
                'like',
                '%' . $this->escapeLike($query) . '%'
            );
        }

        $documents = $documentQuery
            ->orderBy('name')
            ->limit(10)
            ->get();

        if ($documents->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No matching PDF, DOCX, or XLSX document was found in the local index.',
                'documents' => [],
            ];
        }

        if ($documents->count() > 1 && $itemId === '') {
            return [
                'success' => true,
                'requires_selection' => true,
                'message' => 'Multiple documents match. Ask the user to select the correct document.',
                'count' => $documents->count(),
                'documents' => $documents->map(
                    fn (SharePointDocument $document) => [
                        'id' => $document->item_id,
                        'name' => $document->name,
                        'path' => $document->path,
                        'extension' => $document->extension,
                        'webUrl' => $document->web_url,
                    ]
                )->values()->all(),
            ];
        }

        $document = $documents->first();

        if (! $document || ! $document->drive_id || ! $document->item_id) {
            throw new RuntimeException(
                'The document metadata is incomplete.'
            );
        }

        $size = (int) ($document->size ?? 0);

        if ($size <= 0) {
            throw new RuntimeException(
                'The document size is unknown or empty. The file cannot be read safely.'
            );
        }

        if ($size > self::MAX_FILE_SIZE) {
            throw new RuntimeException(
                'The document exceeds the 10 MB reading limit.'
            );
        }

        $extension = strtolower((string) $document->extension);

        if (! in_array($extension, $supportedExtensions, true)) {
            throw new RuntimeException(
                'Only PDF, DOCX, and XLSX documents are supported.'
            );
        }

        $content = $this->graph->downloadFile(
            $document->drive_id,
            $document->item_id
        );

        // Validate actual downloaded size as well as indexed metadata.
        if (strlen($content) > self::MAX_FILE_SIZE) {
            throw new RuntimeException(
                'The downloaded document exceeds the 10 MB reading limit.'
            );
        }

        if ($content === '') {
            throw new RuntimeException(
                'The downloaded document is empty.'
            );
        }

        $text = match ($extension) {
            'pdf' => $this->extractPdfText($content),
            'docx' => $this->extractDocxText($content),
            'xlsx' => $this->extractXlsxText($content),
        };

        $text = trim($text);

        if ($text === '') {
            return [
                'success' => false,
                'document' => $document->name,
                'path' => $document->path,
                'extension' => $extension,
                'webUrl' => $document->web_url,
                'message' => 'No readable text was extracted. The file may contain unsupported content, empty cells, or scanned content.',
                'text' => '',
            ];
        }

        $truncated = mb_strlen($text) > self::MAX_TEXT_LENGTH;

        if ($truncated) {
            $text = mb_substr($text, 0, self::MAX_TEXT_LENGTH);
        }

        return [
            'success' => true,
            'document' => $document->name,
            'path' => $document->path,
            'extension' => $extension,
            'webUrl' => $document->web_url,
            'textLength' => mb_strlen($text),
            'truncated' => $truncated,
            'message' => $truncated
                ? 'Document text extracted. Output was truncated to the configured limit.'
                : 'Document text extracted successfully.',
            'text' => $text,
        ];
    }

    private function extractPdfText(string $content): string
    {
        if (! str_starts_with($content, '%PDF-')) {
            throw new RuntimeException(
                'The downloaded content does not appear to be a valid PDF.'
            );
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseContent($content);

            return $pdf->getText();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Failed to extract text from the PDF document.',
                previous: $exception
            );
        }
    }

    private function extractDocxText(string $content): string
    {
        if (! str_starts_with($content, "PK")) {
            throw new RuntimeException(
                'The downloaded content does not appear to be a valid DOCX file.'
            );
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'rapid_docx_');

        if ($temporaryFile === false) {
            throw new RuntimeException(
                'Could not create a temporary file for DOCX extraction.'
            );
        }

        try {
            if (file_put_contents($temporaryFile, $content) === false) {
                throw new RuntimeException(
                    'Could not write the temporary DOCX file.'
                );
            }

            $word = WordIOFactory::load($temporaryFile, 'Word2007');

            return $this->extractWordElements($word->getSections());
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Failed to extract text from the DOCX document.',
                previous: $exception
            );
        } finally {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }

    private function extractXlsxText(string $content): string
    {
        if (! str_starts_with($content, 'PK')) {
            throw new RuntimeException(
                'The downloaded content does not appear to be a valid XLSX file.'
            );
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'rapid_xlsx_');

        if ($temporaryFile === false) {
            throw new RuntimeException(
                'Could not create a temporary file for XLSX extraction.'
            );
        }

        try {
            if (file_put_contents($temporaryFile, $content) === false) {
                throw new RuntimeException(
                    'Could not write the temporary XLSX file.'
                );
            }

            $reader = ExcelIOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $spreadsheet = $reader->load($temporaryFile);

            try {
                $lines = [];
                $textLength = 0;
                $limit = self::MAX_TEXT_LENGTH + 1;

                foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
                    if ($textLength >= $limit) {
                        break;
                    }

                    $sheetText = $this->extractWorksheetText(
                        $worksheet,
                        $limit - $textLength
                    );

                    if ($sheetText === '') {
                        continue;
                    }

                    $section = 'Worksheet: ' . $worksheet->getTitle()
                        . "\n" . $sheetText;

                    $remaining = $limit - $textLength;

                    if (mb_strlen($section) > $remaining) {
                        $section = mb_substr($section, 0, $remaining);
                    }

                    $lines[] = $section;
                    $textLength += mb_strlen($section);
                }

                return implode("\n\n", $lines);
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Failed to extract text from the XLSX document.',
                previous: $exception
            );
        } finally {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }

    private function extractWorksheetText(
        Worksheet $worksheet,
        int $remainingCharacters
    ): string {
        if ($remainingCharacters <= 0) {
            return '';
        }

        $highestRow = min(
            $worksheet->getHighestDataRow(),
            self::MAX_SHEET_ROWS
        );

        $highestColumnIndex = min(
            \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
                $worksheet->getHighestDataColumn()
            ),
            self::MAX_SHEET_COLUMNS
        );

        $lines = [];
        $length = 0;

        for ($row = 1; $row <= $highestRow; $row++) {
            $values = [];

            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $cell = $worksheet->getCell([$column, $row]);
                $value = $cell->getFormattedValue();

                if ($value === null || trim((string) $value) === '') {
                    continue;
                }

                $values[] = (string) $value;
            }

            if ($values === []) {
                continue;
            }

            $line = implode(' | ', $values);
            $remaining = $remainingCharacters - $length;

            if ($remaining <= 0) {
                break;
            }

            if (mb_strlen($line) > $remaining) {
                $line = mb_substr($line, 0, $remaining);
            }

            $lines[] = $line;
            $length += mb_strlen($line) + 1;

            if ($length >= $remainingCharacters) {
                break;
            }
        }

        return implode("\n", $lines);
    }

    private function extractWordElements(iterable $elements): string
    {
        $lines = [];

        foreach ($elements as $element) {
            if (method_exists($element, 'getText')) {
                $value = $element->getText();

                if (is_string($value) && trim($value) !== '') {
                    $lines[] = $value;
                }

                continue;
            }

            if (method_exists($element, 'getRows')) {
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $lines[] = $this->extractWordElements(
                            $cell->getElements()
                        );
                    }
                }

                continue;
            }

            if (method_exists($element, 'getElements')) {
                $nested = $element->getElements();

                if (is_iterable($nested)) {
                    $lines[] = $this->extractWordElements($nested);
                }
            }
        }

        return implode("\n", array_filter($lines));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }
}
