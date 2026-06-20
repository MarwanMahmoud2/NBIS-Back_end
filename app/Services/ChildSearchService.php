<?php

namespace App\Services;

use App\Models\Child;
use Illuminate\Database\Eloquent\Collection;

/**
 * بحث نصي عن سجلات الأطفال (شرطة / ويب قديم).
 * الواجهة الرئيسية للتطبيق: JSON API + React؛ هذا المنطق مستقل عن Blade.
 */
class ChildSearchService
{
    /** بحث بسيط في حقول الاسم ورقم الأب القومي */
    public function searchByText(?string $query): Collection
    {
        $q = trim((string) $query);
        if ($q === '') {
            return Child::query()->whereRaw('0 = 1')->get();
        }

        return Child::query()
            ->where(function ($builder) use ($q) {
                $builder->where('father_national_id', 'LIKE', "%{$q}%")
                    ->orWhere('father_name', 'LIKE', "%{$q}%")
                    ->orWhere('mother_name', 'LIKE', "%{$q}%")
                    ->orWhere('name', 'LIKE', "%{$q}%");
            })
            ->get();
    }

    /** شكل موحّد للاستجابة JSON (React / موبايل) */
    public function toSearchResultRows(Collection $children): array
    {
        return $children->map(function (Child $child) {
            return [
                'id' => $child->id,
                'child_id' => $child->child_id,
                'name' => $child->name,
                'mother_name' => $child->mother_name,
                'father_name' => $child->father_name,
                'father_phone' => $child->father_phone,
                'father_national_id' => $child->father_national_id,
                'status' => $child->status,
                'footprint_url' => $child->footprint_url,
            ];
        })->values()->all();
    }
}
