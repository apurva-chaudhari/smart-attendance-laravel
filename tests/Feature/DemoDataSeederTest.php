<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_teachers_subjects_students_and_attendance(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(12, Student::count());
        $this->assertSame(3, Subject::count());
        $this->assertSame(1, User::where('role', User::ROLE_CLASS_TEACHER)->count());
        $this->assertSame(2, User::where('role', User::ROLE_SUBJECT_TEACHER)->count());

        foreach (Subject::all() as $subject) {
            $this->assertGreaterThan(0, $subject->attendances()->count(), $subject->name . ' has no attendance');
        }
    }

    public function test_running_it_twice_does_not_duplicate_anything(): void
    {
        $this->seed(DemoDataSeeder::class);
        $before = [Student::count(), Subject::count(), User::count(), Attendance::count()];

        $this->seed(DemoDataSeeder::class);
        $after = [Student::count(), Subject::count(), User::count(), Attendance::count()];

        $this->assertSame($before, $after);
    }

    public function test_it_never_changes_students_that_already_exist(): void
    {
        Student::create([
            'name' => 'Apurva Rajesh Chaudhari', 'roll_no' => '7',
            'course' => 'Information Technology', 'year' => 3,
        ]);

        $this->seed(DemoDataSeeder::class);

        $this->assertSame('Apurva Rajesh Chaudhari', Student::where('roll_no', '7')->value('name'));
        $this->assertSame(12, Student::count());   // roll 7 was not created twice
    }

    public function test_it_does_not_overwrite_existing_attendance(): void
    {
        $this->seed(DemoDataSeeder::class);

        $row = Attendance::first();
        $row->update(['status' => $row->status === 'present' ? 'absent' : 'present']);
        $changed = $row->fresh()->status;

        $this->seed(DemoDataSeeder::class);

        $this->assertSame($changed, $row->fresh()->status);
    }

    public function test_attendance_is_only_on_weekdays_and_never_in_the_future(): void
    {
        $this->seed(DemoDataSeeder::class);

        foreach (Attendance::all() as $record) {
            $this->assertFalse($record->date->isWeekend(), 'Weekend record: ' . $record->date->toDateString());
            $this->assertTrue($record->date->lte(now()->endOfDay()), 'Future record: ' . $record->date->toDateString());
        }
    }

    public function test_java_is_left_unmarked_today_so_the_dashboard_has_something_to_show(): void
    {
        if (now()->isWeekend()) {
            $this->markTestSkipped('No attendance is generated on weekends.');
        }

        $this->seed(DemoDataSeeder::class);
        $today = now()->toDateString();

        $java = Subject::where('code', 'JAVA301')->first();
        $php = Subject::where('code', 'PHP101')->first();

        $this->assertSame(0, $java->attendances()->where('date', $today)->count());
        $this->assertGreaterThan(0, $php->attendances()->where('date', $today)->count());
    }

    public function test_demo_data_fills_the_dashboard_and_the_report(): void
    {
        $this->seed(DemoDataSeeder::class);
        $classTeacher = User::where('role', User::ROLE_CLASS_TEACHER)->first();

        $this->actingAs($classTeacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('lowAttendance', fn ($list) => $list->count() > 0);

        $this->actingAs($classTeacher)
            ->get(route('reports.attendance'))
            ->assertOk()
            ->assertViewHas('students', function ($students) {
                return $students->count() === 12
                    && $students->every(fn ($s) => $s->total_classes > 0);
            });
    }

    public function test_each_subject_teacher_sees_only_their_own_demo_data(): void
    {
        $this->seed(DemoDataSeeder::class);
        $kulkarni = User::where('email', 'kulkarni@example.com')->first();

        $this->actingAs($kulkarni)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Java Programming')
            ->assertDontSee('PHP & Laravel')
            ->assertDontSee('Database Systems');
    }
}
