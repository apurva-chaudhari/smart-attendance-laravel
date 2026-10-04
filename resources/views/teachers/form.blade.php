<div class="mb-3">
    <label class="form-label">Name</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $teacher->name ?? '') }}">
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label">Email (used to log in)</label>
    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
           value="{{ old('email', $teacher->email ?? '') }}">
    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label">Password @isset($teacher)<small class="text-muted">(leave blank to keep the current one)</small>@endisset</label>
    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
           autocomplete="new-password">
    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <div class="form-text">At least 8 characters.</div>
</div>
