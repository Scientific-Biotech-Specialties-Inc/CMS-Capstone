<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class InternalAuditController extends Controller
{
    /**
     * Receive an audit event from internal auth service and write it to CMS audit log.
     */
    public function receive(Request $request)
    {
        $secret = (string) ($request->header('X-Internal-Secret') ?? '');
        $expectedSecret = (string) (config('app.internal_service_secret') ?: env('INTERNAL_SERVICE_SECRET'));

        // Constant-time comparison: prevents timing attacks on the shared secret.
        if ($expectedSecret === '' || !hash_equals($expectedSecret, $secret)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'action' => 'required|string',
            'entity_type' => 'required|string',
            'entity_id' => 'nullable',
            'user_id' => 'nullable|integer',
            'old_data' => 'nullable|array',
            'new_data' => 'nullable|array',
            'user_name' => 'nullable|string',
            'user_email' => 'nullable|string',
            'user_role' => 'nullable|string',
            'user_department' => 'nullable|string',
        ]);

        $isBusinessEntity = in_array($request->input('entity_type'), ['Contract', 'Document', 'Supplier', 'BusinessPartner']);

        if ($isBusinessEntity && !in_array($request->input('user_department'), ['Sales & Marketing', 'Sales'])) {
            return response()->json(['ok' => true, 'message' => 'Skipped: Not in Sales & Marketing or Sales department']);
        }

        $log = AuditLog::create([
            'action' => $request->input('action'),
            'entity_type' => $request->input('entity_type'),
            'entity_id' => $request->input('entity_id') ?? 0,
            'user_id' => $request->input('user_id'),
            'user_name' => $request->input('user_name'),
            'user_email' => $request->input('user_email'),
            'user_role' => $request->input('user_role'),
            'user_department' => $request->input('user_department'),
            'old_data' => $request->input('old_data'),
            'new_data' => $request->input('new_data'),
            'performed_at' => now(),
        ]);

        return response()->json(['ok' => true, 'audit_id' => $log->audit_id]);
    }
}
