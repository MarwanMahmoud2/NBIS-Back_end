<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class FootprintValidationService
{
    public function validate(UploadedFile $image, ?int $childId = null): array
    {
        // أيضاً هنا يتم الاعتماد على متغير البيئة
        $baseUrl = rtrim((string) env('FOOTPRINT_AI_URL', ''), '/');

        if ($baseUrl === '') {
            return [
                'reason' => 'ai_unavailable',
                'message' => 'FOOTPRINT_AI_URL is not configured.',
            ];
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->attach('file', file_get_contents($image->getRealPath()), $image->getClientOriginalName())
                ->post($baseUrl . '/identify');
        } catch (\Throwable $e) {
            return [
                'reason' => 'ai_unavailable',
                'message' => 'AI service is not reachable.',
            ];
        }

        if (! $response->successful()) {
            return [
                'reason' => 'ai_unavailable',
                'message' => $response->json('detail') ?? 'AI service error.',
            ];
        }

        $json = $response->json();
        $status = strtoupper((string) ($json['status'] ?? ''));

        if ($status === 'MATCH') {
            return [
                'reason' => 'verified',
                'message' => 'Match found.',
                'score' => (float) ($json['score'] ?? 0),
                'subject_id' => $json['subject_id'] ?? null,
                'database' => $json['database'] ?? null,
            ];
        }

        if ($status === 'NO_MATCH') {
            return [
                'reason' => 'not_found',
                'message' => 'No match found.',
            ];
        }

        return [
            'reason' => 'ai_unavailable',
            'message' => 'Unexpected AI response.',
        ];
    }
}