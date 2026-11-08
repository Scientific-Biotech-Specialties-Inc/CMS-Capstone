<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MeilisearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SearchController extends Controller
{
    public function __invoke(Request $request, MeilisearchService $meili): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        $results = $meili->search($request->string('q')->toString());

        return response()->json([
            'data'  => $results['hits'] ?? [],
            'query' => $request->string('q')->toString(),
            'total' => $results['estimatedTotalHits'] ?? count($results['hits'] ?? []),
        ]);
    }
}
