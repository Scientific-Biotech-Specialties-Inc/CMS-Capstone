<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $token = $request->bearerToken();

        // 1. Fetch user profiles from auth service to map user IDs to names, emails, and roles
        $userMap  = [];
        $emailMap = [];
        $roleMap  = [];
        try {
            $usersResponse = Http::withHeaders([
                'Accept'             => 'application/json',
                'Authorization'      => 'Bearer ' . $token,
                'X-Internal-Service' => 'vendor-management',
            ])->timeout(3)->get(env('AUTH_SERVICE_URL', 'http://auth-service:8000/api') . '/admin/users', [
                'per_page' => 100
            ]);

            if ($usersResponse->successful() && isset($usersResponse->json()['data'])) {
                foreach ($usersResponse->json()['data'] as $u) {
                    $firstName = $u['profile']['first_name'] ?? '';
                    $lastName  = $u['profile']['last_name']  ?? '';
                    $fullName  = trim("{$firstName} {$lastName}");
                    $userId    = $u['id'];
                    $userMap[$userId]  = !empty($fullName) ? $fullName : ($u['email'] ?? 'Sales & Marketing User');
                    $emailMap[$userId] = $u['email'] ?? '';
                    $roleMap[$userId]  = $u['profile']['role']['name'] ?? 'Sales & Marketing';
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to fetch user list for audit log mapping: ' . $e->getMessage());
        }

        // 2. Build DB query — pagination params extracted early for paginate() call
        $page    = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min((int) $request->input('per_page', 20), 100));

        $cmsLogsQuery = AuditLog::query()->where('user_department', 'Sales & Marketing');

        if ($request->filled('action')) {
            $cmsLogsQuery->where('action', $request->action);
        }
        if ($request->filled('date')) {
            $cmsLogsQuery->whereDate('performed_at', $request->date);
        }
        if ($request->filled('search')) {
            $s = mb_substr((string) $request->search, 0, 255);
            $cmsLogsQuery->where(function ($q) use ($s) {
                $q->where('user_name',    'like', "%{$s}%")
                  ->orWhere('user_email',  'like', "%{$s}%")
                  ->orWhere('entity_type', 'like', "%{$s}%")
                  ->orWhere('action',      'like', "%{$s}%");
            });
        }

        // DB-level pagination — replaces limit(150)->get() + in-memory slicing
        $paginator = $cmsLogsQuery->orderBy('performed_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        // 3. Remote Auth logs disabled (login/logout events are pushed directly to CMS)

        // 4. Normalize paginated results
        $items = [];
        foreach ($paginator->items() as $log) {
            $userName  = $log->user_name  ?? ($userMap[$log->user_id]  ?? 'Sales & Marketing User');
            $userEmail = $log->user_email ?? ($emailMap[$log->user_id] ?? '');
            $userRole  = $log->user_role  ?? ($roleMap[$log->user_id]  ?? 'Sales & Marketing');

            // Extract entity name from stored JSON for human-readable descriptions
            $newArr = is_array($log->new_data) ? $log->new_data : [];
            $oldArr = is_array($log->old_data) ? $log->old_data : [];
            $name   = $newArr['partner_name'] ?? $newArr['supplier_name']
                   ?? $oldArr['partner_name'] ?? $oldArr['supplier_name']
                   ?? null;

            $typeMap   = ['BusinessPartner' => 'Business Partner', 'Supplier' => 'Supplier'];
            $typeLabel = $typeMap[$log->entity_type] ?? $log->entity_type;
            $label     = $name ? "({$name})" : "#{$log->entity_id}";

            if ($log->action === 'created')          $description = "Created {$typeLabel} {$label}";
            elseif ($log->action === 'updated')      $description = "Updated {$typeLabel} {$label}";
            elseif ($log->action === 'deleted')      $description = "Deleted {$typeLabel} {$label}";
            elseif ($log->action === 'user_created') {
                $email       = $newArr['email'] ?? '';
                $description = "Created User account for {$email}";
            } else {
                $description = ucfirst($log->action) . " {$typeLabel}";
            }

            $performedAtStr = $log->performed_at
                ? ($log->performed_at instanceof Carbon
                    ? $log->performed_at->toIso8601String()
                    : Carbon::parse($log->performed_at)->toIso8601String())
                : now()->toIso8601String();

            $items[] = [
                'id'           => 'cms-' . $log->audit_id,
                'source'       => 'cms',
                'user_id'      => $log->user_id,
                'user_name'    => $userName,
                'user_email'   => $userEmail,
                'role'         => $userRole,
                'action'       => $log->action,
                'entity_type'  => $log->entity_type,
                'description'  => $description,
                'old_data'     => $log->old_data,
                'new_data'     => $log->new_data,
                'performed_at' => $performedAtStr,
            ];
        }

        return response()->json([
            'data'         => $items,
            'total'        => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'per_page'     => $paginator->perPage(),
            'last_page'    => $paginator->lastPage(),
        ]);
    }
}
