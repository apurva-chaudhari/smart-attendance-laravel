<?php

namespace Tests\Concerns;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;

/**
 * A tiny school used by every feature test:
 *
 *   classTeacher   -> sees/does everything
 *   mathTeacher    -> teaches Mathematics only
 *   scienceTeacher -> teaches Chemistry only
 *   idleTeacher    -> subject teacher with NO subjects
 *   rahul, priya   -> students
 */
trait SetsUpSchool
{
    protected User $classTeacher;
    protected User $mathTeacher;
    protected User $scienceTeacher;
    protected User $idleTeacher;

    protected Subject $math;
    protected Subject $chemistry;

    protected Student $rahul;
    protected Student $priya;

    protected function setUpSchool(): void
    {
        $this->classTeacher = $this->makeUser(User::ROLE_CLASS_TEACHER, 'Class Teacher Ann');
        $this->mathTeacher = $this->makeUser(User::ROLE_SUBJECT_TEACHER, 'Math Teacher Mia');
        $this->scienceTeacher = $this->makeUser(User::ROLE_SUBJECT_TEACHER, 'Science Teacher Sam');
        $this->idleTeacher = $this->makeUser(User::ROLE_SUBJECT_TEACHER, 'Idle Teacher Ian');

        $this->math = Subject::create(['name' => 'Mathematics', 'code' => 'MATH101', 'teacher_id' => $this->mathTeacher->id]);
        $this->chemistry = Subject::create(['name' => 'Chemistry', 'code' => 'CHEM101', 'teacher_id' => $this->scienceTeacher->id]);

        $this->rahul = Student::create(['name' => 'Rahul Sharma', 'roll_no' => '1', 'course' => 'BCA', 'year' => 2]);
        $this->priya = Student::create(['name' => 'Priya Patil', 'roll_no' => '2', 'course' => 'BCA', 'year' => 2]);
    }

    protected function makeUser(string $role, string $name, string $password = 'password123'): User
    {
        // Factories bypass mass-assignment, so 'role' can be set here.
        return User::factory()->create([
            'name'     => $name,
            'role'     => $role,
            'password' => $password,
        ]);
    }

    protected function daysAgo(int $days): string
    {
        return now()->subDays($days)->toDateString();
    }

    protected function record(Subject $subject, Student $student, string $status, ?string $date = null): Attendance
    {
        return Attendance::create([
            'subject_id' => $subject->id,
            'student_id' => $student->id,
            'status'     => $status,
            'date'       => $date ?? now()->toDateString(),
        ]);
    }

    /**
     * Known data used by the report/dashboard tests:
     *
     *   Mathematics: Rahul P, P, A (2/3 = 66.7%)   Priya A (0/1 = 0%)
     *   Chemistry:   Rahul A (0/1 = 0%)            Priya P (1/1 = 100%)
     *   Overall:     Rahul 2/4 = 50%               Priya 1/2 = 50%
     */
    protected function seedHistory(): void
    {
        $this->record($this->math, $this->rahul, 'present', $this->daysAgo(2));
        $this->record($this->math, $this->rahul, 'present', $this->daysAgo(1));
        $this->record($this->math, $this->rahul, 'absent', $this->daysAgo(0));
        $this->record($this->math, $this->priya, 'absent', $this->daysAgo(2));

        $this->record($this->chemistry, $this->rahul, 'absent', $this->daysAgo(2));
        $this->record($this->chemistry, $this->priya, 'present', $this->daysAgo(2));
    }
}
