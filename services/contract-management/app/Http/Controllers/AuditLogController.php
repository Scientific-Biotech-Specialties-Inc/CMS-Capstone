<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class AuditLogController extends Controller
{
    /**
     * Display a listing of local CMS audit logs (login/logout events are written
     * via the internal webhook from the auth service).
     */
    public function index(Request $request)
    {
        $token = $request->bearerToken();
        
        // 1. Fetch user profiles from auth service to map user IDs to names, emails, and roles
        $userMap = [];
        $emailMap = [];
        $roleMap = [];
        try {
            $usersResponse = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'X-Internal-Service' => 'contract-management',
            ])->timeout(3)->get(env('AUTH_SERVICE_URL', 'http://auth-service:8000/api') . '/admin/users', [
                'per_page' => 100
            ]);

            if ($usersResponse->successful() && isset($usersResponse->json()['data'])) {
                foreach ($usersResponse->json()['data'] as $u) {
                    $firstName = isset($u['profile']['first_name']) ? $u['profile']['first_name'] : '';
                    $lastName = isset($u['profile']['last_name']) ? $u['profile']['last_name'] : '';
                    $fullName = trim("{$firstName} {$lastName}");
                    $userId = $u['id'];
                    $userMap[$userId] = !empty($fullName) ? $fullName : ($u['email'] ?? 'Sales & Marketing User');
                    $emailMap[$userId] = $u['email'] ?? '';
                    $roleMap[$userId] = isset($u['profile']['role']['name']) ? $u['profile']['role']['name'] : 'Sales & Marketing';
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to fetch user list for audit log mapping: ' . $e->getMessage());
        }

        // 2. Fetch local CMS logs
        $cmsLogsQuery = AuditLog::query()->where(function ($query) {
            $query->whereIn('user_department', ['Sales & Marketing', 'Sales'])
                  ->orWhereNotIn('entity_type', ['Contract', 'Document', 'Supplier', 'BusinessPartner']);
        });
        
        if ($request->has('action') && !empty($request->action)) {
            $actionFilter = $request->action;
            if ($actionFilter === 'Contract Created') {
                $cmsLogsQuery->where('action', 'created')->where('entity_type', 'Contract');
            } elseif ($actionFilter === 'Contract Updated') {
                $cmsLogsQuery->where('action', 'updated')->where('entity_type', 'Contract');
            } elseif ($actionFilter === 'Contract Deleted') {
                $cmsLogsQuery->where('action', 'deleted')->where('entity_type', 'Contract');
            } elseif ($actionFilter === 'Document Uploaded' || $actionFilter === 'document_uploaded') {
                $cmsLogsQuery->where(function($q) {
                    $q->where('action', 'document_uploaded')
                      ->orWhere(function($sq) {
                          $sq->where('action', 'created')->where('entity_type', 'Document');
                      });
                });
            } elseif ($actionFilter === 'Document Deleted' || $actionFilter === 'document_deleted') {
                $cmsLogsQuery->where(function($q) {
                    $q->where('action', 'document_deleted')
                      ->orWhere(function($sq) {
                          $sq->where('action', 'deleted')->where('entity_type', 'Document');
                      });
                });
            } elseif ($actionFilter === 'Partner Added') {
                $cmsLogsQuery->where('action', 'created')->where('entity_type', 'BusinessPartner');
            } elseif ($actionFilter === 'Partner Updated') {
                $cmsLogsQuery->where('action', 'updated')->where('entity_type', 'BusinessPartner');
            } elseif ($actionFilter === 'User Created') {
                $cmsLogsQuery->where('action', 'user_created');
            } elseif ($actionFilter === 'Login' || $actionFilter === 'Login Success') {
                $cmsLogsQuery->where('action', 'Login Success');
            } elseif ($actionFilter === 'Logout') {
                $cmsLogsQuery->where('action', 'Logout');
            } else {
                $cmsLogsQuery->where('action', $actionFilter);
            }
        }
        if ($request->has('date') && !empty($request->date)) {
            $cmsLogsQuery->whereDate('performed_at', $request->date);
        }

        $cmsLogs = $cmsLogsQuery->orderBy('performed_at', 'desc')->limit(150)->get();

        // 3. Normalize local CMS logs
        $merged = [];

        // Process CMS logs
        foreach ($cmsLogs as $log) {
            $userName = $log->user_name ?? ($userMap[$log->user_id] ?? 'Sales & Marketing User');
            $userEmail = $log->user_email ?? ($emailMap[$log->user_id] ?? '');
            $userRole = $log->user_role ?? ($roleMap[$log->user_id] ?? 'Sales & Marketing');

            $new_data = is_array($log->new_data) ? $log->new_data : [];
            $old_data = is_array($log->old_data) ? $log->old_data : [];

            // Normalize action name for frontend
            $action = $log->action;
            if ($log->action === 'created' && $log->entity_type === 'Contract') {
                $action = 'Contract Created';
            } elseif ($log->action === 'updated' && $log->entity_type === 'Contract') {
                $action = 'Contract Updated';
            } elseif ($log->action === 'deleted' && $log->entity_type === 'Contract') {
                $action = 'Contract Deleted';
            } elseif (($log->action === 'document_uploaded' || $log->action === 'created') && $log->entity_type === 'Document') {
                $action = 'Document Uploaded';
            } elseif (($log->action === 'document_deleted' || $log->action === 'deleted') && $log->entity_type === 'Document') {
                $action = 'Document Deleted';
            } elseif ($log->action === 'created' && $log->entity_type === 'BusinessPartner') {
                $action = 'Partner Added';
            } elseif ($log->action === 'updated' && $log->entity_type === 'BusinessPartner') {
                $action = 'Partner Updated';
            } elseif ($log->action === 'deleted' && $log->entity_type === 'BusinessPartner') {
                $action = 'Partner Deleted';
            } elseif ($log->action === 'created' && $log->entity_type === 'Supplier') {
                $action = 'Partner Added';
            } elseif ($log->action === 'updated' && $log->entity_type === 'Supplier') {
                $action = 'Partner Updated';
            } elseif ($log->action === 'deleted' && $log->entity_type === 'Supplier') {
                $action = 'Partner Deleted';
            } elseif ($log->action === 'user_created') {
                $action = 'User Created';
            } elseif ($log->action === 'user_activated' || $log->action === 'user_deactivated') {
                $action = 'User Updated';
            } elseif ($log->action === 'profile_updated') {
                $action = 'User Updated';
            } elseif ($log->action === 'email_preferences_updated') {
                $action = 'Settings Changed';
            }

            $description = '';
            if ($action === 'Contract Created') {
                $description = "Created Contract #{$log->entity_id}";
            } elseif ($action === 'Contract Updated') {
                $description = "Updated Contract #{$log->entity_id}";
            } elseif ($action === 'Contract Deleted') {
                $description = "Deleted Contract #{$log->entity_id}";
            } elseif ($action === 'Document Uploaded') {
                $fileName = $new_data['file_name'] ?? 'Document';
                $description = "Uploaded Document \"{$fileName}\"";
            } elseif ($action === 'Document Deleted') {
                $fileName = $old_data['file_name'] ?? 'Document';
                $description = "Deleted Document \"{$fileName}\"";
            } elseif (in_array($action, ['Partner Added', 'Partner Updated', 'Partner Deleted'])) {
                $entityName = $new_data['partner_name'] ?? $new_data['supplier_name']
                           ?? $old_data['partner_name'] ?? $old_data['supplier_name'] ?? null;
                $entityType = $log->entity_type === 'Supplier' ? 'Supplier' : 'Business Partner';
                $label      = $entityName ? "({$entityName})" : "#{$log->entity_id}";
                $verb       = $action === 'Partner Added' ? 'Created' : ($action === 'Partner Deleted' ? 'Deleted' : 'Updated');
                $description = "{$verb} {$entityType} {$label}";
            } elseif ($action === 'User Created') {
                $email = $new_data['email'] ?? '';
                $description = "Created User account for {$email}";
            } elseif ($log->action === 'user_activated') {
                $email = $new_data['email'] ?? '';
                $description = "Activated User account for {$email}";
            } elseif ($log->action === 'user_deactivated') {
                $email = $new_data['email'] ?? '';
                $description = "Deactivated User account for {$email}";
            } elseif ($log->action === 'profile_updated') {
                $email = $new_data['email'] ?? $userEmail;
                $description = "Updated profile details for {$email}";
            } elseif ($log->action === 'email_preferences_updated') {
                $description = "Updated email notification preferences";
            } elseif ($log->action === 'permission_denied') {
                $reqPerm = $new_data['required_permission'] ?? 'N/A';
                $description = "Access Denied: Lacks '{$reqPerm}' permission";
            } elseif (in_array(strtoupper($log->action), [
                'ROLE_CREATED', 'ROLE_UPDATED', 'ROLE_DELETED', 'ROLE_ASSIGNED', 
                'ROLE_PERMISSIONS_UPDATED', 'PERMISSION_ROLES_UPDATED'
            ])) {
                $description = $new_data['message'] ?? (ucfirst(strtolower($log->action)) . " {$log->entity_type}");
            } else {
                $description = $new_data['message'] ?? (ucfirst(str_replace('_', ' ', strtolower($log->action))) . " {$log->entity_type}");
            }

            $performedAtStr = $log->performed_at 
                ? ($log->performed_at instanceof Carbon ? $log->performed_at->toIso8601String() : Carbon::parse($log->performed_at)->toIso8601String())
                : now()->toIso8601String();

            $merged[] = [
                'id' => 'cms-' . $log->audit_id,
                'source' => 'cms',
                'user_id' => $log->user_id,
                'user_name' => $userName,
                'user_email' => $userEmail,
                'role' => $userRole,
                'action' => $action,
                'entity_type' => $log->entity_type,
                'description' => $description,
                'old_data' => $log->old_data,
                'new_data' => $log->new_data,
                'performed_at' => $performedAtStr,
            ];
        }

        // Search filter on merged logs
        if ($request->has('search') && !empty($request->search)) {
            $search = strtolower(mb_substr((string) $request->search, 0, 255));
            $merged = array_filter($merged, function ($item) use ($search) {
                return str_contains(strtolower($item['user_name'] ?? ''), $search) ||
                       str_contains(strtolower($item['user_email'] ?? ''), $search) ||
                       str_contains(strtolower($item['description'] ?? ''), $search) ||
                       str_contains(strtolower($item['action'] ?? ''), $search) ||
                       str_contains(strtolower($item['role'] ?? ''), $search);
            });
            $merged = array_values($merged);
        }

        // 5. Sort by performed_at descending
        usort($merged, function ($a, $b) {
            return strcmp($b['performed_at'], $a['performed_at']);
        });

        // 6. Paginate in-memory
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min((int) $request->input('per_page', 20), 100));
        $total = count($merged);

        $offset = ($page - 1) * $perPage;
        $items = array_slice($merged, $offset, $perPage);

        return response()->json([
            'data' => $items,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage)
        ]);
    }
}
