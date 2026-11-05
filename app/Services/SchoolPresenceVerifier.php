<?php

namespace App\Services;

use Illuminate\Validation\DatabasePresenceVerifier;

class SchoolPresenceVerifier extends DatabasePresenceVerifier
{
    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = []): int
    {
        if (SchoolTables::contains($collection) && ! ($collection === 'users' && $idColumn !== null)) {
            $extra['school_id'] = app(SchoolContext::class)->id() ?? -1;
        }

        return parent::getCount($collection, $column, $value, $excludeId, $idColumn, $extra);
    }

    public function getMultiCount($collection, $column, array $values, array $extra = []): int
    {
        if (SchoolTables::contains($collection)) {
            $extra['school_id'] = app(SchoolContext::class)->id() ?? -1;
        }

        return parent::getMultiCount($collection, $column, $values, $extra);
    }
}
