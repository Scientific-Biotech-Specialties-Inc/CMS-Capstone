<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Jobs\SendHighRiskApprovalEscalationEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * POST /internal/high-risk-approval-escalation — called by contract-management's
 * scheduled SLA-check command. Protected by X-Internal-Secret (internal.secret
 * middleware), no user token needed. Fans out an email-only escalation (no
 * in-app notification) to every user in the given target role(s), per the
 * confirmed implementation plan.
 */
class HighRiskEscalationController extends Controller
{
    public function push(Request $request)
    {
        if (empty($request->all())) {
            $raw = file_get_contents('php://input');
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $request->merge($decoded);
                }
            }
        }

        $validator = Validator::make($request->all(), [
            'target_roles'         => 'required|string',
            'contract_approval_id' => 'required|integer',
            'contract_id'          => 'required|integer',
            'bp_name'              => 'required|string',
            'risk_level'           => 'required|string',
            'hours_overdue'        => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        SendHighRiskApprovalEscalationEmails::dispatch(
            (string) $data['target_roles'],
            (int) $data['contract_approval_id'],
            (int) $data['contract_id'],
            (string) $data['bp_name'],
            (string) $data['risk_level'],
            (int) $data['hours_overdue'],
        );

        return response()->json(['message' => 'Escalation email(s) queued.'], 202);
    }
}
