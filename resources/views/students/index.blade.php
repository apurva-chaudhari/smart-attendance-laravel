@extends('layout')

@section('content')
@php $isClassTeacher = auth()->user()->isClassTeacher(); @endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Students</h3>
    @if ($isClassTeacher)
        <a href="{{ route('students.create') }}" class="btn btn-primary">Add Student</a>
    @endif
</div>

<div class="card"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead class="table-dark">
        <tr>
            <th>Roll No</th>
            <th>Name</th>
            <th>Course</th>
            <th>Year</th>
            @if ($isClassTeacher)<th width="180">Actions</th>@endif
        </tr>
    </thead>
    <tbody>
        @forelse ($students as $student)
            <tr>
                <td>{{ $student->roll_no }}</td>
                <td>{{ $student->name }}</td>
                <td>{{ $student->course }}</td>
                <td>{{ $student->year }}</td>
                @if ($isClassTeacher)
                    <td>
                        <a href="{{ route('students.edit', $student) }}" class="btn btn-sm btn-warning">Edit</a>
                        <form action="{{ route('students.destroy', $student) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Delete this student and ALL their attendance records?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                @endif
            </tr>
        @empty
            <tr><td colspan="{{ $isClassTeacher ? 5 : 4 }}" class="text-center">No students yet.</td></tr>
        @endforelse
    </tbody>
</table>
</div></div>
@endsection
