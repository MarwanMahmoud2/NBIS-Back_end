<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\JsonResponse;

class NurseDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $totalChildren = Child::count();

        $verifiedChildren = Child::where('status', 'verified')->count();
        $pendingChildren  = Child::where('status', 'pending')->count();
        $alertChildren     = Child::where('status', 'missing')->count();

        $childrenList = Child::orderBy('created_at', 'desc')
            ->take(10)
            ->get(['id', 'name', 'mother_name', 'status', 'created_at'])
            ->map(function ($child) {
                return [
                    'id'          => $child->id,
                    'name'        => $child->name ?? 'Unknown Infant',
                    'mother_name' => $child->mother_name ?? 'N/A',
                    'status'      => $child->status ?? 'pending',
                    'created_at'  => $child->created_at ? $child->created_at->toIso8601String() : now()->toIso8601String(),
                    'last_check'  => $child->created_at ? $child->created_at->diffForHumans() : 'Just Now',
                ];
            });

        return response()->json([
            'success' => true,
            'stats' => [
                'total_today'    => $totalChildren,
                'verified_count' => $verifiedChildren,
                'pending_count'  => $pendingChildren,
                'issues_count'   => $alertChildren,
            ],
            'recent_children' => $childrenList,
        ], 200);
    }
}
