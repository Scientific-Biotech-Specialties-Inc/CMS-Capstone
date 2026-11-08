<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Payloads\AttachContractPayload;
use Illuminate\Foundation\Http\FormRequest;

final class AttachContractRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'contract_id' => [
                'required',
                'integer',
                'exists:contracts,contract_id',
            ],
        ];
    }

    /**
     * Transform the request parameters into a typed payload.
     */
    public function toPayload(string $vendorType, int $vendorId, int $attachedBy): AttachContractPayload
    {
        return new AttachContractPayload(
            vendorType: $vendorType,
            vendorId: $vendorId,
            contractId: (int) $this->input('contract_id'),
            attachedBy: $attachedBy
        );
    }
}
