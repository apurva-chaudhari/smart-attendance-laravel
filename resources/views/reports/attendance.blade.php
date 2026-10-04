@extends('layout')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-2">
    <h3 class="mb-0">Attendance Report</h3>
    <a href="{{ route('reports.low-attendance') }}" class="btn btn-danger">
        <i class="bi bi-megaphone"></i> Low Attendance Notice
    </a>
</div>
<p class="text-muted">
    Showing: <strong>{{ $selectedSubject ? $selectedSubject->name : (auth()->user()->isClassTeacher() ? 'All subjects' : 'All my subjects') }}</strong>
</p>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<form method="GET" action="{{ route('reports.attendance') }}" class="row g-2 mb-3 align-items-end">
    <div class="col-auto">
        <label class="form-label mb-0">Subject</label>
        <select name="subject_id" class="form-select">
            <option value="">All subjects</option>
            @foreach ($subjects as $s)
                <option value="{{ $s->id }}" @selected($selectedSubject && $selectedSubject->id == $s->id)>{{ $s->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label mb-0">From</label>
        <input type="date" name="from" value="{{ $from }}" class="form-control">
    </div>
    <div class="col-auto">
        <label class="form-label mb-0">To</label>
        <input type="date" name="to" value="{{ $to }}" class="form-control">
    </div>
    <div class="col-auto">
        <button class="btn btn-outline-primary">Apply</button>
        <a href="{{ route('reports.attendance') }}" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>

<div class="card"><div class="table-responsive">
<table class="table align-middle mb-0">
    <thead class="table-dark">
        <tr>
            <th>Roll No</th><th>Student</th><th>Total Classes</th><th>Present</th><th>Absent</th><th width="220">Attendance %</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($students as $student)
            @php $color = $student->percentage >= 75 ? 'success' : ($student->percentage >= 60 ? 'warning' : 'danger'); @endphp
            <tr>
                <td>{{ $student->roll_no }}</td>
                <td>{{ $student->name }}</td>
                <td>{{ $student->total_classes }}</td>
                <td>{{ $student->present_count }}</td>
                <td>{{ $student->absent_count }}</td>
                <td>
                    <div class="progress" style="height: 22px;">
                        <div class="progress-bar bg-{{ $color }}" role="progressbar" style="width: {{ $student->percentage }}%">
                            {{ $student->percentage }}%
                        </div>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center">No students yet.</td></tr>
        @endforelse
    </tbody>
</table>
</div></div>

<small class="text-muted d-block mt-2">
    <span class="badge bg-success">75% and above</span>
    <span class="badge bg-warning text-dark">60% to 74%</span>
    <span class="badge bg-danger">below 60%</span>
</small>
@endsection
