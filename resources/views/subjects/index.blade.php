@extends('layout')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Subjects</h3>
    <a href="{{ route('subjects.create') }}" class="btn btn-primary">Add Subject</a>
</div>

<div class="card"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead class="table-dark">
        <tr><th>Code</th><th>Subject</th><th>Teacher</th><th>Records</th><th width="180">Actions</th></tr>
    </thead>
    <tbody>
        @forelse ($subjects as $subject)
            <tr>
                <td>{{ $subject->code }}</td>
                <td>{{ $subject->name }}</td>
                <td>
                    @if ($subject->teacher)
                        {{ $subject->teacher->name }}
                    @else
                        <span class="badge bg-secondary">Unassigned</span>
                    @endif
                </td>
                <td>{{ $subject->attendances_count }}</td>
                <td>
                    <a href="{{ route('subjects.edit', $subject) }}" class="btn btn-sm btn-warning">Edit</a>
                    <form action="{{ route('subjects.destroy', $subject) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Delete this subject?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center">No subjects yet.</td></tr>
        @endforelse
    </tbody>
</table>
</div></div>
@endsection
