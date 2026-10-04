<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /** History: only rows of subjects the logged-in teacher may access. */
    public function index(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'date'       => 'nullable|date',
            'student_id' => 'nullable|integer',
            'subject_id' => 'nullable|integer',
        ]);

        $subjects = $user->accessibleSubjects()->orderBy('name')->get();
        $students = Student::ordered();

        $query = Attendance::with(['student', 'subject'])
            ->whereIn('subject_id', $subjects->pluck('id'))
            ->orderByDesc('date')
            ->orderBy('subject_id')
            ->orderBy('student_id');

        if ($request->filled('subject_id')) {
            $subjectId = $request->integer('subject_id');
            abort_unless($subjects->contains('id', $subjectId), 403);
            $query->where('subject_id', $subjectId);
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->integer('student_id'));
        }

        if ($request->filled('date')) {
            $query->where('date', Carbon::parse($request->query('date'))->toDateString());
        }

        $attendances = $query->paginate(15)->withQueryString();

        return view('attendance.index', compact('attendances', 'subjects', 'students'));
    }

    public function mark(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'date'       => 'nullable|date',
            'subject_id' => 'nullable|integer',
        ]);

        $subjects = $user->accessibleSubjects()->orderBy('name')->get();

        if ($request->filled('subject_id')) {
            // Missing OR someone else's subject -> same answer: forbidden.
            $subject = $subjects->firstWhere('id', $request->integer('subject_id'));
            abort_if($subject === null, 403);
        } else {
            $subject = $subjects->first();
        }

        $date = $request->filled('date')
            ? Carbon::parse($request->query('date'))->toDateString()
            : now()->toDateString();

        $students = Student::ordered();

        $existing = $subject
            ? Attendance::where('subject_id', $subject->id)->where('date', $date)->pluck('status', 'student_id')
            : collect();

        return view('attendance.mark', compact('subjects', 'subject', 'date', 'students', 'existing'));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'subject_id' => 'required|integer|exists:subjects,id',
            'date'       => 'required|date|before_or_equal:today',   // no future attendance
            'present'    => 'array',
            'present.*'  => 'integer|exists:students,id',
        ]);

        $subject = Subject::findOrFail($data['subject_id']);

        // The important line: a subject teacher can only save for their own subject.
        abort_unless($user->canAccessSubject($subject), 403);

        $date = Carbon::parse($data['date'])->toDateString();
        $presentIds = array_map('intval', $data['present'] ?? []);

        DB::transaction(function () use ($subject, $date, $presentIds) {
            foreach (Student::pluck('id') as $studentId) {
                Attendance::updateOrCreate(
                    ['student_id' => $studentId, 'subject_id' => $subject->id, 'date' => $date],
                    ['status' => in_array((int) $studentId, $presentIds, true) ? 'present' : 'absent']
                );
            }
        });

        return redirect()
            ->route('attendance.mark', ['subject_id' => $subject->id, 'date' => $date])
            ->with('success', 'Attendance saved for ' . $subject->name . ' on ' . $date . '.');
    }
}
