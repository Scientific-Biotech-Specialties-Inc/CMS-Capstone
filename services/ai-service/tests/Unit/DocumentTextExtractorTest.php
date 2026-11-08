<?php

namespace Tests\Unit;

use App\Services\RiskAssessment\DocumentTextExtractor;
use Dompdf\Dompdf;
use Tests\TestCase;
use ZipArchive;

final class DocumentTextExtractorTest extends TestCase
{
    private DocumentTextExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new DocumentTextExtractor();
    }

    public function test_extracts_text_from_a_real_pdf(): void
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml('<p>SBSI-CONTRACT-SENTINEL-TEXT payment due within 30 days</p>');
        $dompdf->render();
        $pdfBytes = $dompdf->output();

        $text = $this->extractor->extract($pdfBytes, 'pdf');

        $this->assertNotNull($text);
        $this->assertStringContainsString('SBSI-CONTRACT-SENTINEL-TEXT', $text);
    }

    public function test_extracts_text_from_a_real_docx(): void
    {
        $docxBytes = $this->buildMinimalDocx('DOCX-SENTINEL-TEXT termination clause');

        $text = $this->extractor->extract($docxBytes, 'docx');

        $this->assertNotNull($text);
        $this->assertStringContainsString('DOCX-SENTINEL-TEXT', $text);
    }

    public function test_extract_returns_null_for_unsupported_file_type(): void
    {
        $this->assertNull($this->extractor->extract('irrelevant bytes', 'xlsx'));
    }

    public function test_extract_returns_null_for_corrupt_pdf_bytes(): void
    {
        $this->assertNull($this->extractor->extract('not a real pdf', 'pdf'));
    }

    public function test_chunk_splits_long_text_into_overlapping_windows(): void
    {
        $words = array_map(fn ($i) => "word{$i}", range(1, 1000));
        $text = implode(' ', $words);

        $chunks = $this->extractor->chunk($text, wordsPerChunk: 350, overlapWords: 50);

        $this->assertGreaterThan(1, count($chunks));
        // Overlap: the last 50 words of chunk[0] should reappear at the start of chunk[1].
        $chunk0Words = explode(' ', $chunks[0]);
        $chunk1Words = explode(' ', $chunks[1]);
        $this->assertSame(array_slice($chunk0Words, -50), array_slice($chunk1Words, 0, 50));
    }

    public function test_chunk_returns_single_chunk_for_short_text(): void
    {
        $chunks = $this->extractor->chunk('short contract text here');
        $this->assertCount(1, $chunks);
        $this->assertSame('short contract text here', $chunks[0]);
    }

    public function test_chunk_returns_empty_array_for_blank_text(): void
    {
        $this->assertSame([], $this->extractor->chunk('   '));
    }

    private function buildMinimalDocx(string $bodyText): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'test_docx_');
        $zip = new ZipArchive();
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString('word/document.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>{$bodyText}</w:t></w:r></w:p>
  </w:body>
</w:document>
XML);
        $zip->close();

        $bytes = file_get_contents($tmpPath);
        unlink($tmpPath);

        return $bytes;
    }
}
