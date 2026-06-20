<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Child;
use App\Models\MissingReport;
use App\Models\User;
use App\Services\AdminNotificationService;
use App\Services\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function __construct(
        private SystemSettingsService $settingsService,
        private AdminNotificationService $notificationService,
    ) {
    }

    /**
     * Get dashboard statistics
     */
    public function dashboardStats(): JsonResponse
    {
        $totalChildren = Child::count();
        $totalUsers = User::count();
        $verifiedChildren = Child::where('status', 'verified')->count();
        $pendingChildren = Child::where('status', 'pending')->count();
        $missingChildren = Child::where('status', 'missing')->count();
        $systemAlerts = $this->notificationService->unreadCount();

        return response()->json([
            'data' => [
                'total_children' => $totalChildren,
                'total_users' => $totalUsers,
                'verified_children' => $verifiedChildren,
                'pending_children' => $pendingChildren,
                'missing_children' => $missingChildren,
                'total_organizations' => User::query()->whereIn('role', ['nurse', 'police'])->count(),
                'system_alerts' => $systemAlerts,
            ],
        ]);
    }

    /**
     * Get children overview for dashboard (shared with police)
     */
    public function childrenOverview(): JsonResponse
    {
        $children = Child::with('parent')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($child) {
                return [
                    'id' => $child->id,
                    'child_id' => $child->child_id,
                    'name' => $child->name,
                    'mother_name' => $child->mother_name,
                    'father_name' => $child->father_name,
                    'father_phone' => $child->father_phone,
                    'father_national_id' => $child->father_national_id,
                    'gender' => $child->gender,
                    'birth_date' => $child->birth_date,
                    'estimated_age' => $child->estimated_age,
                    'nfc_tag_id' => $child->nfc_tag_id,
                    'found_location' => $child->found_location,
                    'date_found' => $child->date_found,
                    'notes' => $child->notes,
                    'status' => $child->status,
                    'created_at' => $child->created_at ? $child->created_at->format('Y-m-d') : null,
                    'child_photo_path' => $child->child_photo_path,
                    'footprint_path' => $child->footprint_path,
                    'parent' => $child->parent ? [
                        'id' => $child->parent->id,
                        'name' => $child->parent->name,
                        'phone' => $child->parent->phone,
                    ] : null,
                ];
            });

        return response()->json([
            'data' => $children,
        ]);
    }

    /**
     * Get verification logs (shared with police)
     */
    public function verificationLogs(): JsonResponse
    {
        $logs = Child::with(['parent'])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($child) {
                return [
                    'id' => $child->id,
                    'child_id' => $child->child_id,
                    'child_name' => $child->name,
                    'type' => $child->user_id ? 'parent' : 'admin',
                    'verified_by' => $child->parent?->name ?? 'Admin',
                    'status' => $child->status,
                    'date' => $child->updated_at?->format('Y-m-d') ?? $child->created_at?->format('Y-m-d'),
                    'child_photo_path' => $child->child_photo_path,
                    'footprint_path' => $child->footprint_path,
                    'gender' => $child->gender,
                    'birth_date' => $child->birth_date,
                    'estimated_age' => $child->estimated_age,
                    'mother_name' => $child->mother_name,
                    'father_name' => $child->father_name,
                    'father_phone' => $child->father_phone,
                    'father_national_id' => $child->father_national_id,
                    'nfc_tag_id' => $child->nfc_tag_id,
                    'found_location' => $child->found_location,
                    'date_found' => $child->date_found,
                    'notes' => $child->notes,
                    'parent' => $child->parent ? [
                        'id' => $child->parent->id,
                        'name' => $child->parent->name,
                        'phone' => $child->parent->phone,
                    ] : null,
                ];
            });

        return response()->json([
            'data' => $logs,
        ]);
    }

    /**
     * Get all users with filters
     */
    public function users(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::query();

        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Use pagination to avoid memory issues
        $perPage = (int) $request->integer('per_page', 50);
        $users = $query->latest()->paginate($perPage);

        $mappedUsers = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => 'active',
                'created_at' => $user->created_at ? $user->created_at->format('Y-m-d') : null,
                'last_login' => $user->updated_at ? $user->updated_at->diffForHumans() : null,
            ];
        });

        return response()->json([
            'data' => $mappedUsers,
        ]);
    }

    /**
     * Create a new user
     */
    public function createUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:15'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,nurse,police,user'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return response()->json([
            'message' => 'User created successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
            ],
        ], 201);
    }

    /**
     * Update a user
     */
    public function updateUser(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->id === $user->id) {
            return response()->json([
                'message' => 'You cannot edit the currently logged-in account from this screen.',
            ], 403);
        }

        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Admins cannot edit other admin accounts.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:15'],
            'role' => ['nullable', 'in:admin,nurse,police,user'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        if (isset($validated['name'])) $user->name = $validated['name'];
        if (isset($validated['email'])) $user->email = $validated['email'];
        if (isset($validated['phone'])) $user->phone = $validated['phone'];
        if (isset($validated['role'])) $user->role = $validated['role'];
        if (isset($validated['password'])) $user->password = Hash::make($validated['password']);

        $user->save();

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Delete a user
     */
    public function deleteUser(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete the currently logged-in account.',
            ], 403);
        }

        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Admins cannot delete other admin accounts.',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }

    /**
     * Get all children with filters
     */
    public function children(Request $request): JsonResponse
    {
        $query = Child::with('parent');

        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('mother_name', 'like', "%{$search}%")
                  ->orWhere('father_name', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $children = $query->latest()->get()->map(function ($child) {
            return [
                'id' => $child->id,
                'child_id' => $child->child_id,
                'name' => $child->name,
                'mother_name' => $child->mother_name,
                'father_name' => $child->father_name,
                'father_phone' => $child->father_phone,
                'father_national_id' => $child->father_national_id,
                'gender' => $child->gender,
                'birth_date' => $child->birth_date,
                'estimated_age' => $child->estimated_age,
                'nfc_tag_id' => $child->nfc_tag_id,
                'found_location' => $child->found_location,
                'date_found' => $child->date_found,
                'notes' => $child->notes,
                'status' => $child->status,
                'parent_email' => $child->parent_email,
                'is_linked' => $child->is_linked,
                'created_at' => $child->created_at ? $child->created_at->diffForHumans() : null,
                'child_photo_path' => $child->child_photo_path,
                'footprint_path' => $child->footprint_path,
                'parent' => $child->parent ? [
                    'id' => $child->parent->id,
                    'name' => $child->parent->name,
                    'phone' => $child->parent->phone,
                ] : null,
            ];
        });

        return response()->json([
            'data' => $children,
        ]);
    }

    /**
     * Delete a child
     */
    public function deleteChild(Child $child): JsonResponse
    {
        $child->delete();

        return response()->json([
            'message' => 'Child deleted successfully.',
        ]);
    }

    /**
     * Get system settings
     */
    public function settings(): JsonResponse
    {
        return response()->json([
            'data' => $this->settingsService->getAll(),
        ]);
    }

    /**
     * Update system settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => ['nullable', 'string', 'in:en,ar,fr'],
            'notifications' => ['nullable', 'boolean'],
            'email_alerts' => ['nullable', 'boolean'],
            'two_factor' => ['nullable', 'boolean'],
            'login_alerts' => ['nullable', 'boolean'],
            'session_timeout' => ['nullable', 'integer', 'in:0,15,30,60'],
        ]);

        $updatedSettings = $this->settingsService->update($validated, $request->user());

        return response()->json([
            'message' => 'Settings updated successfully.',
            'data' => $updatedSettings,
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) $request->integer('per_page', 20);
        $items = $this->notificationService->listPaginated($perPage);

        return response()->json([
            'data' => $items->map(function (AdminNotification $notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'level' => $notification->level,
                    'action_url' => $notification->action_url,
                    'is_read' => (bool) $notification->read_at,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function notificationsUnreadCount(): JsonResponse
    {
        return response()->json([
            'data' => [
                'unread_count' => $this->notificationService->unreadCount(),
            ],
        ]);
    }

    public function markNotificationRead(AdminNotification $notification): JsonResponse
    {
        $notification = $this->notificationService->markRead($notification);

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => [
                'id' => $notification->id,
                'is_read' => (bool) $notification->read_at,
                'read_at' => $notification->read_at?->toIso8601String(),
            ],
        ]);
    }

    public function markAllNotificationsRead(): JsonResponse
    {
        $updatedCount = $this->notificationService->markAllRead();

        return response()->json([
            'message' => 'All notifications marked as read.',
            'data' => [
                'updated' => $updatedCount,
            ],
        ]);
    }

    /**
     * Get active missing reports for police/admin dashboard
     */
    public function activeMissingReports(): JsonResponse
    {
        $reports = MissingReport::with(['child', 'reporter'])
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($report) {
                // Map database status to new status values
                $statusMap = [
                    'active' => 'New',
                    'pending' => 'Under Investigation',
                    'resolved' => 'Resolved',
                    'closed' => 'Closed',
                ];
                $status = $statusMap[$report->status] ?? ucfirst($report->status);

                return [
                    'id' => $report->id,
                    'child_id' => $report->child_id,
                    'child_name' => $report->child->name,
                    'child_photo_path' => $report->child->child_photo_path,
                    'mother_name' => $report->child->mother_name,
                    'father_name' => $report->child->father_name,
                    'father_phone' => $report->child->father_phone,
                    'reported_by' => $report->reporter->name,
                    'reporter_phone' => $report->reporter->phone,
                    'notes' => $report->notes,
                    'last_seen_location' => $report->last_seen_location,
                    'last_seen_date' => $report->last_seen_date?->toIso8601String(),
                    'report_type' => $report->report_type,
                    'status' => $status,
                    'description' => $report->description,
                    'created_at' => $report->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => $reports,
        ]);
    }

    /**
     * Get all reports for admin (including resolved/closed)
     */
    public function allReports(Request $request): JsonResponse
    {
        $query = MissingReport::with(['child', 'reporter']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by reporter
        if ($request->has('reported_by')) {
            $query->where('reported_by', $request->reported_by);
        }

        // Map database status to new status values
        $statusMap = [
            'active' => 'New',
            'pending' => 'Under Investigation',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ];

        $reports = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($report) use ($statusMap) {
                $status = $statusMap[$report->status] ?? ucfirst($report->status);

                return [
                    'id' => $report->id,
                    'child_id' => $report->child_id,
                    'child_name' => $report->child->name ?? 'Unknown',
                    'child_photo_path' => $report->child->child_photo_path,
                    'mother_name' => $report->child->mother_name,
                    'father_name' => $report->child->father_name,
                    'father_phone' => $report->child->father_phone,
                    'reported_by' => $report->reporter->name ?? 'Unknown',
                    'reporter_phone' => $report->reporter->phone,
                    'notes' => $report->notes,
                    'last_seen_location' => $report->last_seen_location,
                    'last_seen_date' => $report->last_seen_date?->toIso8601String(),
                    'report_type' => $report->report_type,
                    'status' => $status,
                    'description' => $report->description,
                    'created_at' => $report->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => $reports,
        ]);
    }

    /**
     * Get details of a specific missing report
     */
    public function missingReportDetails(MissingReport $report): JsonResponse
    {
        $report->load(['child', 'reporter']);

        return response()->json([
            'data' => [
                'id' => $report->id,
                'child' => [
                    'id' => $report->child->id,
                    'name' => $report->child->name,
                    'mother_name' => $report->child->mother_name,
                    'father_name' => $report->child->father_name,
                    'father_phone' => $report->child->father_phone,
                    'father_national_id' => $report->child->father_national_id,
                    'gender' => $report->child->gender,
                    'birth_date' => $report->child->birth_date,
                    'child_photo_path' => $report->child->child_photo_path,
                    'footprint_path' => $report->child->footprint_path,
                    'status' => $report->child->status,
                ],
                'reporter' => [
                    'id' => $report->reporter->id,
                    'name' => $report->reporter->name,
                    'phone' => $report->reporter->phone,
                    'email' => $report->reporter->email,
                ],
                'notes' => $report->notes,
                'last_seen_location' => $report->last_seen_location,
                'last_seen_date' => $report->last_seen_date?->toIso8601String(),
                'report_type' => $report->report_type,
                'status' => $report->status,
                'description' => $report->description,
                'created_at' => $report->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update missing report status (resolve/close)
     */
    public function updateMissingReportStatus(Request $request, MissingReport $report): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:resolved,closed'],
        ]);

        $report->update(['status' => $validated['status']]);

        // If resolved, also update child status
        if ($validated['status'] === 'resolved') {
            $report->child->update(['status' => 'verified']);
        }

        return response()->json([
            'message' => 'Report status updated successfully.',
            'data' => [
                'id' => $report->id,
                'status' => $report->status,
            ],
        ]);
    }

    /**
     * Link a child to a parent account by email
     */
    public function linkChildToParent(Request $request, Child $child): JsonResponse
    {
        $validated = $request->validate([
            'parent_email' => ['required', 'email', 'exists:users,email'],
        ]);

        // Find parent user by email
        $parent = User::where('email', $validated['parent_email'])
            ->where('role', 'user')
            ->first();

        if (!$parent) {
            return response()->json([
                'message' => 'Parent account not found with this email.',
            ], 404);
        }

        // Check if child is already linked to this parent
        if ($child->user_id === $parent->id && $child->is_linked) {
            return response()->json([
                'message' => 'Child is already linked to this parent.',
            ], 422);
        }

        // Link child to parent
        $child->update([
            'user_id' => $parent->id,
            'parent_email' => $parent->email,
            'is_linked' => true,
        ]);

        return response()->json([
            'message' => 'Child successfully linked to parent account.',
            'data' => [
                'child_id' => $child->id,
                'parent_id' => $parent->id,
                'parent_email' => $parent->email,
                'parent_name' => $parent->name,
            ],
        ]);
    }

    /**
     * Unlink a child from parent account
     */
    public function unlinkChildFromParent(Child $child): JsonResponse
    {
        if (!$child->is_linked) {
            return response()->json([
                'message' => 'Child is not linked to any parent.',
            ], 422);
        }

        $child->update([
            'user_id' => null,
            'parent_email' => null,
            'is_linked' => false,
        ]);

        return response()->json([
            'message' => 'Child successfully unlinked from parent account.',
        ]);
    }
}
