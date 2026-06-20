<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissingReport extends Model
{
    use HasFactory;

    // ── Status Constants ──────────────────────────────────────────────
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_PENDING  = 'pending';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED   = 'closed';

    /**
     * Human-readable labels for each status (single source of truth).
     */
    public const STATUS_LABELS = [
        self::STATUS_ACTIVE   => 'New',
        self::STATUS_PENDING  => 'Under Investigation',
        self::STATUS_RESOLVED => 'Resolved',
        self::STATUS_CLOSED   => 'Closed',
    ];

    protected $fillable = [
        'child_id',
        'reported_by',
        'notes',
        'last_seen_location',
        'last_seen_date',
        'report_type',
        'status',
        'description',
    ];

    protected $casts = [
        'last_seen_date' => 'datetime',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * Get the human-readable label for the current status.
     */
    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }
}
