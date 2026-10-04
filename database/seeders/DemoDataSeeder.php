<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * One command to fill the app with demo data:
 *
 *     php artisan db:seed --class=DemoDataSeeder
 *
 * It creates the demo teachers + subjects (via DatabaseSeeder), 12 students and
 * about 3 weeks of weekday attendance for every subject.
 *
 * Safe to run more than once: it never duplicates anything and never
 * overwrites a student or attendance row that already exists.
 * Local/demo use only -- it does nothing when APP_ENV=production.
 */
class DemoDataSeeder extends Seeder
{
    private const STUDENTS = [
        'Rahul Sharma', 'Priya Patil', 'Amit Verma', 'Sneha Joshi',
        'Rohan Deshmukh', 'Ananya Kulkarni', 'Vikram Singh', 'Neha Gupta',
        'Karan Malhotra', 'Pooja Nair', 'Aditya Rao', 'Isha Kapoor',
    ];

    // How likely (in %) each student is to be present. A few are deliberately
    // low so the "Below 75%" panel and the red report bars have something to show.
    private const PRESENT_RATES = [95, 90, 85, 92, 78, 65, 88, 97, 72, 83, 60, 91];

    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        // Teachers and subjects must exist first.
        $this->call(DatabaseSeeder::class);

        // Roll numbers 1-12. A roll number that already exists is left alone,
        // so your own students are never changed.
        foreach (self::STUDENTS as $index => $name) {
            Student::firstOrCreate(
                ['roll_no' => (string) ($index + 1)],
                ['name' => $name, 'course' => 'Information Technology', 'year' => 3]
            );
        }

        $students = Student::ordered();
        $subjects = Subject::orderBy('code')->get();

        // Fixed seed = the same "random" pattern every time (repeatable demos).
        mt_srand(2026);

        $created = 0;

        for ($daysAgo = 20; $daysAgo >= 0; $daysAgo--) {
            $date = now()->subDays($daysAgo);

            if ($date->isWeekend()) {
                continue;
            }

            foreach ($subjects as $subject) {
                // Leave Java unmarked today so the dashboard shows a "Not marked" badge.
                if ($daysAgo === 0 && $subject->code === 'JAVA301') {
                    continue;
                }

                foreach ($students as $index => $student) {
                    $rate = self::PRESENT_RATES[$index % count(self::PRESENT_RATES)];
                    $status = mt_rand(1, 100) <= $rate ? 'present' : 'absent';

                    $record = Attendance::firstOrCreate(
                        [
                            'student_id' => $student->id,
                            'subject_id' => $subject->id,
                            'date'       => $date->toDateString(),
                        ],
                        ['status' => $status]
                    );

                    if ($record->wasRecentlyCreated) {
                        $created++;
                    }
                }
            }
        }

        mt_srand(); // go back to normal randomness

        $this->command?->info(
            'Demo data ready: ' . $students->count() . ' students, '
            . $subjects->count() . ' subjects, ' . $created . ' new attendance records.'
        );
    }
}
