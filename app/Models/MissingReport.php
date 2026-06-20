<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissingReport extends Model
{
    use HasFactory;

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
}
