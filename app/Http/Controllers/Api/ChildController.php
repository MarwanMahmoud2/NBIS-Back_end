<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use App\Services\ChildRegistrationService;
use App\Services\ChildSearchService;
use App\Services\FootprintAiService;
use App\Services\FootprintValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ChildController extends Controller
{
    public function __construct(
        private ChildRegistrationService $childRegistration,
        private ChildSearchService $childSearch,
        private FootprintAiService $footprintAi,
        private FootprintValidationService $footprintValidation,
    ) {
    }

    /**
     * List all children (nurse/admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Child::with('parent');

        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('mother_name', 'like', "%{$search}%")
                  ->orWhere('father_name', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $children = $query->latest()->get()->map(function ($child) {
            return [
                'id' => $child->id,
                'child_id' => $child->child_id,
                'name' => $child->name,
                'mother_name' => $child->mother_name,
                'father_name' => $child->father_name,
                'father_phone' => $child->father_phone,
                'father_national_id' => $child->father_national_id,
                'gender' => $child->gender,
                'birth_date' => $child->birth_date,
                'estimated_age' => $child->estimated_age,
                'nfc_tag_id' => $child->nfc_tag_id,
                'found_location' => $child->found_location,
                'date_found' => $child->date_found,
                'notes' => $child->notes,
                'status' => $child->status,
                'parent_email' => $child->parent_email,
                'is_linked' => $child->is_linked,
                'created_at' => $child->created_at ? $child->created_at->diffForHumans() : null,
                'child_photo_path' => $child->child_photo_path,
                'footprint_path' => $child->footprint_path,
                'parent' => $child->parent ? [
                    'id' => $child->parent->id,
                    'name' => $child->parent->name,
                    'phone' => $child->parent->phone,
                ] : null,
            ];
        });

        return response()->json([
            'data' => $children,
        ]);
    }

    /**
     * Show single child details (nurse/admin)
     */
    public function show(Child $child): JsonResponse
    {
        $child->load('parent');

        return response()->json([
            'data' => [
                'id' => $child->id,
                'child_id' => $child->child_id,
                'name' => $child->name,
                'mother_name' => $child->mother_name,
                'father_name' => $child->father_name,
                'father_phone' => $child->father_phone,
                'father_national_id' => $child->father_national_id,
                'gender' => $child->gender,
                'birth_date' => $child->birth_date,
                'estimated_age' => $child->estimated_age,
                'nfc_tag_id' => $child->nfc_tag_id,
                'found_location' => $child->found_location,
                'date_found' => $child->date_found,
                'notes' => $child->notes,
                'status' => $child->status,
                'parent_email' => $child->parent_email,
                'is_linked' => $child->is_linked,
                'created_at' => $child->created_at ? $child->created_at->diffForHumans() : null,
                'child_photo_path' => $child->child_photo_path,
                'footprint_path' => $child->footprint_path,
                'parent' => $child->parent ? [
                    'id' => $child->parent->id,
                    'name' => $child->parent->name,
                    'phone' => $child->parent->phone,
                ] : null,
            ],
        ]);
    }

    /**
     * تسجيل طفل جديد (ممرضة / أدمن) — multipart.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'father_phone' => ['nullable', 'string', 'max:15'],
            'father_national_id' => ['nullable', 'string', 'size:14'],
            'gender' => ['nullable', 'in:male,female'],
            'birth_date' => ['nullable', 'date'],
            'estimated_age' => ['nullable', 'string', 'max:50'],
            'found_location' => ['nullable', 'string', 'max:255'],
            'date_found' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'nfc_tag_id' => ['nullable', 'string', 'unique:children,nfc_tag_id'],
            'footprint_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
            'child_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
        ]);

        $child = $this->childRegistration->register(
            $validated,
            (int) $request->user()->id,
            $request->file('footprint_image'),
            $request->file('child_photo'),
        );

        return response()->json([
            'message' => 'Child registered successfully.',
            'data' => $this->childRegistration->registrationPayload($child),
        ], 201);
    }

    /**
     * تسجيل طفل جديد من قبل ولي الأمر (موبايل) — multipart.
     */
    public function storeByParent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'birth_date' => ['nullable', 'date'],
            'nfc_tag_id' => ['required', 'string', 'unique:children,nfc_tag_id'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'father_phone' => ['nullable', 'string', 'max:15'],
            'footprint_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
            'child_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
        ]);

        $user = Auth::user();
        $imagePath = $request->file('footprint_image')?->store('footprints', 'public');
        $childPhotoPath = $request->file('child_photo')?->store('child_photos', 'public');

        $child = Child::create([
            'user_id'             => $user->id,
            'name'                => $validated['name'],
            'gender'              => $validated['gender'],
            'birth_date'          => $validated['birth_date'] ?? null,
            'nfc_tag_id'          => $validated['nfc_tag_id'],
            'footprint_path'      => $imagePath,
            'child_photo_path'    => $childPhotoPath,
            'mother_name'         => $validated['mother_name'] ?? null,
            'father_name'         => $validated['father_name'] ?? $user->name,
            'father_phone'        => $validated['father_phone'] ?? $user->phone,
            'father_national_id'  => $user->national_id,
            'parent_email'        => $user->email,
            'is_linked'           => true,
            'status'              => 'verified',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Child registered successfully',
            'data' => $child
        ], 201);
    }

    /**
     * بحث نصي عن سجلات الأطفال (شرطة / أدمن).
     */
    public function textSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search_query' => ['nullable', 'string', 'max:255'],
        ]);

        $results = $this->childSearch->searchByText($validated['search_query'] ?? null);

        return response()->json([
            'data' => $this->childSearch->toSearchResultRows($results),
        ]);
    }

    /**
     * البحث عن طفل مفقود عن طريق البصمة (شرطة / أدمن) — AI phase.
     */
    public function searchByFootprint(Request $request): JsonResponse
    {
        $request->validate([
            'fingerprint_image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $result = $this->footprintAi->identify($request->file('fingerprint_image'));

        if ($result['status'] === 'ai_unavailable') {
            return response()->json([
                'status' => 'ai_unavailable',
                'message' => $result['message'] ?? 'AI service is not reachable.',
            ], 503);
        }

        if ($result['status'] === 'match_found') {
            return response()->json([
                'status' => 'match_found',
                'confidence_tier' => $result['confidence_tier'],
                'score' => $result['score'],
                'data' => [
                    'child' => $result['ai_child'],
                    'parents' => $result['ai_parents'],
                    'hospital' => $result['ai_hospital'],
                ],
            ]);
        }

        return response()->json([
            'status' => 'no_match',
            'message' => $result['message'] ?? 'No matching records found for this fingerprint.',
        ], 404);
    }

    /**
     * التحقق من البصمة — AI phase.
     */
    public function validateFootprint(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fingerprint_image' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
            'child_id' => ['nullable', 'integer', 'exists:children,id'],
        ]);

        $result = $this->footprintValidation->validate(
            $request->file('fingerprint_image'),
            isset($validated['child_id']) ? (int) $validated['child_id'] : null,
        );

        $statusCode = match ($result['reason'] ?? '') {
            'ai_unavailable' => 503,
            'verified' => 200,
            default => 422,
        };

        return response()->json($result, $statusCode);
    }
}
