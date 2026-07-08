<?php

namespace App\Models\Concerns;

use App\Models\Scopes\LawyerScope;
use Illuminate\Support\Facades\Auth;

trait BelongsToLawyer
{
    protected static function bootBelongsToLawyer(): void
    {
        static::addGlobalScope(new LawyerScope);

        static::creating(function ($model) {
            if (empty($model->lawyer_id) && Auth::check()) {
                $model->lawyer_id = Auth::id();
            }
        });
    }
}
