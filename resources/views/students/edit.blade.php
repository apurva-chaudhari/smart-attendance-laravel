@extends('layout')

@section('content')
<h3>Edit Student</h3>

<form action="{{ route('students.update', $student) }}" method="POST" class="card card-body">
    @csrf
    @method('PUT')
    @include('students.form')
    <div>
        <button class="btn btn-success">Update</button>
        <a href="{{ route('students.index') }}" class="btn btn-secondary">Cancel</a>
    </div>
</form>
@endsection