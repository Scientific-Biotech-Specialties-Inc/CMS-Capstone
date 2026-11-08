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

    public function canManageRevision(Request $request, Contract $contract): bool
    {
        return $this->owns($request, $contract) || $request->input('auth_role') === 'Manager';
    }

    public function hasBypass(Request $request): bool
    {
        return in_array(self::BYPASS, $request->input('auth_permissions', []), true);
    }

    public function canConfirm(Request $request, array $reviewers, string $stage): bool
    {
        $role = $request->input('auth_role');
        if ($stage === 'For Signing' && !in_array($role, ['Manager', 'CEO'], true)) {
            return false;
        }

        $configured = $reviewers[$stage] ?? [];
        if (!$configured) {
            return $role === 'Manager' || ($stage === 'For Signing' && $role === 'CEO');
        }

        foreach ($configured as $reviewer) {
            if (collect($this->roles->activeHoldersOf((int) $reviewer['auth_role_id']))
                ->contains(fn (array $holder): bool => (int) $holder['id'] === (int) $request->input('auth_id'))) {
                return true;
            }
        }

        return false;
    }
}
