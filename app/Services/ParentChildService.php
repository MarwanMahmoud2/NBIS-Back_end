<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\Child;
use App\Models\MissingReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * منطق مشترك بين واجهة ولي الأمر (ويب) وواجهة الـ API (موبايل).
 */
class ParentChildService
{
    /** أطفال ولي الأمر المرتبطين بحسابه */
    public function childrenForParent(User $user): Collection
    {
        return Child::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get();
    }

    /** بيانات الطفل للعرض (JSON أو واجهة) */
    public function childPayload(Child $child): array
    {
        return [
            'id' => $child->id,
            'child_id' => $child->child_id,
            'name' => $child->name,
            'gender' => $child->gender,
            'birth_date' => $child->birth_date,
            'mother_name' => $child->mother_name,
            'father_name' => $child->father_name,
            'father_phone' => $child->father_phone,
            'father_national_id' => $child->father_national_id,
            'parent_email' => $child->parent_email,
            'is_linked' => $child->is_linked,
            'status' => $child->status,
            'nfc_tag_id' => $child->nfc_tag_id,
            'footprint_path' => $child->footprint_path,
            'footprint_url' => $child->footprint_url,
            'child_photo_path' => $child->child_photo_path,
            'estimated_age' => $child->estimated_age,
            'found_location' => $child->found_location,
            'date_found' => $child->date_found,
            'notes' => $child->notes,
            'registered_at' => $child->created_at?->toIso8601String(),
            'updated_at' => $child->updated_at?->toIso8601String(),
        ];
    }

    public function assertParentOwnsChild(User $user, Child $child): void
    {
        if ((int) $child->user_id !== (int) $user->id) {
            abort(403, 'You can only access your own children.');
        }
    }

    /**
     * @return array{status: 'success'|'already_missing', child: Child, notes?: ?string, report?: MissingReport}
     */
    public function reportMissing(User $user, int $childId, ?string $notes, ?string $lastSeenLocation = null, ?string $lastSeenDate = null, ?string $description = null): array
    {
        $child = Child::findOrFail($childId);
        if ($user->role !== 'admin') {
            $this->assertParentOwnsChild($user, $child);
        }

        if ($child->status === 'missing') {
            return ['status' => 'already_missing', 'child' => $child, 'notes' => $notes];
        }

        // Update child status
        $child->update([
            'status' => 'missing',
        ]);

        // Create missing report record
        $report = MissingReport::create([
            'child_id' => $child->id,
            'reported_by' => $user->id,
            'notes' => $notes,
            'last_seen_location' => $lastSeenLocation,
            'last_seen_date' => $lastSeenDate ? \Carbon\Carbon::parse($lastSeenDate) : null,
            'report_type' => 'missing',
            'status' => 'active',
            'description' => $description,
        ]);

        // Send notifications to police and admin
        $this->sendMissingChildNotifications($child, $user, $report);

        return ['status' => 'success', 'child' => $child->fresh(), 'notes' => $notes, 'report' => $report];
    }

    private function sendMissingChildNotifications(Child $child, User $reporter, MissingReport $report): void
    {
        // Get all police and admin users
        $recipients = User::whereIn('role', ['police', 'admin'])->get();

        foreach ($recipients as $recipient) {
            AdminNotification::create([
                'title' => 'Missing Child Reported',
                'message' => "Child '{$child->name}' has been reported as missing by {$reporter->name}.",
                'level' => 'urgent',
                'action_url' => $recipient->role === 'admin' ? "/admin/missing-children" : "/police/verification-logs",
                'read_at' => null,
                'created_by' => $reporter->id,
            ]);
        }
    }
}
