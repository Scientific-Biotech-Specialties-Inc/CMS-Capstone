<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Contracts\ApplyExecutionAction;
use App\Models\ContractExecutionEvent;
use App\Models\Contract;
use App\Services\NotificationService;
use App\Policies\ContractExecutionPolicy;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class NotifyExecutionReviewers implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [10, 60];

    public function __construct(private readonly int $eventId) {}

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    public function handle(ApplyExecutionAction $execution, ContractExecutionPolicy $policy, NotificationService $notifications): void
    {
        $event = ContractExecutionEvent::find($this->eventId);
        if (!$event || $event->state !== 'pending') {
            return;
        }

        $contract = Contract::findOrFail($event->contract_id);
        if ($contract->revision_paused_at !== null) {
            return;
        }

        $roleNames = $policy->reviewerRoleNames($execution->reviewers($contract), $event->stage);
        if (!$roleNames) {
            return;
        }

        $authUrl = config('services.auth.url', env('AUTH_SERVICE_URL', 'http://auth-service:8000/api'));
        $users = Http::acceptJson()->withHeaders(['X-Internal-Secret' => env('INTERNAL_SERVICE_SECRET')])
            ->timeout(10)->get("{$authUrl}/internal/users-by-roles", ['roles' => implode(',', $roleNames)])
            ->throw()->json('data', []);
        $lastRole = array_pop($roleNames);
        $reviewers = $roleNames ? implode(', ', $roleNames).' or '.$lastRole : $lastRole;
        $subject = match ($event->stage) {
            'For Signing' => 'a submitted signed PDF',
            'Signed' => 'a submitted notarized PDF',
            default => 'a submitted '.$event->stage,
        };
        $message = "Contract Number #{$contract->getKey()} with {$subject} is waiting for confirmation from {$reviewers}.";

        foreach ($users as $user) {
            $id = (int) $user['id'];
            $role = $user['role'];
            $owner = $id === (int) $contract->created_by;
            if (($owner && config('services.features.enforce_sod')) || (!$owner && in_array($role, ['Sales', 'Employee'], true))) {
                continue;
            }
            if (!$notifications->push((int) $contract->getKey(), "execution_confirmation_{$this->eventId}", $message, $role, $id)) {
                throw new RuntimeException('Execution confirmation notification could not be delivered.');
            }
        }
    }
}
