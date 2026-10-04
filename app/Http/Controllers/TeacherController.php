<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Class teacher manages subject-teacher accounts.
 * {teacher} is bound in AppServiceProvider so it only ever resolves subject teachers.
 */
class TeacherController extends Controller
{
    public function index()
    {
        $teachers = User::where('role', User::ROLE_SUBJECT_TEACHER)
            ->with('subjects')
            ->orderBy('name')
            ->get();

        return view('teachers.index', compact('teachers'));
    }

    public function create()
    {
        return view('teachers.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:255',
        ]);

        $teacher = new User($data);                    // name, email, password only
        $teacher->role = User::ROLE_SUBJECT_TEACHER;   // role is never taken from the request
        $teacher->save();

        return redirect()->route('teachers.index')
            ->with('success', 'Teacher account created.');
    }

    public function edit(User $teacher)
    {
        return view('teachers.edit', compact('teacher'));
    }

    public function update(Request $request, User $teacher)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($teacher->id)],
            'password' => 'nullable|string|min:8|max:255',
        ]);

        $teacher->name = $data['name'];
        $teacher->email = $data['email'];

        // Blank password = keep the current one
        if (! empty($data['password'])) {
            $teacher->password = $data['password'];
        }

        $teacher->save();

        return redirect()->route('teachers.index')
            ->with('success', 'Teacher updated.');
    }

    public function destroy(User $teacher)
    {
        // Their subjects stay and become "Unassigned" (nullOnDelete in the migration).
        $teacher->delete();

        return redirect()->route('teachers.index')
            ->with('success', 'Teacher deleted. Their subjects are now unassigned.');
    }
}
