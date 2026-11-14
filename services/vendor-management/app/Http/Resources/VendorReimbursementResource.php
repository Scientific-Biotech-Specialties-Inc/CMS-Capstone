<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class VendorReimbursementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'serms_reimbursement_id' => $this->serms_reimbursement_id,
            'reference_no'           => $this->reference_no,
            'tin_number'             => $this->tin_number,
            'vendor_name'            => $this->vendor_name,
            'category'               => $this->category,
            'description'            => $this->description,
            'amount'                 => (float) $this->amount,
            'status'                 => $this->status,
            'employee_name'          => $this->employee_name,
            'contract_id'            => $this->contract_id,
            'expense_date'           => $this->expense_date?->toDateString(),
            'synced_at'              => $this->synced_at?->toISOString(),
        ];
    }
}
