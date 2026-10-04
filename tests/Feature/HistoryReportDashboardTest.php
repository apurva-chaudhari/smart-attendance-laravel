<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpSchool;
use Tests\TestCase;

class HistoryReportDashboardTest extends TestCase
{
    use RefreshDatabase, SetsUpSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchool();
        $this->seedHistory();
    }

    /** Pull one student's row out of the report's view data. */
    private function row($response, $student)
    {
        return $response->viewData('students')->firstWhere('id', $student->id);
    }

    // ---------- history ----------

    public function test_subject_teacher_history_only_contains_their_subject(): void
    {
        $response = $this->actingAs($this->mathTeacher)->get(route('attendance.index'));

        $response->assertOk()
            ->assertSee('Mathematics')
            ->assertDontSee('Chemistry')
            ->assertViewHas('attendances', function ($page) {
                $ids = $page->getCollection()->pluck('subject_id')->unique()->values()->all();

                return $page->total() === 4 && $ids == [$this->math->id];
            });
    }

    public function test_class_teacher_history_contains_every_subject(): void
    {
        $this->actingAs($this->classTeacher)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Mathematics')
            ->assertSee('Chemistry')
            ->assertViewHas('attendances', fn ($page) => $page->total() === 6);
    }

    public function test_subject_teacher_cannot_filter_history_by_another_subject(): void
    {
        $this->actingAs($this->mathTeacher)
            ->get(route('attendance.index', ['subject_id' => $this->chemistry->id]))
            ->assertForbidden();
    }

    public function test_class_teacher_can_filter_history_by_subject_student_and_date(): void
    {
        $this->actingAs($this->classTeacher);

        $this->get(route('attendance.index', ['subject_id' => $this->chemistry->id]))
            ->assertViewHas('attendances', fn ($p) => $p->total() === 2);

        $this->get(route('attendance.index', ['student_id' => $this->priya->id]))
            ->assertViewHas('attendances', fn ($p) => $p->total() === 2);

        $this->get(route('attendance.index', ['date' => $this->daysAgo(2)]))
            ->assertViewHas('attendances', fn ($p) => $p->total() === 4);

        $this->get(route('attendance.index', [
            'subject_id' => $this->math->id,
            'student_id' => $this->rahul->id,
            'date' => $this->daysAgo(0),
        ]))->assertViewHas('attendances', fn ($p) => $p->total() === 1);
    }

    public function test_teacher_without_subjects_sees_empty_history(): void
    {
        $this->actingAs($this->idleTeacher)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('No records found')
            ->assertViewHas('attendances', fn ($p) => $p->total() === 0);
    }

    // ---------- reports ----------

    public function test_subject_teacher_report_counts_only_their_subject(): void
    {
        $response = $this->actingAs($this->mathTeacher)->get(route('reports.attendance'));

        $response->assertOk();

        $rahul = $this->row($response, $this->rahul);
        $this->assertSame(3, (int) $rahul->total_classes);
        $this->assertSame(2, (int) $rahul->present_count);
        $this->assertSame(1, (int) $rahul->absent_count);
        $this->assertEquals(66.7, $rahul->percentage);

        $priya = $this->row($response, $this->priya);
        $this->assertSame(1, (int) $priya->total_classes);
        $this->assertEquals(0, $priya->percentage);
    }

    public function test_class_teacher_overall_report_combines_all_subjects(): void
    {
        $response = $this->actingAs($this->classTeacher)->get(route('reports.attendance'));

        $this->assertEquals(50.0, $this->row($response, $this->rahul)->percentage);
        $this->assertSame(4, (int) $this->row($response, $this->rahul)->total_classes);
        $this->assertEquals(50.0, $this->row($response, $this->priya)->percentage);
    }

    public function test_class_teacher_can_report_on_a_single_subject(): void
    {
        $response = $this->actingAs($this->classTeacher)
            ->get(route('reports.attendance', ['subject_id' => $this->chemistry->id]));

        $this->assertEquals(0, $this->row($response, $this->rahul)->percentage);
        $this->assertEquals(100, $this->row($response, $this->priya)->percentage);
    }

    public function test_subject_teacher_cannot_report_on_another_subject(): void
    {
        $this->actingAs($this->mathTeacher)
            ->get(route('reports.attendance', ['subject_id' => $this->chemistry->id]))
            ->assertForbidden();
    }

    public function test_report_respects_the_date_range(): void
    {
        $day = $this->daysAgo(1);

        $response = $this->actingAs($this->mathTeacher)
            ->get(route('reports.attendance', ['from' => $day, 'to' => $day]));

        $rahul = $this->row($response, $this->rahul);
        $this->assertSame(1, (int) $rahul->total_classes);
        $this->assertEquals(100, $rahul->percentage);

        // Priya has no Maths record that day: 0 classes, 0% (and no division by zero)
        $priya = $this->row($response, $this->priya);
        $this->assertSame(0, (int) $priya->total_classes);
        $this->assertEquals(0, $priya->percentage);
    }

    public function test_report_works_with_only_a_to_date(): void
    {
        $this->actingAs($this->mathTeacher)
            ->get(route('reports.attendance', ['to' => $this->daysAgo(1)]))
            ->assertOk()
            ->assertSessionHasNoErrors();
    }

    public function test_report_rejects_a_to_date_before_the_from_date(): void
    {
        $this->actingAs($this->mathTeacher)
            ->get(route('reports.attendance', ['from' => $this->daysAgo(0), 'to' => $this->daysAgo(3)]))
            ->assertSessionHasErrors('to');
    }

    public function test_student_with_no_records_shows_zero_percent(): void
    {
        $newStudent = \App\Models\Student::create(['name' => 'Amit Verma', 'roll_no' => '3', 'course' => 'BCA', 'year' => 2]);

        $response = $this->actingAs($this->classTeacher)->get(route('reports.attendance'));

        $row = $this->row($response, $newStudent);
        $this->assertSame(0, (int) $row->total_classes);
        $this->assertEquals(0, $row->percentage);
    }

    // ---------- dashboard ----------

    public function test_subject_teacher_dashboard_is_scoped_to_their_subjects(): void
    {
        $this->actingAs($this->mathTeacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mathematics')
            ->assertDontSee('Chemistry')
            ->assertViewHas('subjects', fn ($s) => $s->count() === 1)
            // today: only Rahul's absent Maths record exists
            ->assertViewHas('presentToday', 0)
            ->assertViewHas('absentToday', 1);
    }

    public function test_class_teacher_dashboard_covers_every_subject(): void
    {
        $this->actingAs($this->classTeacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mathematics')
            ->assertSee('Chemistry')
            ->assertViewHas('subjects', fn ($s) => $s->count() === 2)
            ->assertViewHas('totalStudents', 2);
    }

    public function test_low_attendance_list_only_uses_the_teachers_own_subjects(): void
    {
        // Chemistry teacher: Rahul 0% (low), Priya 100% (fine)
        $this->actingAs($this->scienceTeacher)
            ->get(route('dashboard'))
            ->assertViewHas('lowAttendance', fn ($list) => $list->pluck('id')->all() == [$this->rahul->id]);

        // Maths teacher: Rahul 66.7% and Priya 0% are both below 75%
        $this->actingAs($this->mathTeacher)
            ->get(route('dashboard'))
            ->assertViewHas('lowAttendance', fn ($list) => $list->count() === 2);
    }

    public function test_dashboard_for_a_teacher_without_subjects_explains_why(): void
    {
        $this->actingAs($this->idleTeacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No subjects are assigned to you yet')
            ->assertViewHas('presentToday', 0);
    }
}
