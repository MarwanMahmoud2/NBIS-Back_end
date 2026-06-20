<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use App\Services\ParentChildService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const ALLOWED_ROLES = ['user', 'nurse', 'police', 'admin'];

    public function __construct(
        private ParentChildService $parentChild,
    ) {}

    /**
     * إنشاء حساب جديد (web + mobile).
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'phone'       => $validated['phone'] ?? null,
            'password'    => Hash::make($validated['password']),
            'national_id' => $validated['national_id'] ?? null,
            'role'        => 'user',
        ]);

        $this->parentChild->linkExistingChildrenToParent($user);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully.',
            'user'    => $this->userPayload($user),
            'token'   => $token,
        ], 201);
    }

    /**
     * تسجيل دخول — جميع الأدوار (React + mobile).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $throttleKey = Str::lower($validated['email'] ?? $validated['phone'] ?? '') . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Too many login attempts. Try again in {$seconds} seconds.",
            ], 429);
        }

        // Support login by email or phone
        $query = User::query();
        if (! empty($validated['email'])) {
            $query->where('email', $validated['email']);
        } elseif (! empty($validated['phone'])) {
            $query->where('phone', $validated['phone']);
        }
        $user = $query->first();

        $password = $validated['password'];

        if (! $user || ! Hash::check($password, $user->password)) {
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
            $this->parentChild->linkExistingChildrenToParent($user);
        }

        RateLimiter::clear($throttleKey);
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Logged in successfully.',
            'user'    => $this->userPayload($user),
            'token'   => $token,
        ]);
    }

    /**
     * Authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    /**
     * Account settings for any authenticated role.
     */
    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = array_merge(
            User::DEFAULT_SETTINGS,
            is_array($user->settings) ? $user->settings : [],
        );

        return response()->json([
            'data' => $settings,
        ]);
    }

    /**
     * Update account settings for any authenticated role.
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

        $user = $request->user();
        $current = array_merge(
            User::DEFAULT_SETTINGS,
            is_array($user->settings) ? $user->settings : [],
        );
        $user->settings = array_merge($current, $validated);
        $user->save();

        return response()->json([
            'message' => 'Settings updated successfully.',
            'data'    => $user->settings,
        ]);
    }

    /**
     * تسجيل خروج وحذف التوكن.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Update user profile including photo.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => ['nullable', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:15'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ]);

        $user = $request->user();

        if (! empty($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (isset($validated['phone'])) {
            $user->phone = $validated['phone'];
        }

        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $user->profile_photo_path = $path;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user'    => $this->userPayload($user),
        ]);
    }

    /**
     * Update user password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id'                 => $user->id,
            'name'               => $user->name,
            'email'              => $user->email,
            'phone'              => $user->phone,
            'role'               => $user->role,
            'national_id'        => $user->national_id,
            'profile_photo_path' => $user->profile_photo_path,
        ];
    }
}
