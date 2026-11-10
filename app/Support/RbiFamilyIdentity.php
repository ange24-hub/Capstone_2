<?php

namespace App\Support;

class RbiFamilyIdentity
{
    public static function key(array $row, int $fallback = 0): string
    {
        if (filled($row['household_id'] ?? null)) return 'household:'.$row['household_id'];
        if (filled($row['household_head'] ?? null)) return 'head:'.mb_strtolower(trim($row['household_head']));
        if (filled($row['household_number'] ?? null)) return 'number:'.trim($row['household_number']);
        if (filled($row['family_key'] ?? null)) return 'family:'.$row['family_key'];
        return 'unnamed:'.$fallback;
    }
}
