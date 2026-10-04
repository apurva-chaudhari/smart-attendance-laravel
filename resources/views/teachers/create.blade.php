@extends('layout')

@section('content')
<h3>Add Subject Teacher</h3>

<form action="{{ route('teachers.store') }}" method="POST" class="card card-body">
    @csrf
    @include('teachers.form')
    <div>
        <button class="btn btn-success">Create Account</button>
        <a href="{{ route('teachers.index') }}" class="btn btn-secondary">Cancel</a>
    </div>
</form>
@endsection
