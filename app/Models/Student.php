<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Services\SchoolContext;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected $table = 'students';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'student_number',
        'full_name',
        'class',
        'class_id',
        'school_level',
        'prem_number',
        'psle_number',
        'index_number',
        'parent_phone',
        'email',
        'residence',
        'gender',
        'date_of_birth',
        'opening_balance',
        'as_of_date',
        'photo',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
        'opening_balance' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'opening_balance' => 'decimal:2',
            'date_of_birth' => 'date',
            'as_of_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_students', 'student_id', 'parent_id')
            ->using(ParentStudent::class)
            ->withPivotValue('school_id', $this->school_id ?? app(SchoolContext::class)->id() ?? -1)
            ->withPivot('relationship', 'is_primary');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function examResults(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }

    public function behaviorRecords(): HasMany
    {
        return $this->hasMany(BehaviorRecord::class);
    }

    public function teacherNotes(): HasMany
    {
        return $this->hasMany(TeacherNote::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StudentPayment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // =========================================
    // NYONGEZA KWA MATRON
    // =========================================

    public function bedAssignments(): HasMany
    {
        return $this->hasMany(BedAssignment::class);
    }

    public function currentBed(): HasOne
    {
        return $this->hasOne(BedAssignment::class)->whereNull('vacated_at');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(StudentLeave::class);
    }

    public function sickBayRecords(): HasMany
    {
        return $this->hasMany(SickBay::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
