<?php

namespace App\Services\AI\CV;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class CvTemplateGenerator
{
    private const WORD_NS =
        'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const XML_NS =
        'http://www.w3.org/XML/1998/namespace';

    public function generate(
        string $templatePath,
        string $outputPath,
        array $data
    ): string {
        if (!is_file($templatePath)) {
            throw new RuntimeException(
                'File template DOCX tidak ditemukan: ' . $templatePath
            );
        }

        $keys = [
            'full_name',
            'title',
            'date_of_birth',
            'education',
            'role',
            'language',
            'experience_background',
            'main_project_experiences',
        ];

        foreach ($keys as $key) {
            $value = (string) ($data[$key] ?? '');

            if ($key === 'experience_background') {
                $data[$key] = trim($value);
                continue;
            }

            $data[$key] = $this->cleanValue($value);
        }

        $directory = dirname($outputPath);

        if (
            !is_dir($directory)
            && !mkdir($directory, 0775, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Folder output tidak dapat dibuat.'
            );
        }

        $zip = new ZipArchive();

        if ($zip->open($templatePath) !== true) {
            throw new RuntimeException(
                'Template DOCX tidak dapat dibuka.'
            );
        }

        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($documentXml === false) {
            throw new RuntimeException(
                'Struktur document.xml tidak ditemukan.'
            );
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        if (!$dom->loadXML($documentXml)) {
            throw new RuntimeException(
                'Konten DOCX tidak dapat dibaca.'
            );
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORD_NS);
        $xpath->registerNamespace(
            'mc',
            'http://schemas.openxmlformats.org/markup-compatibility/2006'
        );

        // Informasi utama kandidat.
        $this->replaceParagraphExact(
            $xpath,
            'Asa Insani',
            $data['full_name']
        );

        $this->replaceParagraphExact(
            $xpath,
            'Project Control Lead',
            $data['title']
        );

        $this->replaceParagraphStartsWith(
            $xpath,
            'Riau, September 19,',
            $data['date_of_birth']
        );

        // Pendidikan: pertahankan placeholder lama dan dukung variasi penulisan.
        $this->replaceParagraphStartsWith(
            $xpath,
            'Bachelor (S1)',
            $data['education']
        );

        $this->replaceParagraphStartsWith(
            $xpath,
            'Bachelor (S-1)',
            $data['education']
        );

        $this->replaceParagraphStartsWith(
            $xpath,
            'Bachelor',
            $data['education']
        );

        $this->replaceParagraphExact(
            $xpath,
            'Project Planning & Control Engineer',
            $data['role']
        );

        // Bagian informasi umum tetap menggunakan struktur template.
        $this->fillSectionAfterHeading(
            $xpath,
            'LANGUAGE',
            $data['language']
        );

        $this->fillSectionAfterHeading(
            $xpath,
            'EXPERIENCE & BACKGROUND',
            trim($data['experience_background']) !== ''
                ? $data['experience_background']
                : '[PERLU DILENGKAPI]'
        );

        // Pengalaman proyek dibuat sebagai paragraf terpisah.
        $this->fillSectionAfterHeading(
            $xpath,
            'MAIN PROJECT EXPERIENCES',
            $data['main_project_experiences'],
            true
        );

        $updatedXml = $dom->saveXML();

        if ($updatedXml === false) {
            throw new RuntimeException(
                'Gagal menyimpan perubahan DOCX.'
            );
        }

        // Salin template ke output. Jika output sudah ada,
        // file tujuan akan ditimpa dengan salinan template terbaru.
        if (!copy($templatePath, $outputPath)) {
            throw new RuntimeException(
                'Gagal menyalin template ke file output.'
            );
        }

        $outputZip = new ZipArchive();

        if ($outputZip->open($outputPath) !== true) {
            throw new RuntimeException(
                'File output DOCX tidak dapat dibuka.'
            );
        }

        if (
            !$outputZip->addFromString(
                'word/document.xml',
                $updatedXml
            )
        ) {
            $outputZip->close();

            throw new RuntimeException(
                'Gagal menulis document.xml ke file output.'
            );
        }

        $outputZip->close();

        return $outputPath;
    }

    private function replaceParagraphExact(
        DOMXPath $xpath,
        string $target,
        string $replacement
    ): void {
        $paragraphs = $xpath->query('//w:body//w:p');

        if (!$paragraphs) {
            return;
        }

        foreach ($paragraphs as $paragraph) {
            if (
                $this->paragraphText($xpath, $paragraph) === $target
            ) {
                $this->setParagraphText(
                    $xpath,
                    $paragraph,
                    $replacement
                );
            }
        }
    }

    private function replaceParagraphStartsWith(
        DOMXPath $xpath,
        string $prefix,
        string $replacement
    ): void {
        $paragraphs = $xpath->query('//w:body//w:p');

        if (!$paragraphs) {
            return;
        }

        foreach ($paragraphs as $paragraph) {
            $text = $this->paragraphText($xpath, $paragraph);

            if (str_starts_with($text, $prefix)) {
                $this->setParagraphText(
                    $xpath,
                    $paragraph,
                    $replacement
                );
            }
        }
    }

    /**
     * Isi bagian template dengan satu atau beberapa paragraf.
     *
     * Untuk pengalaman proyek, konten setelah heading diganti
     * dengan paragraf baru tanpa menghapus properti akhir dokumen.
     */
    private function fillSectionAfterHeading(
        DOMXPath $xpath,
        string $heading,
        string $value,
        bool $multipleParagraphs = false
    ): void {
        $paragraphs = $xpath->query('//w:body//w:p');

        if (!$paragraphs) {
            return;
        }

        $headingParagraph = null;

        foreach ($paragraphs as $paragraph) {
            if (
                mb_strtoupper(
                    trim($this->paragraphText($xpath, $paragraph))
                ) === mb_strtoupper($heading)
            ) {
                $headingParagraph = $paragraph;
                break;
            }
        }

        if (
            !$headingParagraph
            || !$headingParagraph->parentNode
        ) {
            return;
        }

        // Bagian pengalaman proyek merupakan bagian terakhir template.
        if ($multipleParagraphs) {
            $parent = $headingParagraph->parentNode;
            $next = $headingParagraph->nextSibling;

            while ($next) {
                $current = $next;
                $next = $next->nextSibling;

                // Pertahankan properti akhir dokumen Word.
                if (
                    $current instanceof DOMElement
                    && $current->localName === 'sectPr'
                ) {
                    continue;
                }

                $parent->removeChild($current);
            }

            $lines = $this->splitLines($value);

            foreach ($lines as $lineIndex => $line) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $paragraph = $this->createParagraph(
                    $headingParagraph->ownerDocument,
                    $line
                );

                // Buat label Period beserta nilainya menjadi bold.
                if (preg_match('/^Period\s*:/iu', $line)) {
                    $runs = $paragraph->getElementsByTagNameNS(
                        self::WORD_NS,
                        'r'
                    );

                    if ($runs->length > 0) {
                        $run = $runs->item(0);

                        $runProperties = $paragraph->ownerDocument
                            ->createElementNS(self::WORD_NS, 'w:rPr');

                        $bold = $paragraph->ownerDocument
                            ->createElementNS(self::WORD_NS, 'w:b');

                        $runProperties->appendChild($bold);

                        $run->insertBefore(
                            $runProperties,
                            $run->firstChild
                        );
                    }
                }

                $pPr = $headingParagraph->ownerDocument
                    ->createElementNS(self::WORD_NS, 'w:pPr');

                $spacing = $headingParagraph->ownerDocument
                    ->createElementNS(self::WORD_NS, 'w:spacing');

                $afterSpacing = preg_match(
                    '/^Job Description\s*:/iu',
                    $line
                ) ? '240' : '100';

                // Beri jarak sebelum Period pengalaman berikutnya.
                if (!preg_match('/^Period\s*:/iu', $line)) {
                    for (
                        $nextIndex = $lineIndex + 1;
                        $nextIndex < count($lines);
                        $nextIndex++
                    ) {
                        $nextLine = trim($lines[$nextIndex]);

                        if ($nextLine === '') {
                            continue;
                        }

                        if (preg_match('/^Period\s*:/iu', $nextLine)) {
                            $afterSpacing = '400';
                        }

                        break;
                    }
                }

                $spacing->setAttributeNS(
                    self::WORD_NS,
                    'w:after',
                    $afterSpacing
                );

                $spacing->setAttributeNS(
                    self::WORD_NS,
                    'w:line',
                    '276'
                );

                $spacing->setAttributeNS(
                    self::WORD_NS,
                    'w:lineRule',
                    'auto'
                );

                $indent = $headingParagraph->ownerDocument
                    ->createElementNS(self::WORD_NS, 'w:ind');

                $indent->setAttributeNS(
                    self::WORD_NS,
                    'w:left',
                    '0'
                );

                $indent->setAttributeNS(
                    self::WORD_NS,
                    'w:firstLine',
                    '0'
                );

                $pPr->appendChild($indent);
                $pPr->appendChild($spacing);

                $paragraph->insertBefore(
                    $pPr,
                    $paragraph->firstChild
                );

                // Sisipkan sebelum section properties bila tersedia.
                $sectionProperties = null;

                foreach ($parent->childNodes as $child) {
                    if (
                        $child instanceof DOMElement
                        && $child->localName === 'sectPr'
                    ) {
                        $sectionProperties = $child;
                        break;
                    }
                }

                if ($sectionProperties) {
                    $parent->insertBefore(
                        $paragraph,
                        $sectionProperties
                    );
                } else {
                    $parent->appendChild($paragraph);
                }
            }

            return;
        }

        // Untuk LANGUAGE dan EXPERIENCE & BACKGROUND,
        // pertahankan perilaku pengisian paragraf sebelumnya.
        $next = $headingParagraph->nextSibling;

        while (
            $next
            && $next->nodeType !== XML_ELEMENT_NODE
        ) {
            $next = $next->nextSibling;
        }

        $isParagraph = $next instanceof DOMElement
            && $next->localName === 'p'
            && $next->namespaceURI === self::WORD_NS;

        if ($isParagraph) {
            $nextText = trim($this->paragraphText($xpath, $next));

            $isHeading = in_array(
                mb_strtoupper($nextText),
                [
                    'FULL NAME',
                    'DATE OF BIRTH',
                    'EDUCATION',
                    'ROLE',
                    'LANGUAGE',
                    'EXPERIENCE & BACKGROUND',
                    'MAIN PROJECT EXPERIENCES',
                ],
                true
            );

            if (!$isHeading) {
                if (trim($value) === '') {
                    $next->parentNode?->removeChild($next);
                } else {
                    $this->setParagraphText($xpath, $next, $value);
                }

                return;
            }
        }

        $paragraph = $this->createParagraph(
            $headingParagraph->ownerDocument,
            $value
        );

        if ($next) {
            $headingParagraph->parentNode->insertBefore(
                $paragraph,
                $next
            );
        } else {
            $headingParagraph->parentNode->appendChild($paragraph);
        }
    }

