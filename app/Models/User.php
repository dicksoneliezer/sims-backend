<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Services\SchoolContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'username',
    'password',
    'full_name',
    'email',
    'phone',
    'nin_number',
    'employment_id',
    'role',
    'is_active',
    'last_login',
    'last_ip',
    'profile_pic',
    'login_attempts',
    'locked_until',
])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    use BelongsToSchool;

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLES = [
        'super_admin',
        'system_admin',
        'academic_teacher',
        'teacher',
        'accountant',
        'store_keeper',
        'matron',
        'student',
        'parent',
    ];

    public const MAX_LOGIN_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    public $timestamps = false;

    protected $attributes = [
        'is_active' => true,
        'login_attempts' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_login' => 'datetime',
            'locked_until' => 'datetime',
            'login_attempts' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_students', 'parent_id', 'student_id')
            ->using(ParentStudent::class)
            ->withPivotValue('school_id', $this->school_id ?? app(SchoolContext::class)->id() ?? -1)
            ->withPivot('relationship', 'is_primary');
    }

    public function teacherClasses(): HasMany
    {
        return $this->hasMany(TeacherClass::class, 'teacher_id');
    }

    public function teacherSubjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class, 'teacher_id');
    }

    public function teacherNotes(): HasMany
    {
        return $this->hasMany(TeacherNote::class, 'teacher_id');
    }

    public function teacherAttendances(): HasMany
    {
        return $this->hasMany(TeacherAttendance::class, 'teacher_id');
    }

    public function dashboardPath(): string
    {
        return match ($this->role) {
            'super_admin' => '/admin',
            'system_admin' => '/system',
            'academic_teacher' => '/academic',
            'teacher' => '/teacher',
            'accountant' => '/accountant',
            'store_keeper' => '/storekeeper',
            'matron' => '/matron',
            'student' => '/student',
            'parent' => '/parent',
            default => '/login',
        };
    }
}
