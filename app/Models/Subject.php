<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLawyer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory, BelongsToLawyer;

    protected $fillable = [
        'lawyer_id',
        'document_type',
        'document_number',
        'full_name',
        'company_name',
        'issuance_date',
    ];

    protected function casts(): array
    {
        return [
            'issuance_date' => 'date',
        ];
    }

    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lawyer_id');
    }

    public function consultationRequests(): HasMany
    {
        return $this->hasMany(ConsultationRequest::class);
    }
}