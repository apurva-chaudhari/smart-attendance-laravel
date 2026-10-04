<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        $subjects = $user->accessibleSubjects()->with('teacher')->orderBy('name')->get();
        $subjectIds = $subjects->pluck('id')->all();

        // Today's present/absent per subject (one query)
        $todayStats = Attendance::whereIn('subject_id', $subjectIds)
            ->where('date', $today)
            ->selectRaw("subject_id,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent_count")
            ->groupBy('subject_id')
            ->get()
            ->keyBy('subject_id');

        $presentToday = (int) $todayStats->sum('present_count');
        $absentToday = (int) $todayStats->sum('absent_count');
        $totalStudents = Student::count();

        // Students below 75% -- only counting subjects this teacher may see
        $lowAttendance = Student::withCount([
                'attendances as total_classes' => fn ($q) => $q->whereIn('subject_id', $subjectIds),
                'attendances as present_count' => fn ($q) => $q->whereIn('subject_id', $subjectIds)->where('status', 'present'),
            ])
            ->get()
            ->filter(fn ($s) => $s->total_classes > 0)
            ->map(function ($s) {
                $s->percentage = round($s->present_count / $s->total_classes * 100, 1);

                return $s;
            })
            ->filter(fn ($s) => $s->percentage < 75)
            ->sortBy('percentage')
            ->values();

        return view('dashboard', compact(
            'today', 'subjects', 'todayStats', 'totalStudents',
            'presentToday', 'absentToday', 'lowAttendance'
        ));
    }
}
