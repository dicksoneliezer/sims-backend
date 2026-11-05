<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\User;
use App\Services\SchoolContext;
use App\Services\SchoolTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::saving(function (Model $model): void {
            $context = app(SchoolContext::class);
            if (! $model->exists) {
                if ($model instanceof User && $model->role === 'system_admin') {
                    $model->school_id = null;

                    return;
                }
                if ($context->id() === null) {
                    throw ValidationException::withMessages(['school_id' => 'A school context is required.']);
                }
                if ($model->school_id !== null && (int) $model->school_id !== $context->id()) {
                    throw ValidationException::withMessages(['school_id' => 'School ownership cannot be supplied or changed.']);
                }
                $model->school_id = $context->id();
            }

            if ($model instanceof User && $model->role === 'system_admin' && $model->school_id === null) {
                return;
            }
            if ($context->id() === null || (int) $model->school_id !== $context->id() || ($model->exists && $model->isDirty('school_id'))) {
                throw ValidationException::withMessages(['school_id' => 'School ownership cannot be supplied or changed.']);
            }

            foreach (SchoolTables::foreignKeys($model->getTable()) as $foreignKey) {
                if (! SchoolTables::contains($foreignKey['foreign_table']) || count($foreignKey['columns']) !== 1) {
                    continue;
                }
                $column = $foreignKey['columns'][0];
                $value = $model->getAttribute($column);
                if ($value !== null && (! $model->exists || $model->isDirty($column))) {
                    $valid = DB::table($foreignKey['foreign_table'])
                        ->where($foreignKey['foreign_columns'][0], $value)
                        ->where('school_id', $context->id())->exists();
                    if (! $valid) {
                        throw ValidationException::withMessages([$column => 'The selected record does not belong to your school.']);
                    }
                }
            }
        });

        static::deleting(function (Model $model): void {
            if (! ($model instanceof User && $model->role === 'system_admin' && $model->school_id === null)
                && (app(SchoolContext::class)->id() === null || (int) $model->school_id !== app(SchoolContext::class)->id())) {
                throw ValidationException::withMessages(['school_id' => 'The record does not belong to your school.']);
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
