<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationLog extends Model
{
    use HasFactory;

    // اسم الجدول في قاعدة البيانات
    protected $table = 'verification_logs';

    // 🎯 الحقول المسموح بإدخالها وتعديلها (Mass Assignment) لإنهاء الـ Error
    protected $fillable = [
        'child_id',
        'user_id',
        'type',
        'status',
        'child_name',
        'verified_by',
        'date',
    ];

    /**
     * علاقة السجل مع الطفل المعني
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class, 'child_id');
    }

    /**
     * علاقة السجل مع المستخدم (الضابط أو الآدمن) الذي قام بالإجراء (إن وجد)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}