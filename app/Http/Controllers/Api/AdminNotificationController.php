<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Services\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function __construct(
        private AdminNotificationService $notificationService,
    ) {}

    /**
     * Paginated list of admin notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) $request->integer('per_page', 20);
        $items = $this->notificationService->listPaginated($perPage);

        return response()->json([
            'data' => $items->map(function (AdminNotification $notification) {
                return [
                    'id'         => $notification->id,
                    'title'      => $notification->title,
                    'message'    => $notification->message,
                    'level'      => $notification->level,
                    'action_url' => $notification->action_url,
                    'is_read'    => (bool) $notification->read_at,
                    'read_at'    => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'per_page'     => $items->perPage(),
                'total'        => $items->total(),
            ],
        ]);
    }

    /**
     * Unread notification count badge.
     */
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'data' => [
                'unread_count' => $this->notificationService->unreadCount(),
            ],
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(AdminNotification $notification): JsonResponse
    {
        $notification = $this->notificationService->markRead($notification);

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => [
                'id'      => $notification->id,
                'is_read' => (bool) $notification->read_at,
                'read_at' => $notification->read_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(): JsonResponse
    {
        $updatedCount = $this->notificationService->markAllRead();

        return response()->json([
            'message' => 'All notifications marked as read.',
            'data' => [
                'updated' => $updatedCount,
            ],
        ]);
    }
}
