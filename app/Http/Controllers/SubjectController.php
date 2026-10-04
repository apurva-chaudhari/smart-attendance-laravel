<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    public function index()
    {
        $subjects = Subject::with('teacher')
            ->withCount('attendances')
            ->orderBy('name')
            ->get();

        return view('subjects.index', compact('subjects'));
    }

    public function create()
    {
        return view('subjects.create', ['teachers' => $this->teachers()]);
    }

    public function store(Request $request)
    {
        Subject::create($this->validated($request));

        return redirect()->route('subjects.index')
            ->with('success', 'Subject added successfully.');
    }

    public function edit(Subject $subject)
    {
        return view('subjects.edit', [
            'subject'  => $subject,
            'teachers' => $this->teachers(),
        ]);
    }

    public function update(Request $request, Subject $subject)
    {
        $subject->update($this->validated($request, $subject));

        return redirect()->route('subjects.index')
            ->with('success', 'Subject updated successfully.');
    }

    public function destroy(Subject $subject)
    {
        if ($subject->attendances()->exists()) {
            return redirect()->route('subjects.index')
                ->with('error', 'Cannot delete "' . $subject->name . '": attendance has already been recorded for it.');
        }

        $subject->delete();

        return redirect()->route('subjects.index')
            ->with('success', 'Subject deleted successfully.');
    }

    private function teachers()
    {
        return User::where('role', User::ROLE_SUBJECT_TEACHER)->orderBy('name')->get();
    }

    private function validated(Request $request, ?Subject $subject = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('subjects', 'code')->ignore($subject?->id),
            ],
            // Must be an existing SUBJECT TEACHER (not the class teacher, not a random id).
            'teacher_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', User::ROLE_SUBJECT_TEACHER),
            ],
        ]);
    }
}
