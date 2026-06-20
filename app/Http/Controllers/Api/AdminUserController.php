<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    /**
     * Paginated user listing with search and role filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $perPage = (int) $request->integer('per_page', 50);
        $users = $query->latest()->paginate($perPage);

        $mappedUsers = $users->map(function ($user) {
            return [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'phone'      => $user->phone,
                'role'       => $user->role,
                'status'     => 'active',
                'created_at' => $user->created_at ? $user->created_at->format('Y-m-d') : null,
                'last_login' => $user->updated_at ? $user->updated_at->diffForHumans() : null,
            ];
        });

        return response()->json([
            'data' => $mappedUsers,
        ]);
    }

    /**
     * Create a new user with role assignment.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'phone'    => ['required', 'string', 'max:15'],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['required', 'in:admin,nurse,police,user'],
            'status'   => ['nullable', 'in:active,inactive'],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'phone'    => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
        ]);

        return response()->json([
            'message' => 'User created successfully.',
            'data' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role'  => $user->role,
            ],
        ], 201);
    }

    /**
     * Update user fields (prevents editing own account or other admins).
     */
    public function update(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->id === $user->id) {
            return response()->json([
                'message' => 'You cannot edit the currently logged-in account from this screen.',
            ], 403);
        }

        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Admins cannot edit other admin accounts.',
            ], 403);
        }

        $validated = $request->validate([
            'name'     => ['nullable', 'string', 'max:255'],
            'email'    => ['nullable', 'email', 'unique:users,email,' . $user->id],
            'phone'    => ['nullable', 'string', 'max:15'],
            'role'     => ['nullable', 'in:admin,nurse,police,user'],
            'status'   => ['nullable', 'in:active,inactive'],
        ]);

        if (isset($validated['name']))     $user->name     = $validated['name'];
        if (isset($validated['email']))    $user->email    = $validated['email'];
        if (isset($validated['phone']))    $user->phone    = $validated['phone'];
        if (isset($validated['role']))     $user->role     = $validated['role'];
        if (isset($validated['password'])) $user->password = Hash::make($validated['password']);

        $user->save();

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role'  => $user->role,
            ],
        ]);
    }

    /**
     * Delete user (prevents deleting own account or other admins).
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete the currently logged-in account.',
            ], 403);
        }

        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Admins cannot delete other admin accounts.',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }
}
