<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Works out which students have poor attendance and turns the result into
 * (1) rows for the page / PDF and (2) a ready-to-paste WhatsApp message.
 *
 * Rule: a student is listed when, across the chosen subjects and dates,
 *   - they have at least $minClasses recorded classes, AND
 *   - their attendance % (rounded to 1 decimal, as shown) is BELOW $threshold.
 * Exactly at the threshold is NOT listed.
 *
 * $subjects must already be limited to what the logged-in teacher may see.
 */
class LowAttendanceNotice
{
    /** Very long wa.me links stop working reliably; above this we ask the teacher to copy the text instead. */
    public const MAX_SHARE_URL_LENGTH = 6000;

    private ?Collection $rows = null;

    public function __construct(
        private readonly Collection $subjects,
        public readonly string $scopeLabel,
        public readonly int $threshold = 75,
        public readonly int $minClasses = 1,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {
    }

    /** Only show the "Low in: ..." breakdown when more than one subject is being combined. */
    public function showsBreakdown(): bool
    {
        return $this->subjects->count() > 1;
    }

    /**
     * @return Collection<int, array{student: Student, total: int, present: int, absent: int, percentage: float, weak: Collection}>
     */
    public function rows(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $subjectIds = $this->subjects->pluck('id')->all();

        if ($subjectIds === []) {
            return $this->rows = collect();
        }

        $stats = Attendance::query()
            ->whereIn('subject_id', $subjectIds)
            ->when($this->from, fn ($query, $from) => $query->where('date', '>=', $from))
            ->when($this->to, fn ($query, $to) => $query->where('date', '<=', $to))
            ->selectRaw("student_id, subject_id,
                COUNT(*) AS total_classes,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count")
            ->groupBy('student_id', 'subject_id')
            ->get();

        $students = Student::all()->keyBy('id');
        $subjectNames = $this->subjects->pluck('name', 'id');

        $rows = $stats->groupBy('student_id')->map(function ($group, $studentId) use ($students, $subjectNames) {
            $student = $students->get($studentId);
            if (! $student) {
                return null;
            }

            $total = (int) $group->sum('total_classes');
            $present = (int) $group->sum('present_count');

            $weak = collect();
            if ($this->showsBreakdown()) {
                $weak = $group
                    ->map(fn ($s) => [
                        'subject'    => $subjectNames->get($s->subject_id),
                        'total'      => (int) $s->total_classes,
                        'percentage' => $this->percentage((int) $s->present_count, (int) $s->total_classes),
                    ])
                    ->filter(fn ($s) => $s['total'] >= $this->minClasses && $s['percentage'] < $this->threshold)
                    ->sortBy('percentage')
                    ->values();
            }

            return [
                'student'    => $student,
                'total'      => $total,
                'present'    => $present,
                'absent'     => $total - $present,
                'percentage' => $this->percentage($present, $total),
                'weak'       => $weak,
            ];
        });

        return $this->rows = $rows
            ->filter()
            ->filter(fn ($row) => $row['total'] >= $this->minClasses && $row['percentage'] < $this->threshold)
            // worst first; same % -> by roll number (1, 2, 10 - not 1, 10, 2)
            ->sort(fn ($a, $b) => ($a['percentage'] <=> $b['percentage'])
                ?: strnatcmp((string) $a['student']->roll_no, (string) $b['student']->roll_no))
            ->values();
    }

    public function periodLabel(?Carbon $today = null): string
    {
        $today ??= now();
        $format = fn (string $date) => Carbon::parse($date)->format('d-m-Y');

        if ($this->from && $this->to) {
            return $format($this->from) . ' to ' . $format($this->to);
        }
        if ($this->from) {
            return 'From ' . $format($this->from) . ' to ' . $today->format('d-m-Y');
        }
        if ($this->to) {
            return 'Up to ' . $format($this->to);
        }

        return 'All classes up to ' . $today->format('d-m-Y');
    }

    /** 62.5 -> "62.5", 50.0 -> "50", 0 -> "0" */
    public function formatPercent(float|int $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }

    /** WhatsApp understands *bold*, so the message looks tidy when pasted into a group. */
    public function whatsappMessage(string $issuedBy): string
    {
        $rows = $this->rows();

        $lines = [
            '📢 *LOW ATTENDANCE NOTICE*',
            '',
            '📚 Subject: ' . $this->scopeLabel,
            '🗓️ Period: ' . $this->periodLabel(),
            '⚠️ Attendance below ' . $this->threshold . '%',
            '',
        ];

        if ($rows->isEmpty()) {
            $lines[] = '✅ Good news! No student is below ' . $this->threshold . '%.';
        } else {
            foreach ($rows as $index => $row) {
                $lines[] = ($index + 1) . '. ' . $row['student']->name
                    . ' (Roll ' . $row['student']->roll_no . ') - *'
                    . $this->formatPercent($row['percentage']) . '%* ('
                    . $row['present'] . '/' . $row['total'] . ' classes)';

                if ($row['weak']->isNotEmpty()) {
                    $lines[] = '    Low in: ' . $row['weak']
                        ->map(fn ($w) => $w['subject'] . ' ' . $this->formatPercent($w['percentage']) . '%')
                        ->join(', ');
                }
            }

            $lines[] = '';
            $lines[] = 'Total: ' . $rows->count() . ' student(s)';
            $lines[] = '';
            $lines[] = 'The students listed above are requested to improve their attendance and meet the teacher.';
        }

        $lines[] = '';
        $lines[] = '- ' . $issuedBy;

        return implode("\n", $lines);
    }

    /** Click-to-share link. null = nobody to warn, or the message is too long for a link. */
    public function whatsappUrl(string $issuedBy): ?string
    {
        if ($this->rows()->isEmpty()) {
            return null;
        }

        $url = 'https://wa.me/?text=' . rawurlencode($this->whatsappMessage($issuedBy));

        return strlen($url) <= self::MAX_SHARE_URL_LENGTH ? $url : null;
    }

    private function percentage(int $present, int $total): float
    {
        return $total > 0 ? round($present / $total * 100, 1) : 0.0;
    }
}
