<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Document $resource
 */
final class DocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'document_id' => (string) $this->resource->getKey(),
            'contract_id' => $this->resource->contract_id,
            'file_name' => $this->resource->file_name,
            'file_type' => $this->resource->file_type,
            'file_size' => $this->resource->file_size,
            'document_url' => $this->resource->document_url,
            'uploaded_by' => $this->resource->uploaded_by,
            'uploaded_at' => $this->resource->uploaded_at?->toISOString(),
            'doc_category' => $this->resource->doc_category,
            'signature_method' => $this->resource->signature_method,
            'replaces_document_id' => $this->resource->replaces_document_id,
        ];
    }
}
