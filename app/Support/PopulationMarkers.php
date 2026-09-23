<?php

namespace App\Support;

class PopulationMarkers
{
    public static function has(?string $remarks, string $kind): bool
    {
        $text = RegistryRemarks::display($remarks);
        $marker = match ($kind) {
            'pwd' => '(?:PWD|P\.W\.D\.?|PERSON(?:S)? WITH DISABILIT(?:Y|IES))',
            'indigent' => '(?:INDIGENT(?: RESIDENTS?)?|INDIGENCY)',
            default => '(?:SC|S\.C\.?|SENIOR CITIZEN(?:S)?)',
        };
        // Explicit negative or uncertain labels must not become positive counts.
        if (preg_match('/\b(?:NOT|NON|NO|POSSIBLE|SUSPECTED)\s*[-:]?\s*'.$marker.'\b|\b'.$marker.'\s*[:=\-]?\s*(?:NO|NONE|FALSE|UNKNOWN|UNCONFIRMED)\b|\b'.$marker.'\s*\?/i', $text)) {
            return false;
        }

        return (bool) preg_match('/(?<![A-Z0-9])'.$marker.'(?![A-Z0-9])/i', $text);
    }
}
