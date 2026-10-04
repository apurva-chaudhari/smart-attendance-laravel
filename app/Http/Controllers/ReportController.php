<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function attendance(Request $request)
    {
        $user = $request->user();

        $rules = [
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date'],
            'subject_id' => ['nullable', 'integer'],
        ];
        if ($request->filled('from')) {
            $rules['to'][] = 'after_or_equal:from';
        }
        $request->validate($rules);

        $from = $request->query('from');
        $to = $request->query('to');

        $subjects = $user->accessibleSubjects()->orderBy('name')->get();
        $subjectIds = $subjects->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selectedSubject = null;

        if ($request->filled('subject_id')) {
            $selectedId = $request->integer('subject_id');
            abort_unless(in_array($selectedId, $subjectIds, true), 403);
            $subjectIds = [$selectedId];
            $selectedSubject = $subjects->firstWhere('id', $selectedId);
        }

        $scope = function ($query) use ($subjectIds, $from, $to) {
            $query->whereIn('subject_id', $subjectIds);
            if ($from) {
                $query->where('date', '>=', $from);
            }
            if ($to) {
                $query->where('date', '<=', $to);
            }
        };

        $students = Student::withCount([
                'attendances as total_classes' => $scope,
                'attendances as present_count' => function ($query) use ($scope) {
                    $scope($query);
                    $query->where('status', 'present');
                },
            ])
            ->get()
            ->sortBy('roll_no', SORT_NATURAL)
            ->values()
            ->map(function ($student) {
                $student->absent_count = $student->total_classes - $student->present_count;
                $student->percentage = $student->total_classes > 0
                    ? round($student->present_count / $student->total_classes * 100, 1)
                    : 0;

                return $student;
            });

        return view('reports.attendance', compact('students', 'subjects', 'selectedSubject', 'from', 'to'));
    }
}
