<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_RESIDENT = 'resident';
    public const ROLE_BARANGAY = 'barangay';
    public const ROLE_MUNICIPAL_LGU = 'municipal_lgu';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'barangay_id',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return [
            self::ROLE_RESIDENT => 'Resident',
            self::ROLE_BARANGAY => 'Barangay-level user',
            self::ROLE_MUNICIPAL_LGU => 'Municipal LGU-level user',
        ];
    }

    public function roleLabel(): string
    {
        return self::roleLabels()[$this->role] ?? 'Unknown';
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class, 'resident_id');
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }
}
