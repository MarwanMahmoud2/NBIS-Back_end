<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Services\ParentChildService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MobileChildController extends Controller
{
    public function __construct(
        private ParentChildService $parentChild,
    ) {}

    /**
     * List all children for the authenticated parent.
     */
    public function index(Request $request): JsonResponse
    {
        $children = $this->parentChild->childrenForParent($request->user());

        return response()->json([
            'status' => true,
            'data' => $children->map(fn(Child $child) => $this->parentChild->childPayload($child)),
        ]);
    }

    /**
     * Get a single child detail.
     */
    public function show(Request $request, Child $child): JsonResponse
    {
        $this->parentChild->assertParentOwnsChild($request->user(), $child);

        return response()->json([
            'status' => true,
            'data' => $this->parentChild->childPayload($child),
        ]);
    }

    /**
     * Register a new child from the parent (full fields, multipart for photos).
     */
    public function registerChild(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'gender' => 'required|in:male,female',
            'birth_date' => 'nullable|date',
            'nfc_tag_id' => 'nullable|string|unique:children,nfc_tag_id',
            'mother_name' => 'nullable|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'father_phone' => 'nullable|string|max:15',
            'notes' => 'nullable|string|max:1000',
            'footprint_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'child_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $user = Auth::user();
        $footprintPath = $request->file('footprint_image')?->store('footprints', 'public');
        $childPhotoPath = $request->file('child_photo')?->store('child_photos', 'public');

        $child = Child::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'gender' => $validated['gender'],
            'birth_date' => $validated['birth_date'] ?? null,
            'nfc_tag_id' => $validated['nfc_tag_id'] ?? null,
            'footprint_path' => $footprintPath,
            'child_photo_path' => $childPhotoPath,
            'mother_name' => $validated['mother_name'] ?? null,
            'father_name' => $validated['father_name'] ?? $user->name,
            'father_phone' => $validated['father_phone'] ?? $user->phone,
            'father_national_id' => $user->national_id,
            'parent_email' => $user->email,
            'is_linked' => true,
            'status' => 'verified',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Child registered successfully.',
            'data' => $this->parentChild->childPayload($child),
        ], 201);
    }

    /**
     * Upload/update child photo.
     */
    public function uploadPhoto(Request $request, Child $child): JsonResponse
    {
        $this->parentChild->assertParentOwnsChild($request->user(), $child);

        $request->validate([
            'child_photo' => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $path = $request->file('child_photo')->store('child_photos', 'public');
        $child->update(['child_photo_path' => $path]);

        return response()->json([
            'status' => true,
            'message' => 'Child photo uploaded successfully.',
            'child_photo_path' => $path,
        ]);
    }

    /**
     * Upload/update footprint image.
     */
    public function uploadFootprint(Request $request, Child $child): JsonResponse
    {
        $this->parentChild->assertParentOwnsChild($request->user(), $child);

        $request->validate([
            'footprint_image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $path = $request->file('footprint_image')->store('footprints', 'public');
        $child->update(['footprint_path' => $path]);

        return response()->json([
            'status' => true,
            'message' => 'Footprint uploaded successfully.',
            'footprint_path' => $path,
        ]);
    }

    /**
     * Search missing children by name (public search for parents).
     */
    public function searchMissing(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'child_id' => 'nullable|string|max:20',
        ]);

        $query = Child::where('status', 'missing');

        if ($request->name) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->child_id) {
            $query->where('child_id', $request->child_id);
        }

        $results = $query->latest()->get()->map(fn(Child $child) => $this->parentChild->childPayload($child));

        return response()->json([
            'status' => true,
            'data' => $results,
        ]);
    }
}
