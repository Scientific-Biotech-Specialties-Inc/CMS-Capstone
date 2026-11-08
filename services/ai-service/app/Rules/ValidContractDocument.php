<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class ValidContractDocument implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile) {
            $fail('The :attribute must be a valid uploaded file.');
            return;
        }

        // 1. Verify Extension (case-insensitive)
        $extension = strtolower($value->getClientOriginalExtension());
        if (!in_array($extension, ['pdf', 'docx'])) {
            $fail('The file extension must be pdf or docx.');
            return;
        }

        // 2. Verify MIME Type
        $mimeType = $value->getMimeType();
        $allowedMimes = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        if (!in_array($mimeType, $allowedMimes)) {
            $fail('The file MIME type must be application/pdf or application/vnd.openxmlformats-officedocument.wordprocessingml.document.');
            return;
        }

        // 3. Verify Magic Bytes
        $path = $value->getRealPath();
        if (!$path || !is_readable($path)) {
            $fail('The file contents could not be read.');
            return;
        }

        $handle = fopen($path, 'rb');
        if (!$handle) {
            $fail('The file contents could not be read.');
            return;
        }
        $header = fread($handle, 4);
        fclose($handle);

        $hex = bin2hex($header);

        if ($extension === 'pdf') {
            // PDF Magic Bytes: %PDF (25 50 44 46)
            if ($hex !== '25504446') {
                $fail('The file has invalid magic bytes for a PDF document.');
            }
        } elseif ($extension === 'docx') {
            // DOCX is a zip package. Magic Bytes: PK\x03\x04 (50 4b 03 04)
            if ($hex !== '504b0304') {
                $fail('The file has invalid magic bytes for a DOCX document.');
            }
        }
    }
}
