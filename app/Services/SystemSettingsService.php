<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;

class SystemSettingsService
{
    private const DEFAULTS = [
        'language' => 'en',
        'notifications' => true,
        'email_alerts' => true,
        'two_factor' => false,
        'login_alerts' => false,
        'session_timeout' => 30,
    ];

    public function getAll(): array
    {
        $rows = SystemSetting::query()->get()->keyBy('key');
        $result = [];

        foreach (self::DEFAULTS as $key => $defaultValue) {
            $row = $rows->get($key);
            $result[$key] = $row ? $this->castValue($row->value, $row->type) : $defaultValue;
        }

        return $result;
    }

    public function update(array $payload, User $actor): array
    {
        $current = $this->getAll();
        $merged = array_merge($current, $payload);

        foreach ($merged as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }

            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $this->encodeValue($value),
                    'type' => $this->resolveType($value),
                    'updated_by' => $actor->id,
                ]
            );
        }

        return $this->getAll();
    }

    private function resolveType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            default => 'string',
        };
    }

    private function encodeValue(mixed $value): string
    {
        return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }

    private function castValue(?string $value, string $type): mixed
    {
        return match ($type) {
            'bool' => (bool) (int) $value,
            'int' => (int) $value,
            default => $value,
        };
    }
}
