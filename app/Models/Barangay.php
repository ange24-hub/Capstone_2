<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barangay extends Model
{
    use HasFactory;

    public const MUNICIPALITY = 'Tomas Oppus';

    public const TOMAS_OPPUS_BARANGAYS = [
        'Anahawan',
        'Banday',
        'Biasong',
        'Bogo',
        'Cabascan',
        'Camansi',
        'Cambite',
        'Canlupao',
        'Carnaga',
        'Cawayan',
        'Higosoan',
        'Hinagtikan',
        'Hinapo',
        'Hugpa',
        'Iniguihan',
        'Looc',
        'Luan',
        'Maanyag',
        'Mag-ata',
        'Mapgap',
        'Maslog',
        'Punong',
        'Rizal',
        'San Agustin',
        'San Antonio',
        'San Isidro',
        'San Miguel',
        'San Roque',
        'Tinago',
    ];

    public const LOCAL_NAMES = [
        'Banday' => 'Poblacion · Seat of Government',
        'Bogo' => 'Poblacion',
        'San Agustin' => 'Lotaw',
        'San Antonio' => 'Calayugan',
    ];

    protected $fillable = [
        'name',
        'municipality',
        'secretary_name',
        'punong_barangay_name',
        'logo_path',
        'gcash_enabled',
        'gcash_merchant_name',
        'gcash_account_identifier',
        'gcash_qr_path',
        'gcash_approved_by',
        'gcash_approved_at',
    ];

    protected $casts = [
        'gcash_enabled' => 'boolean',
        'gcash_approved_at' => 'datetime',
    ];

    public function gcashIsReady(): bool
    {
        return $this->gcash_enabled
            && filled($this->gcash_merchant_name)
            && filled($this->gcash_account_identifier)
            && filled($this->gcash_qr_path);
    }

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

    public function secretaries(): HasMany
    {
        return $this->hasMany(User::class)
            ->where('role', User::ROLE_BARANGAY)
            ->where('approval_status', User::APPROVAL_APPROVED);
    }

    public function localName(): ?string
    {
        return self::LOCAL_NAMES[$this->name] ?? null;
    }
}
