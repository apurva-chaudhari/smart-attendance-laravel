@extends('layout')

@section('content')
<h3>Add Student</h3>

<form action="{{ route('students.store') }}" method="POST" class="card card-body">
    @csrf
    @include('students.form')
    <div>
        <button class="btn btn-success">Save</button>
        <a href="{{ route('students.index') }}" class="btn btn-secondary">Cancel</a>
    </div>
</form>
@endsection