<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class MobileAuthController extends Controller
{
    private const DEFAULT_SETTINGS = [
        'language' => 'en',
        'notifications' => true,
        'email_alerts' => false,
        'two_factor' => false,
        'login_alerts' => false,
        'session_timeout' => 30,
    ];

    /**
     * Mobile login — supports email or phone + password.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'password' => 'required|string',
        ]);

        if (!$request->email && !$request->phone) {
            return response()->json([
                'status' => false,
                'message' => 'Email or phone is required.',
            ], 422);
        }

        $throttleKey = Str::lower($request->email ?? $request->phone) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'status' => false,
                'message' => "Too many attempts. Try again in {$seconds}s.",
            ], 429);
        }

        $query = User::query();
        if ($request->email) {
            $query->where('email', $request->email);
        } elseif ($request->phone) {
            $query->where('phone', $request->phone);
        }

        $user = $query->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // Link existing children on login for parent role
        if ($user->role === 'user') {
            $this->linkExistingChildren($user);
        }

        RateLimiter::clear($throttleKey);
        $token = $user->createToken('mobile_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Mobile register — creates a parent account with full fields.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:15',
            'national_id' => 'nullable|string|size:14',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone ?? null,
            'national_id' => $request->national_id ?? null,
            'password' => Hash::make($request->password),
            'role' => 'user',
        ]);

        // Link existing children to new parent
        $this->linkExistingChildren($user);

        $token = $user->createToken('mobile_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Account created successfully.',
            'token' => $token,
            'user' => $this->userPayload($user),
        ], 201);
    }

    /**
     * Mobile logout.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Get authenticated user profile.
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'user' => $this->userPayload($request->user()),
        ]);
    }

    /**
     * Update profile (name, phone, photo).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:15',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if (!empty($validated['name'])) {
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
            'status' => true,
            'message' => 'Profile updated successfully.',
            'user' => $this->userPayload($user),
        ]);
    }

    /**
     * Change password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * Link hospital-registered children to parent by national_id or email.
     */
    private function linkExistingChildren(User $user): void
    {
        $query = Child::query()->whereNull('user_id');

        $query->where(function ($q) use ($user) {
            if ($user->national_id) {
                $q->orWhere('father_national_id', $user->national_id);
            }
            if ($user->email) {
                $q->orWhere('parent_email', $user->email);
            }
        });

        $query->update([
            'user_id' => $user->id,
            'parent_email' => $user->email,
            'is_linked' => true,
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
}
