<?php

namespace App\Services\RiskAssessment;

use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

/**
 * Extracts plain text from a contract document's raw bytes (PDF or DOCX,
 * matching the accepted formats in Create Contract's document upload). No
 * such extraction pipeline existed anywhere in the codebase prior to this
 * feature — confirmed by checking contract-management's Document
 * model/scan job, which only implements ClamAV malware scanning.
 *
 * Scanned/image-only PDFs with no embedded text layer cannot be extracted
 * by this class — a real OCR step would be required, which is explicitly
 * out of scope here (this is a known limitation, not a silent failure: see
 * IMPLEMENTATION_PLAN_AI_RISK_VENDOR_ANALYTICS.md, Feature 1).
 */
class DocumentTextExtractor
{
    /**
     * @return string|null Plain text, or null if extraction failed / no text found.
     */
    public function extract(string $fileBytes, string $fileType): ?string
    {
        $fileType = strtolower($fileType);

        return match ($fileType) {
            'pdf'  => $this->extractPdf($fileBytes),
            'docx' => $this->extractDocx($fileBytes),
            default => null,
        };
    }

    protected function extractPdf(string $fileBytes): ?string
    {
        try {
            $parser = new PdfParser();
            $document = $parser->parseContent($fileBytes);
            $text = trim($document->getText());
            return $text !== '' ? $text : null;
        } catch (\Exception $e) {
            Log::warning('PDF text extraction failed.', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * DOCX files are a zip archive containing word/document.xml — extracting
     * that XML's text nodes avoids pulling in phpoffice/phpword, whose
     * current stable release depends on a phpoffice/math version with a
     * known XXE advisory (PKSA-jw72-bn8m-h7rc) with no compatible fixed
     * pairing available yet.
     */
    protected function extractDocx(string $fileBytes): ?string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'docx_');
        if ($tmpPath === false) {
            return null;
        }

        try {
            file_put_contents($tmpPath, $fileBytes);

            $zip = new ZipArchive();
            if ($zip->open($tmpPath) !== true) {
                return null;
            }

            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($xml === false) {
                return null;
            }

            // PHP 8's DOMDocument disables external entity loading by default,
            // so LIBXML_NONET alone (no network access for any external
            // references) is sufficient hardening against XXE here.
            $previous = libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $loaded = $dom->loadXML($xml, LIBXML_NONET);
            libxml_use_internal_errors($previous);

            if (!$loaded) {
                return null;
            }

            $textNodes = $dom->getElementsByTagName('t');
            $parts = [];
            foreach ($textNodes as $node) {
                $parts[] = $node->textContent;
            }

            $text = trim(implode(' ', $parts));
            return $text !== '' ? $text : null;
        } catch (\Exception $e) {
            Log::warning('DOCX text extraction failed.', ['message' => $e->getMessage()]);
            return null;
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Chunk text into overlapping windows for embedding/retrieval. Uses a
     * simple word-count-based window (not a real tokenizer) since Gemini's
     * exact tokenization isn't exposed via a PHP library — a word-based
     * approximation is a reasonable, dependency-free stand-in.
     *
     * @return list<string>
     */
    public function chunk(string $text, int $wordsPerChunk = 350, int $overlapWords = 50): array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return [];
        }

        $words = preg_split('/\s+/', $trimmed) ?: [];
        if (empty($words)) {
            return [];
        }

        $chunks = [];
        $step = max(1, $wordsPerChunk - $overlapWords);

        for ($start = 0; $start < count($words); $start += $step) {
            $slice = array_slice($words, $start, $wordsPerChunk);
            if (empty($slice)) {
                break;
            }
            $chunks[] = implode(' ', $slice);
            if ($start + $wordsPerChunk >= count($words)) {
                break;
            }
        }

        return $chunks;
    }
}