    private function createParagraph(
        DOMDocument $dom,
        string $text
    ): DOMElement {
        $paragraph = $dom->createElementNS(
            self::WORD_NS,
            'w:p'
        );

        $run = $dom->createElementNS(
            self::WORD_NS,
            'w:r'
        );

        $textNode = $dom->createElementNS(
            self::WORD_NS,
            'w:t'
        );

        $textNode->appendChild(
            $dom->createTextNode($text)
        );

        $textNode->setAttributeNS(
            self::XML_NS,
            'xml:space',
            'preserve'
        );

        $run->appendChild($textNode);
        $paragraph->appendChild($run);

        return $paragraph;
    }

    private function splitLines(string $value): array
    {
        $value = str_replace(
            ["\r\n", "\r"],
            "\n",
            $value
        );

        $lines = array_map(
            static fn ($line) => trim($line),
            explode("\n", $value)
        );

        return array_values(
            array_filter(
                $lines,
                static fn ($line) => $line !== ''
            )
        );
    }

    private function paragraphText(
        DOMXPath $xpath,
        DOMNode $paragraph
    ): string {
        $nodes = $xpath->query('.//w:t', $paragraph);
        $text = '';

        if ($nodes) {
            foreach ($nodes as $node) {
                $text .= $node->textContent;
            }
        }

        return trim($text);
    }


