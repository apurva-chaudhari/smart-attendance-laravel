<?php

namespace App\Http\Controllers;

use App\Support\LowAttendanceNotice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LowAttendanceController extends Controller
{
    /** Preview page + WhatsApp message + buttons. */
    public function show(Request $request)
    {
        $notice = $this->notice($request);
        $user = $request->user();

        return view('reports.low-attendance', [
            'notice'   => $notice,
            'subjects' => $user->accessibleSubjects()->orderBy('name')->get(),
            'message'  => $notice->whatsappMessage($user->name),
            'shareUrl' => $notice->whatsappUrl($user->name),
        ]);
    }

    /** Printable A4 notice (opens in the browser's PDF viewer: print / download from there). */
    public function pdf(Request $request)
    {
        $notice = $this->notice($request);

        $pdf = Pdf::loadView('reports.low-attendance-pdf', [
            'notice'      => $notice,
            'issuedBy'    => $request->user()->name,
            'generatedOn' => now(),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('low-attendance-notice-' . now()->format('Y-m-d') . '.pdf');
    }

    /** Validates the filters and builds the notice, limited to the teacher's own subjects. */
    private function notice(Request $request): LowAttendanceNotice
    {
        $user = $request->user();

        $rules = [
            'subject_id'  => ['nullable', 'integer'],
            'from'        => ['nullable', 'date'],
            'to'          => ['nullable', 'date'],
            'threshold'   => ['nullable', 'integer', 'min:1', 'max:100'],
            'min_classes' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
        if ($request->filled('from')) {
            $rules['to'][] = 'after_or_equal:from';
        }
        $request->validate($rules);

        $accessible = $user->accessibleSubjects()->orderBy('name')->get();
        $subjects = $accessible;

        if ($request->filled('subject_id')) {
            // Missing OR someone else's subject -> forbidden.
            $selected = $accessible->firstWhere('id', $request->integer('subject_id'));
            abort_if($selected === null, 403);

            $subjects = collect([$selected]);
            $scopeLabel = $selected->name;
        } elseif ($user->isClassTeacher()) {
            $scopeLabel = 'All subjects';
        } else {
            $scopeLabel = $accessible->pluck('name')->join(', ');
        }

        return new LowAttendanceNotice(
            subjects: $subjects,
            scopeLabel: $scopeLabel,
            // blank form fields arrive as null, so test filled() instead of using a default
            threshold: $request->filled('threshold') ? $request->integer('threshold') : 75,
            minClasses: $request->filled('min_classes') ? $request->integer('min_classes') : 1,
            from: $request->filled('from') ? Carbon::parse($request->query('from'))->toDateString() : null,
            to: $request->filled('to') ? Carbon::parse($request->query('to'))->toDateString() : null,
        );
    }
}
