<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local/demo accounts. Safe to run more than once (it updates, not duplicates).
 * Every password is "password" -- never run this on a real server.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $this->user('Class Teacher', 'classteacher@example.com', User::ROLE_CLASS_TEACHER);
        $mehta = $this->user('Prof. Mehta', 'mehta@example.com', User::ROLE_SUBJECT_TEACHER);
        $kulkarni = $this->user('Prof. Kulkarni', 'kulkarni@example.com', User::ROLE_SUBJECT_TEACHER);

        Subject::updateOrCreate(['code' => 'PHP101'], ['name' => 'PHP & Laravel', 'teacher_id' => $mehta->id]);
        Subject::updateOrCreate(['code' => 'DBMS201'], ['name' => 'Database Systems', 'teacher_id' => $mehta->id]);
        Subject::updateOrCreate(['code' => 'JAVA301'], ['name' => 'Java Programming', 'teacher_id' => $kulkarni->id]);
    }

    private function user(string $name, string $email, string $role): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = 'password'; // hashed automatically by the model cast
        $user->role = $role;          // set explicitly: role is not mass-assignable
        $user->save();

        return $user;
    }
}
