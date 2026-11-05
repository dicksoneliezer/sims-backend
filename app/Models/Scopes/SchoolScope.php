<?php

namespace App\Models\Scopes;

use App\Models\User;
use App\Services\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SchoolScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(SchoolContext::class);

        if ($context->id() !== null) {
            $builder->where($model->qualifyColumn('school_id'), $context->id());
        } elseif (! ($model instanceof User && ! $context->resolved())) {
            $builder->whereRaw('1 = 0');
        }
    }
}
