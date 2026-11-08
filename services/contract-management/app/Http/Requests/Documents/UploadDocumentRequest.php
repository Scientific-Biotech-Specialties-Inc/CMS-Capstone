<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Payloads\Documents\UploadDocumentPayload;
use App\Rules\ValidContractDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class UploadDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Handled by auth.internal and role middleware on routes
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240', // 10 MB limit
                new ValidContractDocument(),
            ],
            'contract_id' => [
                'nullable',
                'integer',
                'exists:contracts,contract_id',
            ],
        ];
    }

    /**
     * Transform validated data into a typed DTO payload.
     */
    public function toPayload(): UploadDocumentPayload
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');
        
        $contractId = $this->input('contract_id') !== null ? (int) $this->input('contract_id') : null;

        return new UploadDocumentPayload($file, $contractId);
    }
}
