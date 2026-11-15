<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contract;
use App\Services\RoleProxyService;
use Illuminate\Http\Request;

final readonly class ContractExecutionPolicy
{
    public const BYPASS = 'cms.contracts.bypass_submission_confirmation';

    public function __construct(private RoleProxyService $roles) {}

    public function owns(Request $request, Contract $contract): bool
    {
        return $contract->created_by !== null && (int) $contract->created_by === (int) $request->input('auth_id');
    }

    public function canSubmit(Request $request, Contract $contract): bool
    {
        $role = $request->input('auth_role');
        $perms = (array) $request->input('auth_permissions', []);

        return $this->owns($request, $contract)
            || in_array($role, ['Manager', 'Admin', 'Super Admin'], true)
            || in_array('cms.contracts.edit', $perms, true)
            || in_array('cms.contracts.approve', $perms, true);
    }

    public function canManageRevision(Request $request, Contract $contract): bool
    {
        $role = $request->input('auth_role');
        return $this->owns($request, $contract)
            || in_array($role, ['Manager', 'Admin', 'Super Admin'], true);
    }

    public function hasBypass(Request $request): bool
    {
        return in_array(self::BYPASS, $request->input('auth_permissions', []), true);
    }

    public function canConfirm(Request $request, array $reviewers, string $stage): bool
    {
        $role = $request->input('auth_role');
        if (in_array($role, ['Admin', 'Super Admin'], true)) {
            return true;
        }

        if ($stage === 'For Signing' && !in_array($role, ['Manager', 'CEO'], true)) {
            return false;
        }

        $configured = $reviewers[$stage] ?? [];
        if (!$configured) {
            return in_array($role, ['Manager', 'Admin', 'Super Admin'], true) || ($stage === 'For Signing' && $role === 'CEO');
        }

        foreach ($configured as $reviewer) {
            if (collect($this->roles->activeHoldersOf((int) $reviewer['auth_role_id']))
                ->contains(fn (array $holder): bool => (int) $holder['id'] === (int) $request->input('auth_id'))) {
                return true;
            }
        }

        return false;
    }

    public function reviewerRoleNames(array $reviewers, string $stage): array
    {
        $configured = $reviewers[$stage] ?? [];
        if (!$configured) {
            return $stage === 'For Signing' ? ['Manager', 'CEO'] : ['Manager'];
        }

        return collect($configured)
            ->map(fn (array $entry) => $this->roles->describeRole((int) $entry['auth_role_id'])['current_name'] ?? null)
            ->filter(fn ($name) => $name && ($stage !== 'For Signing' || in_array($name, ['Manager', 'CEO', 'Admin', 'Super Admin'], true)))
            ->unique()->values()->all();
    }
}
