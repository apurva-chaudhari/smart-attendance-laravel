<div class="mb-3">
    <label class="form-label">Subject Name</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $subject->name ?? '') }}">
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label">Subject Code</label>
    <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
           value="{{ old('code', $subject->code ?? '') }}">
    @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label">Subject Teacher</label>
    <select name="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror">
        <option value="">- Unassigned -</option>
        @foreach ($teachers as $teacher)
            <option value="{{ $teacher->id }}" @selected(old('teacher_id', $subject->teacher_id ?? '') == $teacher->id)>
                {{ $teacher->name }} ({{ $teacher->email }})
            </option>
        @endforeach
    </select>
    @error('teacher_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
