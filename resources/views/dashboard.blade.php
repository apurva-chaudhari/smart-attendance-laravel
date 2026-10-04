@extends('layout')

@section('content')
@php $isClassTeacher = auth()->user()->isClassTeacher(); @endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0">Dashboard</h3>
        <small class="text-muted">{{ \Carbon\Carbon::parse($today)->format('l, d F Y') }}</small>
    </div>
    <a href="{{ route('attendance.mark') }}" class="btn btn-primary">
        <i class="bi bi-check2-square"></i> Mark Attendance
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex justify-content-between align-items-center">
            <div><div class="text-muted small">Total Students</div><div class="fs-3 fw-semibold">{{ $totalStudents }}</div></div>
            <i class="bi bi-people stat-icon text-primary"></i>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex justify-content-between align-items-center">
            <div><div class="text-muted small">{{ $isClassTeacher ? 'All Subjects' : 'My Subjects' }}</div><div class="fs-3 fw-semibold">{{ $subjects->count() }}</div></div>
            <i class="bi bi-journal-bookmark stat-icon text-info"></i>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex justify-content-between align-items-center">
            <div><div class="text-muted small">Present Today</div><div class="fs-3 fw-semibold">{{ $presentToday }}</div></div>
            <i class="bi bi-check-circle stat-icon text-success"></i>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card"><div class="card-body d-flex justify-content-between align-items-center">
            <div><div class="text-muted small">Absent Today</div><div class="fs-3 fw-semibold">{{ $absentToday }}</div></div>
            <i class="bi bi-x-circle stat-icon text-danger"></i>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">{{ $isClassTeacher ? 'All Subjects' : 'My Subjects' }} - Today</div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Subject</th>@if ($isClassTeacher)<th>Teacher</th>@endif<th>Today</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($subjects as $subject)
                            @php $stat = $todayStats->get($subject->id); @endphp
                            <tr>
                                <td>{{ $subject->name }} <small class="text-muted">({{ $subject->code }})</small></td>
                                @if ($isClassTeacher)
                                    <td>{{ $subject->teacher?->name ?? 'Unassigned' }}</td>
                                @endif
                                <td>
                                    @if ($stat)
                                        <span class="badge bg-success">{{ $stat->present_count }} present</span>
                                        <span class="badge bg-danger">{{ $stat->absent_count }} absent</span>
                                    @else
                                        <span class="badge bg-secondary">Not marked</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('attendance.mark', ['subject_id' => $subject->id]) }}" class="btn btn-sm btn-outline-primary">Mark</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">
                                {{ $isClassTeacher ? 'No subjects yet. Add one from the Subjects page.' : 'No subjects are assigned to you yet. Please ask the class teacher.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-exclamation-triangle text-danger"></i> Below 75% Attendance</span>
                <a href="{{ route('reports.low-attendance') }}" class="btn btn-sm btn-outline-danger">Create notice</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Roll No</th><th>Student</th><th>Attendance</th></tr></thead>
                    <tbody>
                        @forelse ($lowAttendance as $student)
                            <tr>
                                <td>{{ $student->roll_no }}</td>
                                <td>{{ $student->name }}</td>
                                <td><span class="badge bg-danger">{{ $student->percentage }}%</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">Nobody is below 75%.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
