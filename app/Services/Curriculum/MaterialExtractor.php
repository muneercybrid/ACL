<?php

namespace App\Services\Curriculum;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Extracts text from uploaded teaching material so it can be turned into
 * catalogue content.
 *
 * Supported: PDF (via pdftotext), DOCX, PPTX, and plain text/Markdown.
 *
 * DOCX and PPTX are not parsed with a library here. Both formats are a ZIP of
 * XML parts -- a DOCX is `word/document.xml`, a PPTX is one XML part per slide
 * -- so reading the XML directly is a few lines, adds no Composer dependency,
 * and cannot be broken by a library's major-version churn. Adding smalot or
 * phpoffice for this would be a large dependency for a small job.
 *
 * The extractor is deliberately naive about layout. It recovers the text and
 * its heading structure, which is what chapter authoring needs; it does not
 * attempt fidelity to the original page, because the output is going to be
 * restructured into chapters anyway.
 */
class MaterialExtractor
{
    public const MAX_BYTES = 20 * 1024 * 1024;

    public const SUPPORTED = ['pdf', 'docx', 'pptx', 'txt', 'md', 'markdown'];

    /**
     * Extracts structured text from an uploaded file.
     *
     * @return array{
     *     text: string,
     *     headings: array<int, string>,
     *     meta: array<string, string>
     * }
     */
    public function extract(string $absolutePath, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (! in_array($extension, self::SUPPORTED, true)) {
            throw new RuntimeException(
                "Unsupported file type '{$extension}'. Supported: " . implode(', ', self::SUPPORTED)
            );
        }

        if (! is_readable($absolutePath)) {
            throw new RuntimeException("Uploaded file could not be read: {$originalName}");
        }

        $size = filesize($absolutePath);

        if ($size > self::MAX_BYTES) {
            throw new RuntimeException(
                "File is " . round($size / 1048576, 1) . ' MB; the limit is ' . (self::MAX_BYTES / 1048576) . ' MB.'
            );
        }

        return match ($extension) {
            'pdf' => $this->fromPdf($absolutePath, $originalName),
            'docx' => $this->fromDocx($absolutePath, $originalName),
            'pptx' => $this->fromPptx($absolutePath, $originalName),
            default => $this->fromPlainText($absolutePath, $originalName),
        };
    }

    private function fromPdf(string $path, string $name): array
    {
        $binary = trim((string) shell_exec(
            'pdftotext -layout -enc UTF-8 ' . escapeshellarg($path) . ' - 2>/dev/null'
        ));

        if ($binary === '') {
            // Almost always a scanned document with no text layer. Saying so is
            // far more useful than importing an empty chapter.
            throw new RuntimeException(
                "No text could be extracted from {$name}. If it is a scanned document, "
                .'it needs OCR before it can be imported.'
            );
        }

        return $this->structure($binary, ['source' => 'pdftotext', 'file' => $name]);
    }

    private function fromDocx(string $path, string $name): array
    {
        $xml = $this->partFromZip($path, 'word/document.xml');

        if ($xml === null) {
            throw new RuntimeException("{$name} does not look like a valid .docx file.");
        }

        $xml = preg_replace('#<w:tab[^>]*/>#', "\t", $xml) ?? $xml;
        $xml = preg_replace('#<w:br[^>]*/>#', "\n", $xml) ?? $xml;

        // Heading styles are marked by w:pStyle val="Heading1" etc.
        // Convert a Heading-styled paragraph into markdown, keeping its text.
        // The earlier version replaced the whole paragraph with an empty marker
        // and threw the heading away, which is the one thing worth recovering
        // from a document.
        $xml = preg_replace_callback(
            '#<w:p\b[^>]*>(?:(?!</w:p>).)*?<w:pStyle[^>]*w:val="Heading\s*(\d)"[^>]*/>(?:(?!</w:p>).)*?</w:p>#s',
            function ($m) {
                $inner = $m[0];
                $text = '';
                if (preg_match_all('#<w:t[^>]*>(.*?)</w:t>#s', $inner, $tm)) {
                    $text = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }

                $text = trim($text);

                return $text === ''
                    ? ''
                    : "\n" . str_repeat('#', (int) $m[1]) . ' ' . $text . "\n";
            },
            $xml
        ) ?? $xml;

        // Paragraphs are the unit of structure; without them the whole document
        // collapses into one line. This has to run AFTER the heading pass,
        // because the heading pattern needs the closing </w:p> to delimit a
        // paragraph and the split consumes it.
        $xml = preg_replace('#</w:p>#', "\n", $xml) ?? $xml;

        $text = $this->xmlToText($xml);

        return $this->structure($text, ['source' => 'docx', 'file' => $name]);
    }

    private function fromPptx(string $path, string $name): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException("{$name} is not a readable .pptx archive.");
        }

        $slideNumbers = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', (string) $entry, $m)) {
                $slideNumbers[] = (int) $m[1];
            }
        }

        sort($slideNumbers);

        $parts = [];
        $meta = ['source' => 'pptx', 'file' => $name, 'slides' => (string) count($slideNumbers)];

        foreach ($slideNumbers as $number) {
            $xml = $zip->getFromName("ppt/slides/slide{$number}.xml");

            if ($xml === false) {
                continue;
            }

            // One slide is one section of the resulting chapter.
            $body = $this->xmlToText($xml);
            $parts[] = "\n## Slide {$number}\n" . $body;
        }

        $zip->close();

        if ($parts === []) {
            throw new RuntimeException("No slides were found in {$name}.");
        }

        return $this->structure(implode("\n", $parts), $meta);
    }

    private function fromPlainText(string $path, string $name): array
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("{$name} could not be read.");
        }

        return $this->structure($contents, ['source' => 'text', 'file' => $name]);
    }

    private function partFromZip(string $path, string $entry): ?string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return null;
        }

        $contents = $zip->getFromName($entry);
        $zip->close();

        return $contents === false ? null : $contents;
    }

    /**
     * Strips tags and decodes entities, without letting a stray tag in the
     * source swallow the rest of the document.
     */
    private function xmlToText(string $xml): string
    {
        // Word and PowerPoint split a sentence across many runs, and each run
        // boundary is a real break in the text as a reader would see it.
        // Dropping the tags without inserting anything joins them: "Learning
        // Objectives" + "Explain cost behaviour" became
        // "Learning ObjectivesExplain cost behaviour".
        $xml = preg_replace('#</w:t>#', ' ', $xml) ?? $xml;
        $xml = preg_replace('#</a:t>#', "\n", $xml) ?? $xml;
        $xml = preg_replace('#</w:p>#', "\n", $xml) ?? $xml;
        $xml = preg_replace('#<a:br[^>]*/>#', "\n", $xml) ?? $xml;

        $xml = preg_replace('#<(w|wp|a|p):?[^>]*>#', '', $xml) ?? $xml;
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array{text: string, headings: array<int, string>, meta: array<string, string>}
     */
    private function structure(string $text, array $meta): array
    {
        $lines = preg_split('/\r?\n/', $text) ?: [];
        $headings = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line !== '' && preg_match('/^#{1,6}\s+(.{3,120})$/', $line, $m)) {
                $headings[] = trim($m[1]);
            }
        }

        return [
            'text' => trim($text),
            'headings' => array_values(array_unique($headings)),
            'meta' => $meta,
        ];
    }
}
