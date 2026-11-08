<?php

declare(strict_types=1);

namespace App\Http\Requests\EmailPreferences;

use App\Payloads\EmailPreferences\UpdateEmailPreferencePayload;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateEmailPreferenceRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email_notifications_enabled' => ['required', 'boolean'],
            'contract_expiry_alerts' => ['required', 'boolean'],
            'system_alerts_enabled' => ['required', 'boolean'],
            'sms_notifications_enabled' => ['required', 'boolean'],
            'login_alerts_enabled' => ['required', 'boolean'],
            'ai_risk_assessment_enabled' => ['sometimes', 'boolean'],
            'ai_vendor_suggestions_enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Map the validated inputs to a Payload DTO.
     */
    public function toPayload(): UpdateEmailPreferencePayload
    {
        return new UpdateEmailPreferencePayload(
            $this->boolean('email_notifications_enabled'),
            $this->boolean('contract_expiry_alerts'),
            $this->boolean('system_alerts_enabled'),
            $this->boolean('sms_notifications_enabled'),
            $this->boolean('login_alerts_enabled'),
            $this->has('ai_risk_assessment_enabled') ? $this->boolean('ai_risk_assessment_enabled') : true,
            $this->has('ai_vendor_suggestions_enabled') ? $this->boolean('ai_vendor_suggestions_enabled') : true,
        );
    }
}
