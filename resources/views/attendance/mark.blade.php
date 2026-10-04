@extends('layout')

@section('content')
<h3>Mark Attendance</h3>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($subjects->isEmpty())
    <div class="alert alert-warning">
        @if (auth()->user()->isClassTeacher())
            No subjects exist yet. <a href="{{ route('subjects.create') }}">Add a subject</a> first.
        @else
            No subjects are assigned to you yet. Please ask the class teacher to assign one.
        @endif
    </div>
@else
    <form method="GET" action="{{ route('attendance.mark') }}" class="row g-2 mb-3">
        <div class="col-auto">
            <select name="subject_id" class="form-select" onchange="this.form.submit()">
                @foreach ($subjects as $s)
                    <option value="{{ $s->id }}" @selected($subject && $subject->id == $s->id)>{{ $s->name }} ({{ $s->code }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <input type="date" name="date" value="{{ $date }}" max="{{ now()->toDateString() }}" class="form-control">
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-primary">Load</button>
        </div>
    </form>

    <form method="POST" action="{{ route('attendance.store') }}">
        @csrf
        <input type="hidden" name="subject_id" value="{{ $subject->id }}">
        <input type="hidden" name="date" value="{{ $date }}">

        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">{{ $subject->name }} &middot; {{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}</div>
            <div>
                <button type="button" class="btn btn-sm btn-outline-success"
                        onclick="document.querySelectorAll('.att-box').forEach(c => c.checked = true)">All present</button>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="document.querySelectorAll('.att-box').forEach(c => c.checked = false)">All absent</button>
            </div>
        </div>

        <div class="card mb-3"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr><th>Roll No</th><th>Student</th><th width="150">Present</th></tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    <tr>
                        <td>{{ $student->roll_no }}</td>
                        <td>{{ $student->name }}</td>
                        <td>
                            <input type="checkbox" name="present[]" value="{{ $student->id }}"
                                   class="form-check-input att-box"
                                   @checked($existing->get($student->id, 'present') === 'present')>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center">No students yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div></div>

        <button class="btn btn-success">Save Attendance</button>
    </form>
@endif
@endsection
