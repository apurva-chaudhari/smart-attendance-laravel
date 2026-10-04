<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpSchool;
use Tests\TestCase;

/**
 * Who may open which admin screens.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase, SetsUpSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchool();
    }

    public function test_subject_teacher_cannot_open_class_teacher_screens(): void
    {
        $this->actingAs($this->mathTeacher);

        $this->get(route('students.create'))->assertForbidden();
        $this->get(route('students.edit', $this->rahul))->assertForbidden();
        $this->get(route('subjects.index'))->assertForbidden();
        $this->get(route('subjects.create'))->assertForbidden();
        $this->get(route('subjects.edit', $this->math))->assertForbidden();
        $this->get(route('teachers.index'))->assertForbidden();
        $this->get(route('teachers.create'))->assertForbidden();
        $this->get(route('teachers.edit', $this->scienceTeacher))->assertForbidden();
    }

    public function test_subject_teacher_cannot_change_data_through_class_teacher_routes(): void
    {
        $this->actingAs($this->mathTeacher);

        $this->post(route('students.store'), [
            'name' => 'Hacker', 'roll_no' => '99', 'course' => 'BCA', 'year' => 1,
        ])->assertForbidden();

        $this->put(route('students.update', $this->rahul), [
            'name' => 'Changed', 'roll_no' => '1', 'course' => 'BCA', 'year' => 2,
        ])->assertForbidden();

        $this->delete(route('students.destroy', $this->rahul))->assertForbidden();

        $this->post(route('subjects.store'), [
            'name' => 'Sneaky', 'code' => 'SNK1',
        ])->assertForbidden();

        $this->delete(route('subjects.destroy', $this->chemistry))->assertForbidden();

        $this->post(route('teachers.store'), [
            'name' => 'Fake', 'email' => 'fake@example.com', 'password' => 'password123',
        ])->assertForbidden();

        $this->delete(route('teachers.destroy', $this->scienceTeacher))->assertForbidden();

        $this->assertDatabaseHas('students', ['id' => $this->rahul->id, 'name' => 'Rahul Sharma']);
        $this->assertDatabaseMissing('students', ['roll_no' => '99']);
        $this->assertDatabaseMissing('subjects', ['code' => 'SNK1']);
        $this->assertDatabaseHas('subjects', ['id' => $this->chemistry->id]);
        $this->assertDatabaseMissing('users', ['email' => 'fake@example.com']);
        $this->assertDatabaseHas('users', ['id' => $this->scienceTeacher->id]);
    }

    public function test_class_teacher_can_open_all_management_screens(): void
    {
        $this->actingAs($this->classTeacher);

        $this->get(route('students.index'))->assertOk();
        $this->get(route('students.create'))->assertOk();
        $this->get(route('students.edit', $this->rahul))->assertOk();
        $this->get(route('subjects.index'))->assertOk();
        $this->get(route('subjects.create'))->assertOk();
        $this->get(route('subjects.edit', $this->math))->assertOk();
        $this->get(route('teachers.index'))->assertOk();
        $this->get(route('teachers.create'))->assertOk();
        $this->get(route('teachers.edit', $this->mathTeacher))->assertOk();
    }

    public function test_subject_teacher_can_view_the_student_list_without_edit_buttons(): void
    {
        $this->actingAs($this->mathTeacher)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Rahul Sharma')
            ->assertDontSee('Add Student')
            ->assertDontSee('Delete');
    }

    public function test_class_teacher_sees_student_management_buttons(): void
    {
        $this->actingAs($this->classTeacher)
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Add Student')
            ->assertSee('Delete');
    }

    public function test_navigation_hides_admin_links_from_subject_teachers(): void
    {
        $this->actingAs($this->mathTeacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('teachers.index'))
            ->assertDontSee(route('subjects.index'))
            ->assertSee('Subject Teacher');

        $this->actingAs($this->classTeacher)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('teachers.index'))
            ->assertSee(route('subjects.index'))
            ->assertSee('Class Teacher');
    }

    public function test_role_middleware_rejects_unknown_roles(): void
    {
        $weird = $this->makeUser('janitor', 'Mr Janitor');

        $this->actingAs($weird)->get(route('students.create'))->assertForbidden();
        $this->actingAs($weird)->get(route('teachers.index'))->assertForbidden();

        // an unknown role is not a class teacher, so it gets no subjects
        $this->actingAs($weird)->get(route('attendance.mark', ['subject_id' => $this->math->id]))->assertForbidden();
    }
}
