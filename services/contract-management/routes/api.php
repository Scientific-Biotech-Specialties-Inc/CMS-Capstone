<?php

use App\Http\Controllers\ContractController;
use App\Http\Controllers\LookupController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.internal'])->group(function () {

    Route::get('/lookups/{type}', [LookupController::class, 'show']);
    
    Route::get('/dashboard',           [ContractController::class, 'dashboardSummary']);
    // Contracts REST endpoints
    Route::get('/contracts', [\App\Http\Controllers\ContractController::class, 'index'])
        ->middleware('permission:cms.contracts.view');
    Route::post('/contracts', [\App\Http\Controllers\ContractController::class, 'store'])
        ->middleware('permission:cms.contracts.create');
    Route::put('/contracts/{id}', [\App\Http\Controllers\ContractController::class, 'update'])
        ->middleware('permission:cms.contracts.edit');
    Route::delete('/contracts/{id}', [\App\Http\Controllers\ContractController::class, 'destroy'])
        ->middleware('permission:cms.contracts.delete');
    Route::get('/contract-requests',  [ContractController::class, 'indexRequests']);

    Route::get('/contracts/{id}',    [ContractController::class, 'show'])
        ->middleware('permission:cms.contracts.view');
    Route::patch('/contracts/{id}/status', [ContractController::class, 'updateStatus'])
        ->middleware('permission:cms.contracts.approve');
    Route::post('/contracts/{id}/notify-manager', [ContractController::class, 'notifyManager'])
        ->middleware('permission:cms.contracts.view');
    // Phase 7 (decision #13): canonical generalized nudge — notifies the
    // role(s) currently responsible. /notify-manager above stays as an
    // alias to the same behavior so cached callers don't break.
    Route::post('/contracts/{id}/notify-step', [ContractController::class, 'notifyResponsibleRole'])
        ->middleware('permission:cms.contracts.view');

    // US-023: High-Risk Contract Approval Gate
    Route::get('/contracts/{id}/high-risk-approval', [\App\Http\Controllers\ContractApprovalController::class, 'show'])
        ->middleware('permission:cms.contracts.view');
    Route::post('/contracts/{id}/high-risk-approval', [\App\Http\Controllers\ContractApprovalController::class, 'store'])
        ->middleware('permission:cms.contracts.approve');

    Route::post('/admin/users', [\App\Http\Controllers\AdminUserProxyController::class, 'store'])
        ->middleware('permission:cms.users.create');

    // Aggregated Audit Logs Route
    Route::get('/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])
        ->middleware('permission:cms.audit.view|cms.users.view');

    // Document Upload Route
    Route::post('/documents/upload', \App\Http\Controllers\Api\V1\Documents\UploadController::class)
        ->middleware(['permission:cms.contracts.create', 'throttle:10,1']);

    // Document Retrieve Route
    Route::get('/documents/{id}/file', [\App\Http\Controllers\Api\V1\Documents\DownloadController::class, 'show'])
        ->middleware('permission:cms.contracts.view');
    Route::get('/documents/{id}/presigned-url', [\App\Http\Controllers\Api\V1\Documents\DownloadController::class, 'presignedUrl'])
        ->middleware('permission:cms.contracts.view');

    // Contract Amendments Routes
    Route::apiResource('contract-amendments', \App\Http\Controllers\ContractAmendmentController::class);
    Route::patch('contract-amendments/{id}/status', [\App\Http\Controllers\ContractAmendmentController::class, 'updateStatus'])
        ->middleware('permission:cms.contracts.approve');
    Route::get('contracts/{id}/versions', [\App\Http\Controllers\ContractAmendmentController::class, 'versionHistory'])
        ->middleware('permission:cms.contracts.view');

    // Dynamic Approval Workflow Engine — Workflow Builder (Phase 4).
    // Admin-only, matching the existing CMS Roles & Permissions admin page.
    Route::middleware('role:Admin')->prefix('workflows')->group(function () {
        Route::get('/contract-types',    [\App\Http\Controllers\WorkflowController::class, 'contractTypes']);
        Route::get('/assignable-roles',  [\App\Http\Controllers\WorkflowController::class, 'assignableRoles']);
        Route::get('/{id}',              [\App\Http\Controllers\WorkflowController::class, 'show']);
        Route::post('/',                 [\App\Http\Controllers\WorkflowController::class, 'store']);
        Route::put('/{id}',              [\App\Http\Controllers\WorkflowController::class, 'update']);
        Route::post('/{id}/duplicate',   [\App\Http\Controllers\WorkflowController::class, 'duplicate']);
        Route::delete('/{id}',           [\App\Http\Controllers\WorkflowController::class, 'destroy']);
    });

    // Dynamic Approval Workflow Engine — runtime (Phase 5). Any
    // authenticated user may call these; eligibility to act on a given
    // task is checked at the service layer (must currently hold the
    // task's role), not via a blanket permission here.
    Route::get('/contracts/{id}/approval-progress', [\App\Http\Controllers\ApprovalEngineController::class, 'progress'])
        ->middleware('permission:cms.contracts.view');
    Route::post('/contracts/{id}/approval-tasks/{taskId}/decision', [\App\Http\Controllers\ApprovalEngineController::class, 'decide'])
        ->middleware('permission:cms.contracts.approve');

});

