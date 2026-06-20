<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChildSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoliceController extends Controller
{
    public function search(Request $request, ChildSearchService $childSearch): JsonResponse
    {
        $validated = $request->validate([
            'search_query' => ['nullable', 'string', 'max:255'],
        ]);

        $results = $childSearch->searchByText($validated['search_query'] ?? null);

        return response()->json([
            'success' => true,
            'data'    => $childSearch->toSearchResultRows($results),
        ], 200);
    }
}
