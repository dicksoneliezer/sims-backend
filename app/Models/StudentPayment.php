<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StudentPayment extends Model
{
    use BelongsToSchool;

    protected $table = 'student_payments';

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'invoice_id',
        'control_number',
        'amount',
        'description',
        'status',
        'transaction_ref',
        'payment_method',
        'created_at',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public static function generateControlNumber(): string
    {
        do {
            $controlNumber = 'CTRL-'.now()->format('Ymd').'-'.strtoupper(Str::random(16));
        } while (self::withoutGlobalScope(SchoolScope::class)->where('control_number', $controlNumber)->exists());

        return $controlNumber;
    }
}
