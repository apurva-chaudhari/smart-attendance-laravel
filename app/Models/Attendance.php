<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = ['student_id', 'subject_id', 'date', 'status'];

    // 'date:Y-m-d' keeps the stored value as a plain date on every database
    // (a bare 'date' cast stores "2026-10-03 00:00:00" on SQLite, which
    // breaks equality lookups in tests).
    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
