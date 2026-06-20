<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\MissingReport;
use App\Services\ParentChildService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function __construct(
        private ParentChildService $parentChild,
    ) {}

    /**
     * Active missing reports for police/admin dashboard.
     */
    public function activeReports(): JsonResponse
    {
        $reports = MissingReport::with(['child', 'reporter'])
            ->where('status', MissingReport::STATUS_ACTIVE)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($report) {
                return [
                    'id'                 => $report->id,
                    'child_id'           => $report->child_id,
                    'child_name'         => $report->child->name,
                    'child_photo_path'   => $report->child->child_photo_path,
                    'mother_name'        => $report->child->mother_name,
                    'father_name'        => $report->child->father_name,
                    'father_phone'       => $report->child->father_phone,
                    'reported_by'        => $report->reporter->name,
                    'reporter_phone'     => $report->reporter->phone,
                    'notes'              => $report->notes,
                    'last_seen_location' => $report->last_seen_location,
                    'last_seen_date'     => $report->last_seen_date?->toIso8601String(),
                    'report_type'        => $report->report_type,
                    'status'             => $report->statusLabel(),
                    'description'        => $report->description,
                    'created_at'         => $report->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => $reports,
        ]);
    }

    /**
     * All reports with optional status and reporter filters.
     */
    public function allReports(Request $request): JsonResponse
    {
        $query = MissingReport::with(['child', 'reporter']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('reported_by')) {
            $query->where('reported_by', $request->reported_by);
        }

        $reports = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($report) {
                return [
                    'id'                 => $report->id,
                    'child_id'           => $report->child_id,
                    'child_name'         => $report->child->name ?? 'Unknown',
                    'child_photo_path'   => $report->child->child_photo_path,
                    'mother_name'        => $report->child->mother_name,
                    'father_name'        => $report->child->father_name,
                    'father_phone'       => $report->child->father_phone,
                    'reported_by'        => $report->reporter->name ?? 'Unknown',
                    'reporter_phone'     => $report->reporter->phone,
                    'notes'              => $report->notes,
                    'last_seen_location' => $report->last_seen_location,
                    'last_seen_date'     => $report->last_seen_date?->toIso8601String(),
                    'report_type'        => $report->report_type,
                    'status'             => $report->statusLabel(),
                    'description'        => $report->description,
                    'created_at'         => $report->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => $reports,
        ]);
    }

    /**
     * Show details of a specific missing report.
     */
    public function show(MissingReport $report): JsonResponse
    {
        $report->load(['child', 'reporter']);

        return response()->json([
            'data' => [
                'id'     => $report->id,
                'child'  => [
                    'id'                 => $report->child->id,
                    'name'               => $report->child->name,
                    'mother_name'        => $report->child->mother_name,
                    'father_name'        => $report->child->father_name,
                    'father_phone'       => $report->child->father_phone,
                    'father_national_id' => $report->child->father_national_id,
                    'gender'             => $report->child->gender,
                    'birth_date'         => $report->child->birth_date,
                    'child_photo_path'   => $report->child->child_photo_path,
                    'footprint_path'     => $report->child->footprint_path,
                    'status'             => $report->child->status,
                ],
                'reporter' => [
                    'id'    => $report->reporter->id,
                    'name'  => $report->reporter->name,
                    'phone' => $report->reporter->phone,
                    'email' => $report->reporter->email,
                ],
                'notes'              => $report->notes,
                'last_seen_location' => $report->last_seen_location,
                'last_seen_date'     => $report->last_seen_date?->toIso8601String(),
                'report_type'        => $report->report_type,
                'status'             => $report->statusLabel(),
                'description'        => $report->description,
                'created_at'         => $report->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update report status (resolve/close). If resolved, also updates child status.
     */
    public function updateStatus(Request $request, MissingReport $report): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:resolved,closed'],
        ]);

        $report->update(['status' => $validated['status']]);

        if ($validated['status'] === MissingReport::STATUS_RESOLVED) {
            $report->child->update(['status' => 'verified']);
        }

        return response()->json([
            'message' => 'Report status updated successfully.',
            'data' => [
                'id'     => $report->id,
                'status' => $report->statusLabel(),
            ],
        ]);
    }

    /**
     * Verification logs — all children ordered by last update (shared with police).
     */
    public function verificationLogs(): JsonResponse
    {
        $logs = Child::with(['parent'])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($child) {
                return array_merge($this->parentChild->childPayload($child), [
                    'child_name'  => $child->name,
                    'type'        => $child->user_id ? 'parent' : 'admin',
                    'verified_by' => $child->parent?->name ?? 'Admin',
                    'date'        => $child->updated_at?->format('Y-m-d') ?? $child->created_at?->format('Y-m-d'),
                    'parent'      => $child->parent ? [
                        'id'    => $child->parent->id,
                        'name'  => $child->parent->name,
                        'phone' => $child->parent->phone,
                    ] : null,
                ]);
            });

        return response()->json([
            'data' => $logs,
        ]);
    }
}
