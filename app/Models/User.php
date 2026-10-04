<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// NOTE: 'role' is deliberately NOT fillable, so a request can never
// set its own role through mass assignment.
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_CLASS_TEACHER = 'class_teacher';
    public const ROLE_SUBJECT_TEACHER = 'subject_teacher';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isClassTeacher(): bool
    {
        return $this->role === self::ROLE_CLASS_TEACHER;
    }

    /** Subjects this user is assigned to teach. */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'teacher_id');
    }

    /**
     * The subjects this user may see / mark.
     * Class teacher: every subject. Subject teacher: only their own.
     * Every controller scopes its queries through this one method.
     */
    public function accessibleSubjects(): Builder
    {
        return $this->isClassTeacher()
            ? Subject::query()
            : Subject::where('teacher_id', $this->id);
    }

    public function canAccessSubject(Subject $subject): bool
    {
        return $this->isClassTeacher()
            || (int) $subject->teacher_id === (int) $this->id;
    }
}
