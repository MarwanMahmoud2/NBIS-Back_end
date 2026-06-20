<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FootprintAiService
{
    public function identify(UploadedFile $image): array
    {
        $baseUrl = rtrim((string) env('FOOTPRINT_AI_URL', ''), '/');

        if ($baseUrl === '') {
            return [
                'status' => 'ai_unavailable',
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
                'status' => 'ai_unavailable',
                'message' => 'AI service is not reachable.',
            ];
        }

        if (! $response->successful()) {
            return [
                'status' => 'ai_unavailable',
                'message' => $response->json('detail') ?? 'AI service error.',
            ];
        }

        $json = $response->json();
        $status = strtoupper((string) ($json['status'] ?? ''));

        if ($status === 'MATCH') {
            return [
                'status' => 'match_found',
                'confidence_tier' => $this->confidenceTier((float) ($json['score'] ?? 0)),
                'score' => (float) ($json['score'] ?? 0),
                'ai_child' => $json['database']['child'] ?? ($json['database'] ?? null),
                'ai_parents' => $json['database']['parents'] ?? null,
                'ai_hospital' => $json['database']['hospital'] ?? null,
            ];
        }

        if ($status === 'NO_MATCH') {
            return [
                'status' => 'no_match',
                'message' => 'No matching records found for this fingerprint.',
            ];
        }

        return [
            'status' => 'ai_unavailable',
            'message' => 'Unexpected AI response.',
        ];
    }

    private function confidenceTier(float $score): string
    {
        return match (true) {
            $score >= 0.85 => 'high',
            $score >= 0.65 => 'medium',
            default => 'low',
        };
    }
}