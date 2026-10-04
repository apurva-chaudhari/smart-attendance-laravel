<?php

namespace Tests\Feature;

use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpSchool;
use Tests\TestCase;

class AttendanceMarkingTest extends TestCase
{
    use RefreshDatabase, SetsUpSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchool();
    }

    // ---------- who sees what on the Mark page ----------

    public function test_subject_teacher_only_sees_their_own_subject_on_the_mark_page(): void
    {
        $this->actingAs($this->mathTeacher)
            ->get(route('attendance.mark'))
            ->assertOk()
            ->assertSee('Mathematics')
            ->assertDontSee('Chemistry')
            ->assertSee('Rahul Sharma');
    }

    public function test_class_teacher_sees_every_subject_on_the_mark_page(): void
    {
        $this->actingAs($this->classTeacher)
            ->get(route('attendance.mark'))
            ->assertOk()
            ->assertSee('Mathematics')
            ->assertSee('Chemistry');
    }

    public function test_subject_teacher_cannot_open_another_teachers_subject(): void
    {
        $this->actingAs($this->mathTeacher)
            ->get(route('attendance.mark', ['subject_id' => $this->chemistry->id]))
            ->assertForbidden();
    }

    public function test_requesting_a_subject_that_does_not_exist_is_forbidden(): void
    {
        $this->actingAs($this->mathTeacher)
            ->get(route('attendance.mark', ['subject_id' => 99999]))
            ->assertForbidden();
    }

    public function test_teacher_without_subjects_sees_a_helpful_message(): void
    {
        $this->actingAs($this->idleTeacher)
            ->get(route('attendance.mark'))
            ->assertOk()
            ->assertSee('No subjects are assigned to you yet');
    }

    public function test_roll_numbers_are_sorted_naturally_not_alphabetically(): void
    {
        \App\Models\Student::create(['name' => 'Tenth Student', 'roll_no' => '10', 'course' => 'BCA', 'year' => 2]);

        $this->actingAs($this->classTeacher)
            ->get(route('attendance.mark', ['subject_id' => $this->math->id]))
            ->assertViewHas('students', function ($students) {
                return $students->pluck('roll_no')->all() === ['1', '2', '10'];
            });
    }

    // ---------- saving ----------

    public function test_subject_teacher_can_save_attendance_for_their_own_subject(): void
    {
        $date = now()->toDateString();

        $this->actingAs($this->mathTeacher)
            ->post(route('attendance.store'), [
                'subject_id' => $this->math->id,
                'date' => $date,
                'present' => [$this->rahul->id],   // Priya is not ticked -> absent
            ])
            ->assertRedirect(route('attendance.mark', ['subject_id' => $this->math->id, 'date' => $date]))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('attendances', 2);
        $this->assertDatabaseHas('attendances', [
            'student_id' => $this->rahul->id, 'subject_id' => $this->math->id, 'date' => $date, 'status' => 'present',
        ]);
        $this->assertDatabaseHas('attendances', [
            'student_id' => $this->priya->id, 'subject_id' => $this->math->id, 'date' => $date, 'status' => 'absent',
        ]);
    }

    public function test_subject_teacher_cannot_save_attendance_for_another_subject(): void
    {
        $this->actingAs($this->mathTeacher)
            ->post(route('attendance.store'), [
                'subject_id' => $this->chemistry->id,
                'date' => now()->toDateString(),
                'present' => [$this->rahul->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_class_teacher_can_save_attendance_for_any_subject(): void
    {
        $this->actingAs($this->classTeacher);

        foreach ([$this->math, $this->chemistry] as $subject) {
            $this->post(route('attendance.store'), [
                'subject_id' => $subject->id,
                'date' => now()->toDateString(),
                'present' => [$this->rahul->id, $this->priya->id],
            ])->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('attendances', 4);
    }

    public function test_unassigned_subject_is_closed_to_subject_teachers_but_open_to_the_class_teacher(): void
    {
        $this->math->update(['teacher_id' => null]);
        $payload = ['subject_id' => $this->math->id, 'date' => now()->toDateString(), 'present' => []];

        $this->actingAs($this->mathTeacher)->post(route('attendance.store'), $payload)->assertForbidden();
        $this->actingAs($this->classTeacher)->post(route('attendance.store'), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('attendances', 2);
    }

    public function test_saving_twice_updates_the_same_rows_instead_of_duplicating(): void
    {
        $date = now()->toDateString();
        $this->actingAs($this->mathTeacher);

        $this->post(route('attendance.store'), [
            'subject_id' => $this->math->id, 'date' => $date, 'present' => [$this->rahul->id],
        ]);

        // teacher changes their mind: now only Priya is present
        $this->post(route('attendance.store'), [
            'subject_id' => $this->math->id, 'date' => $date, 'present' => [$this->priya->id],
        ]);

        $this->assertDatabaseCount('attendances', 2);
        $this->assertDatabaseHas('attendances', ['student_id' => $this->rahul->id, 'status' => 'absent']);
        $this->assertDatabaseHas('attendances', ['student_id' => $this->priya->id, 'status' => 'present']);
    }

    public function test_the_same_student_and_date_can_have_different_subjects(): void
    {
        $date = now()->toDateString();

        $this->actingAs($this->mathTeacher)->post(route('attendance.store'), [
            'subject_id' => $this->math->id, 'date' => $date, 'present' => [$this->rahul->id],
        ]);
        $this->actingAs($this->scienceTeacher)->post(route('attendance.store'), [
            'subject_id' => $this->chemistry->id, 'date' => $date, 'present' => [],
        ]);

        $this->assertDatabaseCount('attendances', 4);
        $this->assertDatabaseHas('attendances', [
            'student_id' => $this->rahul->id, 'subject_id' => $this->math->id, 'status' => 'present',
        ]);
        $this->assertDatabaseHas('attendances', [
            'student_id' => $this->rahul->id, 'subject_id' => $this->chemistry->id, 'status' => 'absent',
        ]);
    }

    public function test_leaving_everyone_unticked_marks_everyone_absent(): void
    {
        $this->actingAs($this->mathTeacher)->post(route('attendance.store'), [
            'subject_id' => $this->math->id, 'date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('attendances', 2);
        $this->assertSame(2, Attendance::where('status', 'absent')->count());
    }

    public function test_past_dates_can_be_corrected(): void
    {
        $this->actingAs($this->mathTeacher)->post(route('attendance.store'), [
            'subject_id' => $this->math->id, 'date' => $this->daysAgo(5), 'present' => [$this->rahul->id],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendances', ['date' => $this->daysAgo(5), 'student_id' => $this->rahul->id]);
    }

    public function test_saved_attendance_is_preloaded_when_the_page_is_opened_again(): void
    {
        $date = now()->toDateString();
        $this->record($this->math, $this->rahul, 'absent', $date);
        $this->record($this->math, $this->priya, 'present', $date);

        $this->actingAs($this->mathTeacher)
            ->get(route('attendance.mark', ['subject_id' => $this->math->id, 'date' => $date]))
            ->assertViewHas('existing', function ($existing) {
                return $existing->get($this->rahul->id) === 'absent'
                    && $existing->get($this->priya->id) === 'present';
            });
    }

    public function test_attendance_of_another_subject_is_not_preloaded(): void
    {
        $date = now()->toDateString();
        $this->record($this->chemistry, $this->rahul, 'absent', $date);

        $this->actingAs($this->mathTeacher)
            ->get(route('attendance.mark', ['subject_id' => $this->math->id, 'date' => $date]))
            ->assertViewHas('existing', fn ($existing) => $existing->isEmpty());
    }

    // ---------- validation ----------

    public function test_future_dates_are_rejected(): void
    {
        $this->actingAs($this->mathTeacher)->post(route('attendance.store'), [
            'subject_id' => $this->math->id,
            'date' => now()->addDay()->toDateString(),
            'present' => [$this->rahul->id],
        ])->assertSessionHasErrors('date');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_invalid_or_missing_date_is_rejected(): void
    {
        $this->actingAs($this->mathTeacher);

        $this->post(route('attendance.store'), [
            'subject_id' => $this->math->id, 'date' => 'not-a-date',
        ])->assertSessionHasErrors('date');

        $this->post(route('attendance.store'), [
            'subject_id' => $this->math->id,
        ])->assertSessionHasErrors('date');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_missing_or_unknown_subject_is_rejected(): void
    {
        $this->actingAs($this->classTeacher);

        $this->post(route('attendance.store'), [
            'date' => now()->toDateString(),
        ])->assertSessionHasErrors('subject_id');

        $this->post(route('attendance.store'), [
            'subject_id' => 99999, 'date' => now()->toDateString(),
        ])->assertSessionHasErrors('subject_id');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_unknown_student_ids_are_rejected(): void
    {
        $this->actingAs($this->mathTeacher)->post(route('attendance.store'), [
            'subject_id' => $this->math->id,
            'date' => now()->toDateString(),
            'present' => [99999],
        ])->assertSessionHasErrors('present.0');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_status_cannot_be_forced_through_extra_fields(): void
    {
        // The form only sends "present[]"; a hand-crafted "status" field is ignored.
        $this->actingAs($this->mathTeacher)->post(route('attendance.store'), [
            'subject_id' => $this->math->id,
            'date' => now()->toDateString(),
            'present' => [],
            'status' => 'present',
        ]);

        $this->assertSame(0, Attendance::where('status', 'present')->count());
    }
}
