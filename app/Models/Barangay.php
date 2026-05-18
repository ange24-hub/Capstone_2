<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'municipality',
    ];

    public function households(): HasMany
    {
        return $this->hasMany(Household::class);
    }

    public function inhabitants(): HasMany
    {
        return $this->hasMany(Inhabitant::class);
    }

    public function migrationRecords(): HasMany
    {
        return $this->hasMany(MigrationRecord::class);
    }
}
