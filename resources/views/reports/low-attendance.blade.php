@extends('layout')

@section('content')
<style>
    @media print {
        nav.navbar, footer, .no-print { display: none !important; }
        body { background: #fff !important; }
        .card { box-shadow: none !important; border: 0 !important; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <h3 class="mb-0">Low Attendance Notice</h3>
    <a href="{{ route('reports.attendance') }}" class="btn btn-outline-secondary btn-sm">Back to Report</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger no-print">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

@if ($subjects->isEmpty())
    <div class="alert alert-warning">No subjects are assigned to you yet, so there is nothing to report on.</div>
@else
    <form method="GET" action="{{ route('reports.low-attendance') }}" class="row g-2 mb-3 align-items-end no-print">
        <div class="col-auto">
            <label class="form-label mb-0">Subject</label>
            <select name="subject_id" class="form-select">
                <option value="">{{ auth()->user()->isClassTeacher() ? 'All subjects (overall)' : 'All my subjects' }}</option>
                @foreach ($subjects as $s)
                    <option value="{{ $s->id }}" @selected(request('subject_id') == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label mb-0">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control">
        </div>
        <div class="col-auto">
            <label class="form-label mb-0">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="form-control">
        </div>
        <div class="col-auto">
            <label class="form-label mb-0">Below %</label>
            <input type="number" name="threshold" min="1" max="100" value="{{ request('threshold', 75) }}" class="form-control" style="width: 90px;">
        </div>
        <div class="col-auto">
            <label class="form-label mb-0">Min. classes</label>
            <input type="number" name="min_classes" min="1" max="200" value="{{ request('min_classes', 1) }}" class="form-control" style="width: 100px;">
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-primary">Apply</button>
            <a href="{{ route('reports.low-attendance') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    {{-- The notice itself (this part is what gets printed) --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="text-center mb-3">
                <h4 class="text-danger fw-bold mb-1">LOW ATTENDANCE NOTICE</h4>
                <div><strong>Subject:</strong> {{ $notice->scopeLabel }}</div>
                <div><strong>Period:</strong> {{ $notice->periodLabel() }}</div>
                <div><strong>Attendance below:</strong> {{ $notice->threshold }}%</div>
            </div>

            @if ($notice->rows()->isEmpty())
                <div class="alert alert-success mb-0">
                    Good news! No student is below {{ $notice->threshold }}% for this selection.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Sr</th><th>Roll No</th><th>Student</th><th>Attendance</th><th>Classes</th>
                                @if ($notice->showsBreakdown())<th>Low in</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($notice->rows() as $row)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $row['student']->roll_no }}</td>
                                    <td>{{ $row['student']->name }}</td>
                                    <td><span class="badge bg-danger">{{ $notice->formatPercent($row['percentage']) }}%</span></td>
                                    <td>{{ $row['present'] }} / {{ $row['total'] }}</td>
                                    @if ($notice->showsBreakdown())
                                        <td>
                                            @forelse ($row['weak'] as $weak)
                                                {{ $weak['subject'] }} ({{ $notice->formatPercent($weak['percentage']) }}%)@if (! $loop->last), @endif
                                            @empty
                                                -
                                            @endforelse
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 mb-0">
                    The students listed above are requested to improve their attendance and meet the teacher.
                    <br><small class="text-muted">Issued by {{ auth()->user()->name }} on {{ now()->format('d-m-Y') }}</small>
                </p>
            @endif
        </div>
    </div>

    @if ($notice->rows()->isNotEmpty())
        <div class="card no-print">
            <div class="card-header bg-white fw-semibold">Share this notice</div>
            <div class="card-body">
                <label class="form-label">WhatsApp message <small class="text-muted">(you can edit it before sharing)</small></label>
                <textarea id="wa-message" class="form-control mb-3" rows="12">{{ $message }}</textarea>

                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <button type="button" id="wa-copy" class="btn btn-outline-secondary">
                        <i class="bi bi-clipboard"></i> Copy message
                    </button>

                    @if ($shareUrl)
                        <a id="wa-share" href="{{ $shareUrl }}" target="_blank" rel="noopener" class="btn btn-success">
                            <i class="bi bi-whatsapp"></i> Share on WhatsApp
                        </a>
                    @else
                        <button type="button" class="btn btn-success" disabled>
                            <i class="bi bi-whatsapp"></i> Share on WhatsApp
                        </button>
                        <small class="text-muted">The message is too long for a share link. Use "Copy message" and paste it into the group.</small>
                    @endif

                    <a href="{{ route('reports.low-attendance.pdf', request()->query()) }}" target="_blank" class="btn btn-danger">
                        <i class="bi bi-file-earmark-pdf"></i> PDF (view / print / download)
                    </a>

                    <button type="button" class="btn btn-outline-dark" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print this page
                    </button>

                    <span id="wa-copied" class="text-success small" style="display:none;">Copied!</span>
                </div>
            </div>
        </div>

        <script>
            (function () {
                const box = document.getElementById('wa-message');
                const share = document.getElementById('wa-share');
                const copy = document.getElementById('wa-copied');

                // keep the share link in step with any edits made to the text
                if (share) {
                    box.addEventListener('input', function () {
                        share.href = 'https://wa.me/?text=' + encodeURIComponent(box.value);
                    });
                }

                document.getElementById('wa-copy').addEventListener('click', async function () {
                    try {
                        await navigator.clipboard.writeText(box.value);
                    } catch (e) {
                        box.select();
                        document.execCommand('copy');
                    }
                    copy.style.display = 'inline';
                    setTimeout(function () { copy.style.display = 'none'; }, 2000);
                });
            })();
        </script>
    @endif
@endif
@endsection
