<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\MissingReport;
use App\Services\ParentChildService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentController extends Controller
{
    public function __construct(
        private ParentChildService $parentChild,
    ) {}

    /** عرض قائمة أطفال ولي الأمر */
    public function index(Request $request): JsonResponse
    {
        $children = $this->parentChild->childrenForParent($request->user());

        return response()->json([
            'success' => true,
            'data' => $children->map(fn(Child $child) => $this->parentChild->childPayload($child)),
        ]);
    }

    /** عرض تفاصيل طفل واحد */
    public function show(Request $request, Child $child): JsonResponse
    {
        $this->parentChild->assertParentOwnsChild($request->user(), $child);

        return response()->json([
            'success' => true,
            'data'    => $this->parentChild->childPayload($child),
        ]);
    }

    /** الإبلاغ عن طفل مفقود */
    public function reportMissing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'child_id'           => ['required', 'integer', 'exists:children,id'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'last_seen_location' => ['nullable', 'string', 'max:255'],
            'last_seen_date'     => ['nullable', 'date'],
            'description'        => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->parentChild->reportMissing(
            $request->user(),
            (int) $validated['child_id'],
            $validated['notes'] ?? null,
            $validated['last_seen_location'] ?? null,
            $validated['last_seen_date'] ?? null,
            $validated['description'] ?? null
        );

        if ($result['status'] === 'already_missing') {
            return response()->json([
                'success' => false,
                'message' => 'This child is already reported as missing.',
                'data'    => $this->parentChild->childPayload($result['child']),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Missing child report submitted successfully.',
            'data'    => $this->parentChild->childPayload($result['child']),
            'notes'   => $result['notes'] ?? null,
            'report'  => $result['report'] ?? null,
        ], 201);
    }

    /**
     * Get parent's verification logs (from VerificationLog model).
     */
    public function getReports(Request $request): JsonResponse
    {
        $reports = \App\Models\VerificationLog::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * Get parent's missing reports (from MissingReport model).
     */
    public function myReports(Request $request): JsonResponse
    {
        $reports = MissingReport::where('reported_by', $request->user()->id)
            ->with('child')
            ->latest()
            ->get()
            ->map(function ($report) {
                // Map database status to display status values
                $statusMap = [
                    'active'   => 'New',
                    'pending'  => 'Under Investigation',
                    'resolved' => 'Resolved',
                    'closed'   => 'Closed',
                ];
                $status = $statusMap[$report->status] ?? ucfirst($report->status);

                return [
                    'id'                 => $report->id,
                    'child_name'         => $report->child->name ?? 'Unknown',
                    'child_id'           => $report->child->id ?? 'Unknown',
                    'type'               => 'Missing Child',
                    'status'             => $status,
                    'date'               => $report->created_at ? $report->created_at->format('F Y') : null,
                    'avatar'             => $report->child->name ? strtoupper(substr($report->child->name, 0, 1)) : 'U',
                    'notes'              => $report->notes,
                    'last_seen_location' => $report->last_seen_location,
                    'last_seen_date'     => $report->last_seen_date,
                    'created_at'         => $report->created_at ? $report->created_at->toIso8601String() : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }
}