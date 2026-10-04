@extends('layout')

@section('content')
<h3>Edit Subject</h3>

<form action="{{ route('subjects.update', $subject) }}" method="POST" class="card card-body">
    @csrf
    @method('PUT')
    @include('subjects.form')
    <div>
        <button class="btn btn-success">Update</button>
        <a href="{{ route('subjects.index') }}" class="btn btn-secondary">Cancel</a>
    </div>
</form>
@endsection
