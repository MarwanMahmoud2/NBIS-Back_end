<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MissingReport;
use App\Models\VerificationLog;
use App\Services\ParentChildService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileReportController extends Controller
{
    public function __construct(
        private ParentChildService $parentChild,
    ) {}

    /**
     * File a missing child report.
     */
    public function reportMissing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'child_id' => 'required|integer|exists:children,id',
            'notes' => 'nullable|string|max:1000',
            'last_seen_location' => 'nullable|string|max:255',
            'last_seen_date' => 'nullable|date',
            'description' => 'nullable|string|max:2000',
        ]);

        $result = $this->parentChild->reportMissing(
            $request->user(),
            (int) $validated['child_id'],
            $validated['notes'] ?? null,
            $validated['last_seen_location'] ?? null,
            $validated['last_seen_date'] ?? null,
            $validated['description'] ?? null,
        );

        if ($result['status'] === 'already_missing') {
            return response()->json([
                'status' => false,
                'message' => 'This child is already reported as missing.',
                'data' => $this->parentChild->childPayload($result['child']),
            ], 422);
        }

        return response()->json([
            'status' => true,
            'message' => 'Missing child report submitted successfully.',
            'data' => $this->parentChild->childPayload($result['child']),
            'report' => $result['report'] ?? null,
        ], 201);
    }

    /**
     * List parent's missing reports.
     */
    public function myReports(Request $request): JsonResponse
    {
        $reports = MissingReport::where('reported_by', $request->user()->id)
            ->with('child')
            ->latest()
            ->get()
            ->map(function ($report) {
                $statusMap = [
                    'active' => 'New',
                    'pending' => 'Under Investigation',
                    'resolved' => 'Resolved',
                    'closed' => 'Closed',
                ];

                return [
                    'id' => $report->id,
                    'child_name' => $report->child->name ?? 'Unknown',
                    'child_id' => $report->child->child_id ?? $report->child->id ?? 'Unknown',
                    'status' => $statusMap[$report->status] ?? ucfirst($report->status),
                    'notes' => $report->notes,
                    'last_seen_location' => $report->last_seen_location,
                    'last_seen_date' => $report->last_seen_date,
                    'description' => $report->description,
                    'created_at' => $report->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $reports,
        ]);
    }

    /**
     * Single missing report detail.
     */
    public function show(Request $request, MissingReport $report): JsonResponse
    {
        // Only the reporter or admin can view
        if ($report->reported_by !== $request->user()->id && $request->user()->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $report->load('child');

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $report->id,
                'child' => $report->child ? $this->parentChild->childPayload($report->child) : null,
                'status' => $report->status,
                'notes' => $report->notes,
                'last_seen_location' => $report->last_seen_location,
                'last_seen_date' => $report->last_seen_date,
                'description' => $report->description,
                'created_at' => $report->created_at->toIso8601String(),
                'updated_at' => $report->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get parent's verification logs.
     */
    public function verificationLogs(Request $request): JsonResponse
    {
        $logs = VerificationLog::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'data' => $logs,
        ]);
    }
}
