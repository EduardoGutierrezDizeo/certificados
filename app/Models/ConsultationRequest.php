<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLawyer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsultationRequest extends Model
{
    use HasFactory, BelongsToLawyer;

    protected $fillable = [
        'lawyer_id',
        'subject_id',
        'status',
    ];

    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lawyer_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function certificateRequests(): HasMany
    {
        return $this->hasMany(CertificateRequest::class);
    }

    public function refreshStatus(): void
    {
        $estados = $this->certificateRequests()->pluck('status');

        if ($estados->contains('pending') || $estados->contains('processing')) {
            $nuevoEstado = 'pending';
        } elseif ($estados->every(fn ($e) => $e === 'success')) {
            $nuevoEstado = 'success';
        } elseif ($estados->every(fn ($e) => $e === 'failed')) {
            $nuevoEstado = 'failed';
        } else {
            $nuevoEstado = 'partial';
        }

        $this->update(['status' => $nuevoEstado]);
    }
}