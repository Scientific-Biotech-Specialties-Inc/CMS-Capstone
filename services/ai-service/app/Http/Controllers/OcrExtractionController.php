<?php

namespace App\Http\Controllers;

use App\Models\OcrExtraction;
use App\Rules\ValidContractDocument;
use App\Services\ContractDocumentClient;
use App\Services\Gemini\GeminiClient;
use App\Services\Ocr\OcrTextExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OcrExtractionController extends Controller
{
    public function __construct(
        protected OcrTextExtractor $ocrExtractor,
        protected GeminiClient $geminiClient,
        protected ContractDocumentClient $documentClient
    ) {
    }

    /**
     * POST /api/ocr/extract
     * Extracts text using Tesseract OCR and structures it using Gemini.
     */
    public function extract(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => ['nullable', 'file', 'max:10240', new ValidContractDocument()],
            'document_id' => 'nullable|string', // soft ref ID
            'candidate_partners' => 'nullable|array',
            'candidate_partners.*' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');
        $documentId = $request->input('document_id');

        if (!$file && !$documentId) {
            return response()->json([
                'message' => 'Either an uploaded file (file) or a pre-uploaded document identifier (document_id) is required.'
            ], 422);
        }

        $fileBytes = null;
        $fileName = '';
        $fileType = '';

        try {
            if ($file) {
                $fileBytes = file_get_contents($file->getRealPath());
                $fileName = $file->getClientOriginalName();
                $fileType = $file->getClientOriginalExtension();
            } else {
                Log::info("Fetching document bytes from contract-management service.", ['document_id' => $documentId]);
                
                $metadata = $this->documentClient->getDocumentMetadata($documentId);
                if (!$metadata) {
                    return response()->json([
                        'message' => 'Could not retrieve document metadata from contract service.'
                    ], 404);
                }

                // Row-level authorization: Sales/Employee may only extract from documents
                // belonging to contracts they created, or pre-link documents they uploaded.
                $role = $request->get('auth_role');
                if (in_array($role, ['Sales', 'Employee'], true)) {
                    $ownerId = $metadata['contract_created_by'] ?? $metadata['uploaded_by'] ?? null;
                    if ($ownerId === null || (int) $ownerId !== (int) $request->get('auth_id')) {
                        return response()->json(['message' => 'Forbidden.'], 403);
                    }
                }

                $fileBytes = $this->documentClient->fetchFileBytes($documentId);
                if (!$fileBytes) {
                    return response()->json([
                        'message' => 'Could not retrieve document file bytes from contract service.'
                    ], 404);
                }

                $fileName = $metadata['file_name'];
                $fileType = $metadata['file_type'] ?? pathinfo($fileName, PATHINFO_EXTENSION);
            }

            // Create temporary extraction record
            $ocrRecord = OcrExtraction::create([
                'document_id' => $documentId ? (int)$documentId : null,
                'status' => 'pending',
            ]);

            // Perform text extraction (native parser with OCR fallback)
            $text = $this->ocrExtractor->extract($fileBytes, $fileName, $fileType);

            if (!$text || strlen(trim($text)) === 0) {
                $ocrRecord->update(['status' => 'failed']);
                return response()->json([
                    'message' => 'No readable text could be extracted from the document. Please verify the file is not corrupted or empty.'
                ], 422);
            }

            // Prepare prompt and schema for Gemini
            $candidatePartners = $request->input('candidate_partners') ?? [];
            
            $systemInstruction = "You are an AI assistant specialized in analyzing contract text. Extract metadata fields from the provided text and format them into the requested JSON schema.
For fields that are not mentioned or found in the text, return an empty string (\"\").
For dates (start_date, end_date), return them in YYYY-MM-DD format if possible.
For the category, match one of the allowed values: 'Service Agreement', 'Partnership Agreement', 'Supply Contract', 'Equipment Lease', 'Equipment Maintenance'. If it does not match exactly, select the closest category.
For the region, match one of: 'Luzon', 'Visayas', 'Mindanao'. If not mentioned, return an empty string (\"\").";

            $userPrompt = "Here is the contract text:\n\n" . $text;
            
            if (!empty($candidatePartners)) {
                $userPrompt .= "\n\nAvailable Business Partners in our system:\n" . json_encode($candidatePartners) . "\n\n";
                $userPrompt .= "CRITICAL: If the extracted business partner name matches or is closely related to one of these system partners (case-insensitively or with minor spacing differences), please map it EXACTLY to that partner's name. Otherwise, return the business partner name extracted from the text.";
            }

            $schema = [
                'type' => 'OBJECT',
                'properties' => [
                    'business_partner' => [
                        'type' => 'STRING',
                        'description' => 'The name of the business partner or company.'
                    ],
                    'category' => [
                        'type' => 'STRING',
                        'enum' => ['Service Agreement', 'Partnership Agreement', 'Supply Contract', 'Equipment Lease', 'Equipment Maintenance'],
                        'description' => 'The contract category matching the allowed options.'
                    ],
                    'item_code' => [
                        'type' => 'STRING',
                        'description' => 'The item code of the contract if mentioned (e.g. ITM-XXXX). Return "" if not found.'
                    ],
                    'description' => [
                        'type' => 'STRING',
                        'description' => 'A brief description of the contract services or items. Return "" if not found.'
                    ],
                    'serial_number' => [
                        'type' => 'STRING',
                        'description' => 'The serial number mentioned in the contract. Return "" if not found.'
                    ],
                    'sbu_number' => [
                        'type' => 'STRING',
                        'description' => 'The SBU (Strategic Business Unit) number (e.g. SBU-XXX). Return "" if not found.'
                    ],
                    'region' => [
                        'type' => 'STRING',
                        'enum' => ['Luzon', 'Visayas', 'Mindanao', 'Unknown'],
                        'description' => 'The region mentioned in the contract. If not mentioned, return "Unknown".'
                    ],
                    'start_date' => [
                        'type' => 'STRING',
                        'description' => 'The start date of the contract in YYYY-MM-DD format. Return "" if not found.'
                    ],
                    'end_date' => [
                        'type' => 'STRING',
                        'description' => 'The end date of the contract in YYYY-MM-DD format. Return "" if not found.'
                    ],
                    'confidence_score' => [
                        'type' => 'NUMBER',
                        'description' => 'A confidence score from 0.0 to 100.0 representing how confident you are in the extracted values overall.'
                    ]
                ],
                'required' => [
                    'business_partner', 'category', 'item_code', 'description', 
                    'serial_number', 'sbu_number', 'region', 'start_date', 'end_date', 
                    'confidence_score'
                ]
            ];

            // Request Gemini structured response
            $structuredResult = $this->geminiClient->generateJson($systemInstruction, $userPrompt, $schema);

            if (!$structuredResult) {
                $ocrRecord->update(['status' => 'failed']);
                return response()->json([
                    'message' => 'Gemini AI failed to extract structured fields from the text.'
                ], 500);
            }

            // Save details to the database
            $ocrRecord->update([
                'status' => 'completed',
                'extracted_fields' => $structuredResult,
                'confidence_score' => $structuredResult['confidence_score'] ?? 0.0,
                'extracted_at' => now(),
            ]);

            return response()->json([
                'message' => 'OCR and metadata extraction completed successfully.',
                'data' => $structuredResult,
            ]);

        } catch (\Exception $e) {
            Log::error('OCR extraction endpoint failed.', ['message' => $e->getMessage()]);
            return response()->json([
                'message' => 'An internal error occurred during the OCR service process.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
