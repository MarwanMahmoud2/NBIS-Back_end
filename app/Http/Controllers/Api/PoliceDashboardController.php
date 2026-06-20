<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\VerificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PoliceDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $totalActiveCases  = Child::where('status', 'missing')->count();
        $verifiedMatches   = VerificationLog::where('status', 'verified')->count();
        $pendingCases      = VerificationLog::where('status', 'pending')->count();

        $highPriority = Child::where('status', 'missing')
            ->where(function ($q) {
                $q->whereNull('birth_date')->orWhere('created_at', '>=', now()->subDays(3));
            })->count();

        $reports = VerificationLog::with(['child', 'user'])
            ->latest()
            ->take(10)
            ->get();

        $formattedReports = $reports->map(function ($log) {
            return [
                'id'           => $log->id,
                'child_id'     => $log->child_id,
                'child_name'   => $log->child_name ?? ($log->child->name ?? 'Unknown Child'),
                'officer_name' => $log->verified_by ?? ($log->user->name ?? 'System AI'),
                'status'       => $log->status ?? 'pending',
                'date'         => $log->date ?? ($log->created_at ? $log->created_at->format('Y-m-d') : null),
                'child'        => $log->child ?? null,
            ];
        });

        return response()->json([
            'success' => true,
            'stats' => [
                'total_active_cases'     => $totalActiveCases,
                'verified_matches'       => $verifiedMatches,
                'pending_investigations' => $pendingCases,
                'high_priority_alerts'   => $highPriority,
            ],
            'reports' => $formattedReports,
        ], 200);
    }
}
