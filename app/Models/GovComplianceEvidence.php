<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovComplianceEvidence extends Model
{
    protected $table = 'gov_compliance_evidences';

    protected $fillable = [
        'gov_compliance_item_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'sha256',
        'size_bytes',
        'uploaded_by_user_id',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GovComplianceItem::class, 'gov_compliance_item_id');
    }
}
