<?php

namespace App\Rules;

use App\Models\Scopes\SchoolScope;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueLoginIdentifier implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreId = null) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $exists = User::withoutGlobalScope(SchoolScope::class)
            ->when($this->ignoreId !== null, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->where(fn ($query) => $query->where('username', $value)->orWhere('email', $value)->orWhere('phone', $value))
            ->exists();
        if ($exists) {
            $fail('This login identifier is already in use.');
        }
    }
}