// Internal webhook for login/logout events from auth-service
Route::post('/internal/audit-event', [\App\Http\Controllers\InternalAuditController::class, 'receive']);

// Internal service-to-service reads for ai-service's RAG risk-assessment pipeline (Feature 1).
Route::middleware(['internal.secret'])->group(function () {
    Route::get('/internal/contracts/{contractId}/documents', [\App\Http\Controllers\Api\V1\Documents\InternalDocumentController::class, 'listForContract']);
    Route::get('/internal/documents/{id}', [\App\Http\Controllers\Api\V1\Documents\InternalDocumentController::class, 'show']);
    Route::get('/internal/documents/{id}/file', [\App\Http\Controllers\Api\V1\Documents\InternalDocumentController::class, 'file']);
    Route::get('/internal/contracts/{id}/owner', function (string $id) {
        $contract = \App\Models\Contract::where('contract_code', $id)
            ->orWhere('contract_id', is_numeric($id) ? (int) $id : 0)
            ->first();
        if (!$contract) {
            return response()->json(['message' => 'Contract not found.'], 404);
        }
        return response()->json([
            'contract_id'   => $contract->contract_id,
            'contract_code' => $contract->contract_code,
            'created_by'    => $contract->created_by,
        ]);
    });

    Route::post('/internal/contracts/resolve-batch', function (\Illuminate\Http\Request $request) {
        $rawIds = (array) ($request->input('ids') ?? []);
        $numericIds = array_map('intval', array_filter($rawIds, 'is_numeric'));
        $stringCodes = array_filter($rawIds, fn ($v) => !is_numeric($v));

        $query = \App\Models\Contract::query();
        $query->where(function ($q) use ($numericIds, $stringCodes) {
            if (!empty($numericIds)) {
                $q->orWhereIn('contract_id', $numericIds);
            }
            if (!empty($stringCodes)) {
                $q->orWhereIn('contract_code', $stringCodes);
            }
        });

        $contracts = $query->get(['contract_id', 'contract_code', 'created_by']);

        return response()->json([
            'data' => $contracts->map(fn ($c) => [
                'contract_id'   => $c->contract_id,
                'contract_code' => $c->contract_code,
                'created_by'    => $c->created_by,
            ])->values(),
        ]);
    });

    // Feature 4: Analytics — descriptive/diagnostic metrics snapshot for analytics-service.
    Route::get('/internal/metrics/contracts', [\App\Http\Controllers\Api\V1\Internal\InternalMetricsController::class, 'contracts']);
    Route::get('/internal/metrics/contracts/by-segment', [\App\Http\Controllers\Api\V1\Internal\InternalMetricsController::class, 'contractsBySegment']);

    // SERMS Integration (1.1): Active contracts for searchable dropdown & burn-rate margin lookups.
    // Exposed at /active (routed via Nginx /api/contract/active) and /internal/contracts/active.
    Route::get('/active', \App\Http\Controllers\Api\V1\Internal\ActiveContractsController::class);
    Route::get('/internal/contracts/active', \App\Http\Controllers\Api\V1\Internal\ActiveContractsController::class);
});
