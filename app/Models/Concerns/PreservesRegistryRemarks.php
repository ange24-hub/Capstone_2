<?php

namespace App\Models\Concerns;

use App\Support\RegistryRemarks;

trait PreservesRegistryRemarks
{
    public function setRemarksAttribute(?string $value): void
    {
        $this->attributes['remarks'] = RegistryRemarks::preserve($value, $this->attributes['remarks'] ?? null);
    }
}
