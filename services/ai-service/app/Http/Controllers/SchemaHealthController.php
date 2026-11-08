<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class SchemaHealthController extends Controller
{
    /**
     * Report schema metadata only; never read or return stored AI data.
     */
    public function check(): JsonResponse
    {
        $tables = [
            'risk_assessment_results' => [
                'id',
                'document_id',
                'contract_id',
                'risk_score',
                'risk_level',
                'findings',
                'status',
                'scanned_at',
            ],
            'vendor_suggestions' => [
                'id',
                'vendor_type',
                'vendor_id',
                'contract_id',
                'suggestion_score',
                'suggestion_reason',
                'status',
                'suggested_at',
            ],
            'ocr_extractions' => [
                'id',
                'document_id',
                'contract_id',
                'extracted_fields',
                'confidence_score',
                'status',
                'extracted_at',
            ],
            'embeddings' => [
                'id',
                'entity_type',
                'entity_id',
                'embedding',
                'model_name',
            ],
            'audit_logs' => [
                'audit_id',
                'action',
                'entity_type',
                'entity_id',
                'user_id',
                'old_data',
                'new_data',
                'performed_at',
            ],
        ];

        $schema = [];

        foreach ($tables as $table => $columns) {
            $tableExists = Schema::hasTable($table);

            $schema[$table] = [
                'table_exists' => $tableExists,
                'columns_exist' => $tableExists && Schema::hasColumns($table, $columns),
            ];
        }

        return response()->json([
            'status' => 'ok',
            'schema' => $schema,
        ]);
    }
}
