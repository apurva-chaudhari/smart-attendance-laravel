<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SetsUpSchool;
use Tests\TestCase;

/**
 * What the class teacher can manage: teacher accounts, subjects, students.
 */
class ClassTeacherManagementTest extends TestCase
{
    use RefreshDatabase, SetsUpSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchool();
        $this->actingAs($this->classTeacher);
    }

    // ---------- teacher accounts ----------

    public function test_class_teacher_can_create_a_subject_teacher_account(): void
    {
        $this->post(route('teachers.store'), [
            'name' => 'New Teacher', 'email' => 'new@example.com', 'password' => 'password123',
        ])->assertRedirect(route('teachers.index'));

        $user = User::where('email', 'new@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_SUBJECT_TEACHER, $user->role);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_a_role_sent_in_the_request_is_ignored(): void
    {
        $this->post(route('teachers.store'), [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'password' => 'password123',
            'role' => User::ROLE_CLASS_TEACHER,
        ]);

        $this->assertSame(
            User::ROLE_SUBJECT_TEACHER,
            User::where('email', 'sneaky@example.com')->value('role')
        );
    }

    public function test_teacher_creation_is_validated(): void
    {
        // duplicate email
        $this->post(route('teachers.store'), [
            'name' => 'Dup', 'email' => $this->mathTeacher->email, 'password' => 'password123',
        ])->assertSessionHasErrors('email');

        // short password
        $this->post(route('teachers.store'), [
            'name' => 'Short', 'email' => 'short@example.com', 'password' => '1234567',
        ])->assertSessionHasErrors('password');

        // missing everything
        $this->post(route('teachers.store'), [])->assertSessionHasErrors(['name', 'email', 'password']);

        // bad email
        $this->post(route('teachers.store'), [
            'name' => 'Bad', 'email' => 'not-an-email', 'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'short@example.com']);
    }

    public function test_blank_password_on_edit_keeps_the_current_password(): void
    {
        $this->put(route('teachers.update', $this->mathTeacher), [
            'name' => 'Renamed', 'email' => $this->mathTeacher->email, 'password' => '',
        ])->assertRedirect(route('teachers.index'));

        $fresh = $this->mathTeacher->fresh();
        $this->assertSame('Renamed', $fresh->name);
        $this->assertTrue(Hash::check('password123', $fresh->password));
    }

    public function test_a_new_password_on_edit_replaces_the_old_one(): void
    {
        $this->put(route('teachers.update', $this->mathTeacher), [
            'name' => $this->mathTeacher->name,
            'email' => $this->mathTeacher->email,
            'password' => 'brand-new-pass',
        ]);

        $this->assertTrue(Hash::check('brand-new-pass', $this->mathTeacher->fresh()->password));
    }

    public function test_email_must_stay_unique_when_editing_a_teacher(): void
    {
        $this->put(route('teachers.update', $this->mathTeacher), [
            'name' => 'X', 'email' => $this->scienceTeacher->email,
        ])->assertSessionHasErrors('email');
    }

    public function test_deleting_a_teacher_keeps_their_subjects_but_unassigns_them(): void
    {
        $this->delete(route('teachers.destroy', $this->mathTeacher))
            ->assertRedirect(route('teachers.index'));

        $this->assertDatabaseMissing('users', ['id' => $this->mathTeacher->id]);
        $this->assertDatabaseHas('subjects', ['id' => $this->math->id, 'teacher_id' => null]);
    }

    public function test_the_class_teacher_account_cannot_be_edited_or_deleted_from_the_teachers_screen(): void
    {
        $this->get(route('teachers.edit', $this->classTeacher->id))->assertNotFound();
        $this->delete(route('teachers.destroy', $this->classTeacher->id))->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $this->classTeacher->id]);
    }

    public function test_the_teachers_list_only_shows_subject_teachers(): void
    {
        $this->get(route('teachers.index'))
            ->assertOk()
            ->assertSee('Math Teacher Mia')
            ->assertSee('Science Teacher Sam')
            ->assertViewHas('teachers', function ($teachers) {
                // the class teacher is never listed as a manageable teacher
                return $teachers->count() === 3
                    && $teachers->pluck('id')->doesntContain($this->classTeacher->id);
            });
    }

    // ---------- subjects ----------

    public function test_class_teacher_can_create_a_subject_and_assign_a_teacher(): void
    {
        $this->post(route('subjects.store'), [
            'name' => 'Physics', 'code' => 'PHY101', 'teacher_id' => $this->idleTeacher->id,
        ])->assertRedirect(route('subjects.index'));

        $this->assertDatabaseHas('subjects', [
            'code' => 'PHY101', 'teacher_id' => $this->idleTeacher->id,
        ]);
    }

    public function test_a_subject_can_be_created_without_a_teacher(): void
    {
        $this->post(route('subjects.store'), ['name' => 'Art', 'code' => 'ART1', 'teacher_id' => ''])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('subjects', ['code' => 'ART1', 'teacher_id' => null]);
    }

    public function test_subject_validation(): void
    {
        // duplicate code
        $this->post(route('subjects.store'), ['name' => 'Dup', 'code' => 'MATH101'])
            ->assertSessionHasErrors('code');

        // required fields
        $this->post(route('subjects.store'), [])->assertSessionHasErrors(['name', 'code']);

        // teacher id that does not exist
        $this->post(route('subjects.store'), ['name' => 'X', 'code' => 'X1', 'teacher_id' => 99999])
            ->assertSessionHasErrors('teacher_id');

        // the class teacher is not a valid "subject teacher"
        $this->post(route('subjects.store'), ['name' => 'Y', 'code' => 'Y1', 'teacher_id' => $this->classTeacher->id])
            ->assertSessionHasErrors('teacher_id');

        $this->assertDatabaseMissing('subjects', ['code' => 'Y1']);
    }

    public function test_a_subject_can_be_updated_without_changing_its_code(): void
    {
        $this->put(route('subjects.update', $this->math), [
            'name' => 'Advanced Mathematics', 'code' => 'MATH101', 'teacher_id' => $this->mathTeacher->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('subjects', ['id' => $this->math->id, 'name' => 'Advanced Mathematics']);
    }

    public function test_reassigning_a_subject_moves_access_to_the_new_teacher(): void
    {
        $this->put(route('subjects.update', $this->math), [
            'name' => 'Mathematics', 'code' => 'MATH101', 'teacher_id' => $this->idleTeacher->id,
        ])->assertSessionHasNoErrors();

        // old teacher is locked out ...
        $this->actingAs($this->mathTeacher)
            ->get(route('attendance.mark', ['subject_id' => $this->math->id]))
            ->assertForbidden();

        // ... new teacher gets in
        $this->actingAs($this->idleTeacher)
            ->get(route('attendance.mark', ['subject_id' => $this->math->id]))
            ->assertOk();
    }

    public function test_a_subject_without_attendance_can_be_deleted(): void
    {
        $this->delete(route('subjects.destroy', $this->math))
            ->assertRedirect(route('subjects.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('subjects', ['id' => $this->math->id]);
    }

    public function test_a_subject_with_attendance_cannot_be_deleted(): void
    {
        $this->record($this->math, $this->rahul, 'present');

        $this->delete(route('subjects.destroy', $this->math))
            ->assertRedirect(route('subjects.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('subjects', ['id' => $this->math->id]);
        $this->assertDatabaseCount('attendances', 1);
    }

    // ---------- students ----------

    public function test_class_teacher_can_add_a_student(): void
    {
        $this->post(route('students.store'), [
            'name' => 'Amit Verma', 'roll_no' => '3', 'course' => 'BCA', 'year' => 2,
        ])->assertRedirect(route('students.index'));

        $this->assertDatabaseHas('students', ['roll_no' => '3', 'name' => 'Amit Verma']);
    }

    public function test_student_validation(): void
    {
        $this->post(route('students.store'), [
            'name' => 'Dup', 'roll_no' => '1', 'course' => 'BCA', 'year' => 2,
        ])->assertSessionHasErrors('roll_no');

        $this->post(route('students.store'), [
            'name' => 'Old', 'roll_no' => '50', 'course' => 'BCA', 'year' => 7,
        ])->assertSessionHasErrors('year');

        $this->post(route('students.store'), [
            'name' => 'Young', 'roll_no' => '51', 'course' => 'BCA', 'year' => 0,
        ])->assertSessionHasErrors('year');

        $this->post(route('students.store'), [])->assertSessionHasErrors(['name', 'roll_no', 'course', 'year']);
    }

    public function test_a_student_can_be_edited_keeping_their_own_roll_number(): void
    {
        $this->put(route('students.update', $this->rahul), [
            'name' => 'Rahul S.', 'roll_no' => '1', 'course' => 'BCA', 'year' => 3,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('students', ['id' => $this->rahul->id, 'name' => 'Rahul S.', 'year' => 3]);
    }

    public function test_editing_cannot_steal_another_students_roll_number(): void
    {
        $this->put(route('students.update', $this->rahul), [
            'name' => 'Rahul', 'roll_no' => '2', 'course' => 'BCA', 'year' => 2,
        ])->assertSessionHasErrors('roll_no');
    }

    public function test_deleting_a_student_also_deletes_their_attendance_in_every_subject(): void
    {
        $this->seedHistory();
        $this->assertDatabaseCount('attendances', 6);

        $this->delete(route('students.destroy', $this->rahul))->assertRedirect(route('students.index'));

        $this->assertDatabaseMissing('students', ['id' => $this->rahul->id]);
        $this->assertSame(0, Attendance::where('student_id', $this->rahul->id)->count());
        $this->assertDatabaseCount('attendances', 2);   // Priya's two rows remain
    }
}
