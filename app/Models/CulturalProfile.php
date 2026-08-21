<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CulturalProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'document_type',
        'municipality',
        'state',
        'cultural_areas',
        'legal_profiles',
        'territories',
        'experience_years',
        'preferred_budget_min',
        'preferred_budget_max',
        'audiences',
        'accessibility_experience',
        'profile_complete',
    ];

    protected function casts(): array
    {
        return [
            'cultural_areas' => 'array',
            'legal_profiles' => 'array',
            'territories' => 'array',
            'audiences' => 'array',
            'accessibility_experience' => 'array',
            'preferred_budget_min' => 'decimal:2',
            'preferred_budget_max' => 'decimal:2',
            'profile_complete' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