    private function setParagraphText(
        DOMXPath $xpath,
        DOMNode $paragraph,
        string $text
    ): void {
        $textNodes = $xpath->query('.//w:t', $paragraph);

        if (!$textNodes || $textNodes->length === 0) {
            $dom = $paragraph->ownerDocument;

            $run = $dom->createElementNS(
                self::WORD_NS,
                'w:r'
            );

            $textNode = $dom->createElementNS(
                self::WORD_NS,
                'w:t'
            );

            $textNode->appendChild(
                $dom->createTextNode($text)
            );

            $textNode->setAttributeNS(
                self::XML_NS,
                'xml:space',
                'preserve'
            );

            $run->appendChild($textNode);
            $paragraph->appendChild($run);

            return;
        }

      $first = $textNodes->item(0);

        if ($first !== null) {
            while ($first->firstChild !== null) {
                $first->removeChild($first->firstChild);
            }

            $first->appendChild(
                $first->ownerDocument->createTextNode($text)
            );
        }

        $first->setAttributeNS(
            self::XML_NS,
            'xml:space',
            'preserve'
        );

        for ($i = $textNodes->length - 1; $i >= 1; $i--) {
            $node = $textNodes->item($i);
            $node->parentNode?->removeChild($node);
        }
    }


    private function cleanValue(string $value): string
    {
        $value = trim(
            str_replace(
                ["\r\n", "\r"],
                "\n",
                $value
            )
        );

        return $value === ''
            ? '[PERLU DILENGKAPI]'
            : $value;
    }
}
