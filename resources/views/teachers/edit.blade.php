@extends('layout')

@section('content')
<h3>Edit Teacher</h3>

<form action="{{ route('teachers.update', $teacher) }}" method="POST" class="card card-body">
    @csrf
    @method('PUT')
    @include('teachers.form')
    <div>
        <button class="btn btn-success">Update</button>
        <a href="{{ route('teachers.index') }}" class="btn btn-secondary">Cancel</a>
    </div>
</form>
@endsection
