<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function createDraftPdf(int $ownerId = 42): string
    {
        return (string) \App\Models\Document::create([
            'file_name' => 'draft.pdf', 'file_type' => 'pdf',
            'file_path' => 'contracts/documents/draft.pdf',
            'uploaded_by' => $ownerId, 'uploaded_at' => now(), 'scan_status' => 'clean',
        ])->getKey();
    }
}
