<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'consultation_request_id',
        'site',
        'status',
        'error_message',
        'pdf_path',
        'pdf_generated_at',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'pdf_generated_at' => 'datetime',
        ];
    }

    public function consultationRequest(): BelongsTo
    {
        return $this->belongsTo(ConsultationRequest::class);
    }
}
