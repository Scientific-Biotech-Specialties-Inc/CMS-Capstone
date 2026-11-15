<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PrsClosingOutcomeController
{
    public function __invoke(Request $request): JsonResponse
    {
        $key = (string) config('services.prs.inbound_key');
        abort_unless($key !== '' && hash_equals($key, (string) $request->bearerToken()), 401);

        $event = $request->validate([
            'event_id' => 'required|uuid',
            'prs_activity_id' => 'required|integer|min:1',
            'revision' => 'required|integer|min:1',
            'outcome' => 'required|in:Won,Loss,Follow-up',
        ]);

        $stored = DB::table('prs_closing_events')->insertOrIgnore([...$event, 'created_at' => now()]);
        if (!$stored) {
            $existing = DB::table('prs_closing_events')->where('event_id', $event['event_id'])->first();
            abort_unless(
                $existing && (int) $existing->prs_activity_id === (int) $event['prs_activity_id']
                    && (int) $existing->revision === (int) $event['revision'] && $existing->outcome === $event['outcome'],
                409,
                'The event ID or activity revision is already in use.'
            );
        }

        return response()->json(['data' => ['event_id' => $event['event_id']]], $stored ? 201 : 200);
    }
}
