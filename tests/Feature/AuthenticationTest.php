<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpSchool;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase, SetsUpSchool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchool();
    }

    public function test_guests_are_redirected_to_login_from_every_protected_page(): void
    {
        $urls = [
            route('dashboard'),
            route('students.index'),
            route('students.create'),
            route('subjects.index'),
            route('teachers.index'),
            route('attendance.mark'),
            route('attendance.index'),
            route('reports.attendance'),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_guests_cannot_post_attendance_or_logout(): void
    {
        $this->post(route('attendance.store'), [
            'subject_id' => $this->math->id,
            'date' => now()->toDateString(),
        ])->assertRedirect(route('login'));

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_login_page_is_visible_to_guests(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Teacher Login');
    }

    public function test_user_can_log_in_with_correct_credentials(): void
    {
        $this->post(route('login'), [
            'email' => $this->mathTeacher->email,
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->mathTeacher);
    }

    public function test_login_redirects_to_the_page_the_user_originally_wanted(): void
    {
        $this->withSession(['url.intended' => route('attendance.mark')])
            ->post(route('login'), [
                'email' => $this->mathTeacher->email,
                'password' => 'password123',
            ])
            ->assertRedirect(route('attendance.mark'));
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->post(route('login'), [
            'email' => $this->mathTeacher->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unknown_email_is_rejected(): void
    {
        $this->post(route('login'), [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_empty_login_form_shows_validation_errors(): void
    {
        $this->post(route('login'), [])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'email' => $this->mathTeacher->email,
                'password' => 'wrong-password',
            ]);
        }

        // Even the CORRECT password is refused once the limit is hit.
        $this->post(route('login'), [
            'email' => $this->mathTeacher->email,
            'password' => 'password123',
        ])->assertStatus(429);

        $this->assertGuest();
    }

    public function test_logged_in_user_is_sent_away_from_the_login_page(): void
    {
        $this->actingAs($this->mathTeacher)
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs($this->mathTeacher)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_passwords_are_stored_hashed(): void
    {
        $this->assertNotSame('password123', $this->mathTeacher->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password123', $this->mathTeacher->password));
    }
}
