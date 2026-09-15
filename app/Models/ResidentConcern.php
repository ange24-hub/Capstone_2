<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class ResidentConcern extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['ai_suggested_at' => 'datetime', 'version' => 'integer'];

    public static function categories(): array
    {
        return ['sanitation' => 'Waste & sanitation', 'roads' => 'Roads & drainage',
            'water' => 'Water supply', 'lighting' => 'Street lighting',
            'services' => 'Barangay services', 'community' => 'Community concerns', 'other' => 'Other / needs review'];
    }

    public static function statuses(): array
    {
        return ['submitted' => 'Submitted', 'under_review' => 'Under review', 'in_progress' => 'In progress',
            'resolved' => 'Resolved', 'closed' => 'Closed'];
    }

    public function allowedStatuses(): array
    {
        $next = match ($this->status) {
            'submitted' => ['under_review', 'closed'],
            'under_review' => ['in_progress', 'resolved', 'closed'],
            'in_progress' => ['under_review', 'resolved', 'closed'],
            'resolved' => ['under_review', 'closed'],
            'closed' => ['under_review'],
            default => [],
        };
        return array_intersect_key(self::statuses(), array_flip([$this->status, ...$next]));
    }

    public function resident(): BelongsTo { return $this->belongsTo(User::class, 'resident_id'); }
    public function barangay(): BelongsTo { return $this->belongsTo(Barangay::class); }
    public function updates(): HasMany { return $this->hasMany(ConcernUpdate::class); }
}
