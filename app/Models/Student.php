<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Student extends Model
{
    protected $fillable = ['name', 'roll_no', 'course', 'year'];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * All students sorted by roll number the way humans expect (1, 2, 10),
     * not the way a string column sorts (1, 10, 2).
     */
    public static function ordered(): Collection
    {
        return static::all()->sortBy('roll_no', SORT_NATURAL)->values();
    }
}
