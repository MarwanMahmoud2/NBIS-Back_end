<?php

namespace App\Services;

use App\Models\AdminNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminNotificationService
{
    public function listPaginated(int $perPage = 20): LengthAwarePaginator
    {
        return AdminNotification::query()
            ->latest()
            ->paginate($perPage);
    }

    public function unreadCount(): int
    {
        return AdminNotification::query()->whereNull('read_at')->count();
    }

    public function markRead(AdminNotification $notification): AdminNotification
    {
        if (! $notification->read_at) {
            $notification->read_at = now();
            $notification->save();
        }

        return $notification;
    }

    public function markAllRead(): int
    {
        return AdminNotification::query()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function createSystem(string $title, string $message, string $level = 'info', ?string $actionUrl = null): AdminNotification
    {
        return AdminNotification::query()->create([
            'title' => $title,
            'message' => $message,
            'level' => $level,
            'action_url' => $actionUrl,
        ]);
    }
}
