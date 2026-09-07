<?php

namespace App\Support;

class RegistryRemarks
{
    private const METADATA = '/\[(?:Source(?:\s[^\]:]*)?:|Also\s|Original household:|Duplicate source:|Household assignment pending confirmation;)[^\]]*\]/i';

    public static function display(?string $value): string
    {
        return trim(preg_replace(self::METADATA, '', $value ?? ''));
    }

    public static function preserve(?string $value, ?string $original): ?string
    {
        preg_match_all(self::METADATA, $original ?? '', $matches);
        foreach (array_unique($matches[0]) as $note) {
            if (! str_contains($value ?? '', $note)) $value = trim(($value ?? '').' '.$note);
        }
        return $value;
    }
}
