@extends('layout')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Subject Teachers</h3>
    <a href="{{ route('teachers.create') }}" class="btn btn-primary">Add Teacher</a>
</div>

<div class="card"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead class="table-dark">
        <tr><th>Name</th><th>Email</th><th>Subjects</th><th width="180">Actions</th></tr>
    </thead>
    <tbody>
        @forelse ($teachers as $teacher)
            <tr>
                <td>{{ $teacher->name }}</td>
                <td>{{ $teacher->email }}</td>
                <td>
                    @forelse ($teacher->subjects as $subject)
                        <span class="badge bg-info text-dark">{{ $subject->name }}</span>
                    @empty
                        <span class="text-muted">None assigned</span>
                    @endforelse
                </td>
                <td>
                    <a href="{{ route('teachers.edit', $teacher) }}" class="btn btn-sm btn-warning">Edit</a>
                    <form action="{{ route('teachers.destroy', $teacher) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Delete this teacher account? Their subjects will become unassigned.')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center">No subject teachers yet.</td></tr>
        @endforelse
    </tbody>
</table>
</div></div>
<small class="text-muted d-block mt-2">Assign subjects to a teacher from the Subjects page.</small>
@endsection
