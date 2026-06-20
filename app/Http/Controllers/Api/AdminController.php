<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\User;
use App\Services\AdminNotificationService;
use App\Services\ParentChildService;
use App\Services\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(
        private SystemSettingsService    $settingsService,
        private AdminNotificationService $notificationService,
        private ParentChildService       $parentChild,
    ) {}

    // ── Dashboard ────────────────────────────────────────────────────

    /**
     * Aggregate counts for the admin dashboard.
     */
    public function dashboardStats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'total_children'      => Child::count(),
                'total_users'         => User::count(),
                'verified_children'   => Child::where('status', 'verified')->count(),
                'pending_children'    => Child::where('status', 'pending')->count(),
                'missing_children'    => Child::where('status', 'missing')->count(),
                'total_organizations' => User::whereIn('role', ['nurse', 'police'])->count(),
                'system_alerts'       => $this->notificationService->unreadCount(),
            ],
        ]);
    }

    /**
     * Latest 10 children for the dashboard overview (shared with police).
     */
    public function childrenOverview(): JsonResponse
    {
        $children = Child::with('parent')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(fn (Child $child) => array_merge(
                $this->parentChild->childPayload($child),
                [
                    'created_at' => $child->created_at?->format('Y-m-d'),
                    'parent'     => $child->parent ? [
                        'id'    => $child->parent->id,
                        'name'  => $child->parent->name,
                        'phone' => $child->parent->phone,
                    ] : null,
                ],
            ));

        return response()->json([
            'data' => $children,
        ]);
    }

    // ── Children Management ──────────────────────────────────────────

    /**
     * Paginated child listing with search and status filters.
     */
    public function children(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Child::with('parent');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('mother_name', 'like', "%{$search}%")
                  ->orWhere('father_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage  = (int) $request->integer('per_page', 50);
        $children = $query->latest()->paginate($perPage);

        $mapped = $children->map(fn (Child $child) => array_merge(
            $this->parentChild->childPayload($child),
            [
                'created_at' => $child->created_at?->diffForHumans(),
                'parent'     => $child->parent ? [
                    'id'    => $child->parent->id,
                    'name'  => $child->parent->name,
                    'phone' => $child->parent->phone,
                ] : null,
            ],
        ));

        return response()->json([
            'data' => $mapped,
        ]);
    }

    /**
     * Delete a child record.
     */
    public function deleteChild(Child $child): JsonResponse
    {
        $child->delete();

        return response()->json([
            'message' => 'Child deleted successfully.',
        ]);
    }

    // ── System Settings ──────────────────────────────────────────────

    /**
     * Retrieve all system settings.
     */
    public function settings(): JsonResponse
    {
        return response()->json([
            'data' => $this->settingsService->getAll(),
        ]);
    }

    /**
     * Update system settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language'        => ['nullable', 'string', 'in:en,ar,fr'],
            'notifications'   => ['nullable', 'boolean'],
            'email_alerts'    => ['nullable', 'boolean'],
            'two_factor'      => ['nullable', 'boolean'],
            'login_alerts'    => ['nullable', 'boolean'],
            'session_timeout' => ['nullable', 'integer', 'in:0,15,30,60'],
        ]);

        $updated = $this->settingsService->update($validated, $request->user());

        return response()->json([
            'message' => 'Settings updated successfully.',
            'data'    => $updated,
        ]);
    }

    // ── Parent-Child Linking ─────────────────────────────────────────

    /**
     * Link a child to a parent account by email.
     */
    public function linkChildToParent(Request $request, Child $child): JsonResponse
    {
        $validated = $request->validate([
            'parent_email' => ['required', 'email', 'exists:users,email'],
        ]);

        $parent = User::where('email', $validated['parent_email'])
            ->where('role', 'user')
            ->first();

        if (! $parent) {
            return response()->json([
                'message' => 'Parent account not found with this email.',
            ], 404);
        }

        if ($child->user_id === $parent->id && $child->is_linked) {
            return response()->json([
                'message' => 'Child is already linked to this parent.',
            ], 422);
        }

        $child->update([
            'user_id'      => $parent->id,
            'parent_email' => $parent->email,
            'is_linked'    => true,
        ]);

        return response()->json([
            'message' => 'Child successfully linked to parent account.',
            'data' => [
                'child_id'     => $child->id,
                'parent_id'    => $parent->id,
                'parent_email' => $parent->email,
                'parent_name'  => $parent->name,
            ],
        ]);
    }

    /**
     * Unlink a child from its parent account.
     */
    public function unlinkChildFromParent(Child $child): JsonResponse
    {
        if (! $child->is_linked) {
            return response()->json([
                'message' => 'Child is not linked to any parent.',
            ], 422);
        }

        $child->update([
            'user_id'      => null,
            'parent_email' => null,
            'is_linked'    => false,
        ]);

        return response()->json([
            'message' => 'Child successfully unlinked from parent account.',
        ]);
    }
}
