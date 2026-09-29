<?php

namespace Tests\Feature;

use App\Services\Curriculum\MaterialExtractor;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Extracted from real files of each type, built in the test rather than
 * checked in as fixtures, so a format change cannot quietly pass.
 */
class MaterialExtractorTest extends TestCase
{
    private array $temp = [];

    protected function tearDown(): void
    {
        foreach ($this->temp as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function test_it_extracts_text_and_headings_from_a_docx(): void
    {
        $path = tempnam(sys_get_temp_dir(), 't').'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('word/document.xml',
            '<?xml version="1.0"?><w:document xmlns:w="x"><w:body>'
            .'<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Introduction to Costing</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>Costing allocates overheads.</w:t></w:r></w:p>'
            .'</w:body></w:document>');
        $zip->close();
        $this->temp[] = $path;

        $result = (new MaterialExtractor)->extract($path, 'lecture.docx');

        $this->assertStringContainsString('Introduction to Costing', $result['text']);
        // The heading text must survive, not just the marker.
        $this->assertContains('Introduction to Costing', $result['headings']);
        $this->assertStringContainsString('Costing allocates overheads.', $result['text']);
    }

    public function test_it_keeps_slide_boundaries_in_a_pptx(): void
    {
        $path = tempnam(sys_get_temp_dir(), 't').'.pptx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('ppt/slides/slide1.xml',
            '<?xml version="1.0"?><p:sld xmlns:p="p" xmlns:a="a">'
            .'<a:t>Learning Objectives</a:t><a:t>Explain cost behaviour</a:t></p:sld>');
        $zip->addFromString('ppt/slides/slide2.xml',
            '<?xml version="1.0"?><p:sld xmlns:p="p" xmlns:a="a">'
            .'<a:t>Cost Behaviour</a:t></p:sld>');
        $zip->close();
        $this->temp[] = $path;

        $result = (new MaterialExtractor)->extract($path, 'lecture.pptx');

        $this->assertStringContainsString('Slide 1', $result['text']);
        $this->assertStringContainsString('Slide 2', $result['text']);
        // Runs must not run together, or a slide reads as one gibberish line.
        $this->assertStringNotContainsString('ObjectivesExplain', $result['text']);
    }

    public function test_it_reads_plain_text(): void
    {
        $path = tempnam(sys_get_temp_dir(), 't').'.txt';
        file_put_contents($path, "Cost Accounting Basics\n\nOverhead allocation is central.\n");
        $this->temp[] = $path;

        $result = (new MaterialExtractor)->extract($path, 'notes.txt');

        $this->assertStringContainsString('Overhead allocation is central.', $result['text']);
    }

    public function test_it_rejects_an_unsupported_type(): void
    {
        $path = tempnam(sys_get_temp_dir(), 't').'.exe';
        file_put_contents($path, 'MZ');
        $this->temp[] = $path;

        $this->expectException(RuntimeException::class);

        (new MaterialExtractor)->extract($path, 'malware.exe');
    }
}
