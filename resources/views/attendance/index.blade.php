@extends('layout')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Attendance History</h3>
    <a href="{{ route('attendance.mark') }}" class="btn btn-primary">Mark Attendance</a>
</div>

<form method="GET" action="{{ route('attendance.index') }}" class="row g-2 mb-3">
    <div class="col-auto">
        <input type="date" name="date" value="{{ request('date') }}" class="form-control">
    </div>
    <div class="col-auto">
        <select name="subject_id" class="form-select">
            <option value="">All subjects</option>
            @foreach ($subjects as $s)
                <option value="{{ $s->id }}" @selected(request('subject_id') == $s->id)>{{ $s->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <select name="student_id" class="form-select">
            <option value="">All students</option>
            @foreach ($students as $student)
                <option value="{{ $student->id }}" @selected(request('student_id') == $student->id)>
                    {{ $student->roll_no }} - {{ $student->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-primary">Filter</button>
        <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>

<div class="card"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead class="table-dark">
        <tr><th>Date</th><th>Subject</th><th>Roll No</th><th>Student</th><th>Status</th></tr>
    </thead>
    <tbody>
        @forelse ($attendances as $attendance)
            <tr>
                <td>{{ $attendance->date->format('d-m-Y') }}</td>
                <td>{{ $attendance->subject->name }}</td>
                <td>{{ $attendance->student->roll_no }}</td>
                <td>{{ $attendance->student->name }}</td>
                <td>
                    @if ($attendance->status === 'present')
                        <span class="badge bg-success">Present</span>
                    @else
                        <span class="badge bg-danger">Absent</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center">No records found.</td></tr>
        @endforelse
    </tbody>
</table>
</div></div>

<div class="mt-3">{{ $attendances->links() }}</div>
@endsection
