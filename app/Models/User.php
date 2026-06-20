<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'national_id',
        'password',
        'profile_photo_path',
        'settings',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'settings' => 'array',
    ];

    // الأطفال اللي سجلتها الممرضة
    public function registeredChildren(): HasMany
    {
        return $this->hasMany(Child::class, 'nurse_id');
    }

    // أطفال ولي الأمر المرتبطين بحسابه
    public function children(): HasMany
    {
        return $this->hasMany(Child::class, 'user_id');
    }

    // Missing reports submitted by this user
    public function missingReports(): HasMany
    {
        return $this->hasMany(MissingReport::class, 'reported_by');
    }
}