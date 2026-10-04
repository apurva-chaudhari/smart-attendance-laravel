@extends('layout')

@section('content')
<h3>Add Subject</h3>

<form action="{{ route('subjects.store') }}" method="POST" class="card card-body">
    @csrf
    @include('subjects.form')
    <div>
        <button class="btn btn-success">Save</button>
        <a href="{{ route('subjects.index') }}" class="btn btn-secondary">Cancel</a>
    </div>
</form>
@endsection
