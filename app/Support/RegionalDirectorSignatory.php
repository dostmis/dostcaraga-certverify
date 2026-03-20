<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class RegionalDirectorSignatory
{
    public const KEY_ENABLED = 'cert_rd_esign_enabled';
    public const KEY_PATH = 'cert_rd_esign_path';

    public static function enabled(): bool
    {
        $stored = static::trimmedSetting(static::KEY_ENABLED);
        if ($stored !== null) {
            return filter_var($stored, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $stored;
        }

        $envValue = filter_var(
            (string) env('CERT_RD_ESIGN_ENABLED', 'true'),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        return $envValue ?? true;
    }

    public static function configuredPath(): ?string
    {
        $stored = static::trimmedSetting(static::KEY_PATH);
        if ($stored !== null) {
            return $stored;
        }

        $envPath = trim((string) env('CERT_RD_ESIGN_PATH', 'images/regional-director-signature.png'));

        return $envPath !== '' ? $envPath : null;
    }

    public static function resolvedPath(): ?string
    {
        if (!static::enabled()) {
            return null;
        }

        $rawPath = static::configuredPath();
        if ($rawPath === null) {
            return null;
        }

        $trimmedPath = ltrim($rawPath, '/');
        $candidates = [];

        if (Storage::disk('public')->exists($trimmedPath)) {
            $candidates[] = Storage::disk('public')->path($trimmedPath);
        }

        if (str_starts_with($rawPath, '/')) {
            $candidates[] = $rawPath;
        }

        $candidates[] = public_path($trimmedPath);
        $candidates[] = storage_path('app/public/' . $trimmedPath);
        $candidates[] = storage_path('app/private/' . $trimmedPath);
        $candidates[] = base_path($rawPath);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public static function publicUrl(): ?string
    {
        if (!static::enabled()) {
            return null;
        }

        $rawPath = static::configuredPath();
        if ($rawPath === null) {
            return null;
        }

        $trimmedPath = ltrim($rawPath, '/');
        if (Storage::disk('public')->exists($trimmedPath)) {
            return Storage::disk('public')->url($trimmedPath);
        }

        if (is_file(public_path($trimmedPath))) {
            return asset($trimmedPath);
        }

        return null;
    }

    public static function viewData(): array
    {
        return [
            'enabled' => static::enabled(),
            'image_url' => static::publicUrl(),
            'has_image' => static::resolvedPath() !== null,
        ];
    }

    private static function trimmedSetting(string $key): ?string
    {
        $value = Setting::getValue($key);
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
