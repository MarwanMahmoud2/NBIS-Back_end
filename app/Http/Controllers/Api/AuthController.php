<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\Child;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const ALLOWED_ROLES = ['user', 'nurse', 'police', 'admin'];
    private const DEFAULT_ACCOUNT_SETTINGS = [
        'language' => 'en',
        'notifications' => true,
        'email_alerts' => true,
        'two_factor' => false,
        'login_alerts' => false,
        'session_timeout' => 30,
    ];

    /**
     * تطبيق ولي الأمر: إنشاء حساب جديد .
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'national_id' => $validated['national_id'] ?? null,
            'role' => 'user',
        ]);

        $this->linkExistingChildrenToParent($user);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully.',
            'user' => $this->userPayload($user),
            'token' => $token,
        ], 201);
    }

    /** تسجيل دخول — جميع الأدوار (React / mobile) */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $throttleKey = Str::lower($validated['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Too many login attempts. Try again in {$seconds} seconds.",
            ], 429);
        }

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        if (! in_array($user->role, self::ALLOWED_ROLES, true)) {
            RateLimiter::hit($throttleKey, 60);
            return response()->json([
                'message' => 'This account role is not allowed to sign in.',
            ], 403);
        }

        if ($user->role === 'user') {
            $this->linkExistingChildrenToParent($user);
        }

        RateLimiter::clear($throttleKey);
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Logged in successfully.',
            'user' => $this->userPayload($user),
            'token' => $token,
        ]);
    }

    /** المستخدم الحالي (نفس شكل register/login) */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    /** Account settings for any authenticated role */
    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = array_merge(
            self::DEFAULT_ACCOUNT_SETTINGS,
            is_array($user->settings) ? $user->settings : []
        );

        return response()->json([
            'data' => $settings,
        ]);
    }

    /** Update account settings for any authenticated role */
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

        $user = $request->user();
        $current = array_merge(
            self::DEFAULT_ACCOUNT_SETTINGS,
            is_array($user->settings) ? $user->settings : []
        );
        $user->settings = array_merge($current, $validated);
        $user->save();

        return response()->json([
            'message' => 'Settings updated successfully.',
            'data' => $user->settings,
        ]);
    }

    /** تسجيل خروج وحذف التوكن */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'national_id' => $user->national_id,
            'profile_photo_path' => $user->profile_photo_path,
        ];
    }

    /** Update user profile including photo */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        \Log::info('Profile update request:', [
            'has_file' => $request->hasFile('profile_photo'),
            'all_files' => $request->allFiles(),
            'all_data' => $request->all(),
            'content_type' => $request->header('Content-Type'),
            'method' => $request->method(),
        ]);

        if ($request->has('name') && $request->input('name')) {
            $user->name = $request->input('name');
        }
        if ($request->has('email') && $request->input('email')) {
            $user->email = $request->input('email');
        }
        if ($request->has('phone') && $request->input('phone')) {
            $user->phone = $request->input('phone');
        }

        if ($request->hasFile('profile_photo')) {
            $photo = $request->file('profile_photo');
            $path = $photo->store('profile-photos', 'public');
            $user->profile_photo_path = $path;
            \Log::info('Profile photo saved:', ['path' => $path]);
        }

        $user->save();

        \Log::info('User saved:', ['profile_photo_path' => $user->profile_photo_path]);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $this->userPayload($user),
        ]);
    }

    /** Update user password */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        // Verify current password
        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        // Update password
        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * ربط سجلات المستشفى (سجلتها الممرضة) بولي الأمر لو الرقم القومي أو الإيميل متطابق.
     */
    private function linkExistingChildrenToParent(User $user): void
    {
        $query = Child::query()->whereNull('user_id');

        // Build OR conditions: match by national_id OR by parent_email
        $query->where(function ($q) use ($user) {
            if ($user->national_id) {
                $q->orWhere('father_national_id', $user->national_id);
            }
            if ($user->email) {
                $q->orWhere('parent_email', $user->email);
            }
        });

        $query->update([
            'user_id'      => $user->id,
            'parent_email' => $user->email,
            'is_linked'    => true,
        ]);
    }
}
