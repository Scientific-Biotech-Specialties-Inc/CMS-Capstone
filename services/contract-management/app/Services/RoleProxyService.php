<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proxies role data from auth-module for the Workflow Builder (Phase 4).
 * contract-management does not own role data — it only ever holds a soft
 * reference (auth_role_id + a cached role_name_snapshot). Live lookups go
 * through auth-module's internal API, the same X-Internal-Secret pattern
 * already used by AuthService for user data.
 */
class RoleProxyService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.auth.url', env('AUTH_SERVICE_URL', 'http://auth-service:8000/api'));
    }

    /**
     * List all roles (for the Role Picker dialog). Requires an end-user
     * bearer token because this hits auth-module's session-authenticated
     * /admin/roles endpoint (gated by its own 'manage-roles' ability),
     * rather than an internal-secret route — the admin building a workflow
     * is already an authenticated session.
     */
    public function listRoles(string $token, ?string $sessionId = null): array
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'X-Session-ID' => $sessionId ?: '',
            ])->get("{$this->baseUrl}/admin/roles", ['per_page' => 100]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? $data; // paginator wraps in 'data', plain array otherwise
            }

            Log::warning('RoleProxyService::listRoles failed', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('RoleProxyService::listRoles connection error', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Fetch the permissions held by a single role, by role ID. Used for
     * the save-time validation (decision #6): every role assigned to a
     * workflow step must hold cms.contracts.view AND cms.contracts.edit.
     *
     * @return string[] permission slugs
     */
    public function getRolePermissionSlugs(int $roleId, string $token, ?string $sessionId = null): array
    {
        try {
            // auth-module's /admin/roles/{id}/permissions returns permission
            // IDs, not slugs, so we also need the permission list to resolve
            // slugs. Fetch both and join client-side (contract-management
            // doesn't cache auth-module's permission table).
            $permIdsResponse = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'X-Session-ID' => $sessionId ?: '',
            ])->get("{$this->baseUrl}/admin/roles/{$roleId}/permissions");

            $allPermsResponse = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'X-Session-ID' => $sessionId ?: '',
            ])->get("{$this->baseUrl}/admin/permissions", ['per_page' => 200]);

            if (!$permIdsResponse->successful() || !$allPermsResponse->successful()) {
                Log::warning('RoleProxyService::getRolePermissionSlugs failed', [
                    'role_id' => $roleId,
                    'perm_ids_status' => $permIdsResponse->status(),
                    'all_perms_status' => $allPermsResponse->status(),
                ]);
                return [];
            }

            $permIds = $permIdsResponse->json(); // array of ints
            $allPerms = $allPermsResponse->json();
            $allPerms = $allPerms['data'] ?? $allPerms;

            $idToSlug = collect($allPerms)->keyBy('id')->map(fn ($p) => $p['slug'])->all();

            return collect($permIds)
                ->map(fn ($id) => $idToSlug[$id] ?? null)
                ->filter()
                ->values()
                ->all();
        } catch (\Exception $e) {
            Log::error('RoleProxyService::getRolePermissionSlugs connection error', [
                'role_id' => $roleId,
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Active users currently holding $roleId (primary assignment OR an
     * active delegation), via Phase 1's internal endpoint. Used by the
     * approval engine (Phase 5) to resolve task eligibility and detect the
     * zero-holders ("on hold") scenario (decision #4/#12).
     *
     * @return array<int, array{id:int,email:string,first_name:string,last_name:string,department:?string}>
     */
    public function activeHoldersOf(int $roleId): array
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-Internal-Secret' => env('INTERNAL_SERVICE_SECRET'),
            ])->get("{$this->baseUrl}/internal/roles/{$roleId}/active-holders");

            if ($response->successful()) {
                return $response->json('data') ?? [];
            }

            Log::warning('RoleProxyService::activeHoldersOf failed', [
                'role_id' => $roleId,
                'status' => $response->status(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('RoleProxyService::activeHoldersOf connection error', [
                'role_id' => $roleId,
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Resolve a single role's current name + existence/soft-delete state,
     * via the internal (secret-based) endpoint added in Phase 1. Used when
     * rendering the Visual Contract Workflow Tracker later (Phase 6), and
     * available here for completeness.
     */
    public function describeRole(int $roleId): array
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-Internal-Secret' => env('INTERNAL_SERVICE_SECRET'),
            ])->get("{$this->baseUrl}/internal/roles/{$roleId}/describe");

            if ($response->successful()) {
                return $response->json();
            }

            return ['exists' => false, 'deleted' => true, 'current_name' => null, 'was_renamed' => false, 'name_history' => []];
        } catch (\Exception $e) {
            Log::error('RoleProxyService::describeRole connection error', ['message' => $e->getMessage()]);
            return ['exists' => false, 'deleted' => true, 'current_name' => null, 'was_renamed' => false, 'name_history' => []];
        }
    }
}
