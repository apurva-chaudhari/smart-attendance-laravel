<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    // Everyone logged in can LIST students (a subject teacher needs them to mark attendance).
    // create / store / edit / update / destroy are class-teacher only (see routes/web.php).
    public function index()
    {
        $students = Student::ordered();

        return view('students.index', compact('students'));
    }

    public function create()
    {
        return view('students.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'roll_no' => 'required|string|max:50|unique:students,roll_no',
            'course'  => 'required|string|max:255',
            'year'    => 'required|integer|min:1|max:6',
        ]);

        Student::create($data);

        return redirect()->route('students.index')
            ->with('success', 'Student added successfully.');
    }

    public function edit(Student $student)
    {
        return view('students.edit', compact('student'));
    }

    public function update(Request $request, Student $student)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'roll_no' => 'required|string|max:50|unique:students,roll_no,' . $student->id,
            'course'  => 'required|string|max:255',
            'year'    => 'required|integer|min:1|max:6',
        ]);

        $student->update($data);

        return redirect()->route('students.index')
            ->with('success', 'Student updated successfully.');
    }

    public function destroy(Student $student)
    {
        // Attendance rows of this student are deleted too (cascade in the migration).
        $student->delete();

        return redirect()->route('students.index')
            ->with('success', 'Student deleted successfully.');
    }
}
