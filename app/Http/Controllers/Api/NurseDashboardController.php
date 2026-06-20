<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\JsonResponse;

class NurseDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $totalChildrenToday = Child::count();

        $verifiedChildren = Child::whereIn('status', ['Verified', 'verified'])->count();
        $pendingChildren  = Child::whereIn('status', ['Pending', 'pending'])->count();
        $alertChildren    = Child::whereIn('status', ['Alerts', 'Alert', 'missing', 'missing_report'])->count();

        $childrenList = Child::orderBy('created_at', 'desc')
            ->take(10)
            ->get(['id', 'name_en', 'mother_name', 'status', 'created_at'])
            ->map(function ($child) {
                return [
                    'id'           => $child->id,
                    'name_en'      => $child->name_en ?? 'Unknown Infant',
                    'mother_name'  => $child->mother_name ?? 'N/A',
                    'status'       => $child->status ?? 'Pending',
                    'created_at'   => $child->created_at ? $child->created_at->toIso8601String() : now()->toIso8601String(),
                    'last_check'   => $child->created_at ? $child->created_at->diffForHumans() : 'Just Now'
                ];
            });

        return response()->json([
            'success' => true,
            'stats' => [
                'total_today'    => $totalChildrenToday,
                'verified_count' => $verifiedChildren,
                'pending_count'  => $pendingChildren,
                'issues_count'   => $alertChildren,
            ],
            'recent_children' => $childrenList,
            'children'        => $childrenList
        ], 200);
    }
}
