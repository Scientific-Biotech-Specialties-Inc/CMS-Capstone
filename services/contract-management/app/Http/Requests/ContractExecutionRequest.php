<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ValidContractDocument;
use Illuminate\Foundation\Http\FormRequest;

final class ContractExecutionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:submit,confirm,return,pause,resume,revise'],
            'operation_id' => ['required', 'uuid'],
            'stage' => ['required', 'in:SBSI Review,Client Review,For Signing,Signed'],
            'draft_document_id' => ['required', 'string', 'max:64'],
            'submission_id' => ['required_if:action,confirm,return', 'integer', 'min:1'],
            'reason' => ['required_if:action,return,pause,resume', 'nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'extensions:pdf', 'max:10240', new ValidContractDocument()],
            'method' => ['nullable', 'in:scanned_upload,e_signature'],
            'signer_name' => ['required_if:method,e_signature', 'nullable', 'string', 'max:160'],
            'signature_page' => ['required_if:method,e_signature', 'nullable', 'integer', 'min:1'],
            'signature_x' => ['required_if:method,e_signature', 'nullable', 'numeric', 'between:0,0.75'],
            'signature_y' => ['required_if:method,e_signature', 'nullable', 'numeric', 'between:0,1'],
        ];
    }
}
