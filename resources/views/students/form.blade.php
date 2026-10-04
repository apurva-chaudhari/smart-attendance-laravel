<div class="mb-3">
    <label class="form-label">Name</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $student->name ?? '') }}">
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label">Roll No</label>
    <input type="text" name="roll_no" class="form-control @error('roll_no') is-invalid @enderror"
           value="{{ old('roll_no', $student->roll_no ?? '') }}">
    @error('roll_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label">Course</label>
    <input type="text" name="course" class="form-control @error('course') is-invalid @enderror"
           value="{{ old('course', $student->course ?? '') }}">
    @error('course') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label">Year</label>
    <input type="number" name="year" class="form-control @error('year') is-invalid @enderror"
           value="{{ old('year', $student->year ?? '') }}">
    @error('year') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>