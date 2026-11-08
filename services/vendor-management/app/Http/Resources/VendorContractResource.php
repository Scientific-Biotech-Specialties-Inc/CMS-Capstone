<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

final class VendorContractResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $contract = $this->relationLoaded('contract') ? $this->contract : null;
        $engagementStatus = 'active';

        if ($contract) {
            if ($contract->end_date) {
                $endDate = Carbon::parse($contract->end_date);
                $days = (int) Carbon::today()->diffInDays($endDate, false);
                $engagementStatus = match (true) {
                    $days < 0 => 'expired',
                    $days <= 30 => 'expiring',
                    default => 'active',
                };
            }
        }

        return [
            'association_id' => $this->id,
            'contract_id' => $this->contract_id,
            'vendor_type' => $this->vendor_type,
            'vendor_id' => $this->vendor_id,
            'engagement_status' => $engagementStatus,
            'contract' => $contract ? [
                'contract_code' => $contract->contract_code ?? null,
                'description' => $contract->description,
                'bp_name' => $contract->bp_name,
                'start_date' => $contract->start_date?->toDateString(),
                'end_date' => $contract->end_date?->toDateString(),
            ] : null,
            'attached_by' => $this->attached_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
