<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Subject;
use App\Support\LowAttendanceNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetsUpSchool;
use Tests\TestCase;

class LowAttendanceNoticeTest extends TestCase
{
    use RefreshDatabase, SetsUpSchool;

    private Student $amit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchool();

        $this->amit = Student::create(['name' => 'Amit Verma', 'roll_no' => '3', 'course' => 'BCA', 'year' => 2]);
    }

    // ---------- helpers ----------

    /** Creates $present + $absent classes, one per day, starting $startDaysAgo days back. */
    private function classes(Subject $subject, Student $student, int $present, int $absent, int $startDaysAgo = 0): void
    {
        $day = $startDaysAgo;

        for ($i = 0; $i < $present; $i++) {
            $this->record($subject, $student, 'present', $this->daysAgo($day++));
        }
        for ($i = 0; $i < $absent; $i++) {
            $this->record($subject, $student, 'absent', $this->daysAgo($day++));
        }
    }

    private function noticeFrom($response): LowAttendanceNotice
    {
        return $response->viewData('notice');
    }

    /** Student names in the order they are listed. */
    private function names(LowAttendanceNotice $notice): array
    {
        return $notice->rows()->map(fn ($row) => $row['student']->name)->all();
    }

    private function openAsClassTeacher(array $query = [])
    {
        return $this->actingAs($this->classTeacher)->get(route('reports.low-attendance', $query));
    }

    // ---------- access ----------

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('reports.low-attendance'))->assertRedirect(route('login'));
        $this->get(route('reports.low-attendance.pdf'))->assertRedirect(route('login'));
    }

    public function test_both_roles_can_open_the_notice_page(): void
    {
        $this->actingAs($this->classTeacher)->get(route('reports.low-attendance'))->assertOk()->assertSee('LOW ATTENDANCE NOTICE');
        $this->actingAs($this->mathTeacher)->get(route('reports.low-attendance'))->assertOk()->assertSee('LOW ATTENDANCE NOTICE');
    }

    public function test_subject_teacher_cannot_ask_for_another_teachers_subject(): void
    {
        $this->actingAs($this->mathTeacher)
            ->get(route('reports.low-attendance', ['subject_id' => $this->chemistry->id]))
            ->assertForbidden();

        $this->actingAs($this->mathTeacher)
            ->get(route('reports.low-attendance.pdf', ['subject_id' => $this->chemistry->id]))
            ->assertForbidden();
    }

    public function test_an_unknown_subject_is_forbidden(): void
    {
        $this->openAsClassTeacher(['subject_id' => 99999])->assertForbidden();
    }

    public function test_a_teacher_without_subjects_gets_an_empty_notice_not_an_error(): void
    {
        $response = $this->actingAs($this->idleTeacher)->get(route('reports.low-attendance'));

        $response->assertOk()->assertSee('No subjects are assigned to you yet');
        $this->assertTrue($this->noticeFrom($response)->rows()->isEmpty());

        $this->actingAs($this->idleTeacher)->get(route('reports.low-attendance.pdf'))->assertOk();
    }

    public function test_notice_links_are_shown_on_the_dashboard_and_report_pages(): void
    {
        $this->actingAs($this->classTeacher)->get(route('dashboard'))->assertSee(route('reports.low-attendance'));
        $this->actingAs($this->classTeacher)->get(route('reports.attendance'))->assertSee(route('reports.low-attendance'));
    }

    // ---------- who is listed: scope ----------

    public function test_class_teacher_overall_notice_combines_all_subjects_and_shows_weak_subjects(): void
    {
        $this->classes($this->math, $this->rahul, 2, 3);       // Maths 40%
        $this->classes($this->chemistry, $this->rahul, 5, 0);  // Chemistry 100%  -> overall 7/10 = 70%
        $this->classes($this->math, $this->priya, 5, 0);
        $this->classes($this->chemistry, $this->priya, 5, 0);  // Priya 100%

        $notice = $this->noticeFrom($this->openAsClassTeacher());

        $this->assertSame(['Rahul Sharma'], $this->names($notice));

        $row = $notice->rows()->first();
        $this->assertEquals(70.0, $row['percentage']);
        $this->assertSame(7, $row['present']);
        $this->assertSame(10, $row['total']);
        $this->assertSame(['Mathematics'], $row['weak']->pluck('subject')->all());
        $this->assertEquals(40.0, $row['weak']->first()['percentage']);
    }

    public function test_a_subject_teacher_only_counts_their_own_subject(): void
    {
        $this->classes($this->math, $this->rahul, 2, 3);       // 40% in Maths
        $this->classes($this->chemistry, $this->rahul, 5, 0);  // 100% in Chemistry

        $maths = $this->noticeFrom($this->actingAs($this->mathTeacher)->get(route('reports.low-attendance')));
        $this->assertSame(['Rahul Sharma'], $this->names($maths));
        $this->assertEquals(40.0, $maths->rows()->first()['percentage']);

        $chem = $this->noticeFrom($this->actingAs($this->scienceTeacher)->get(route('reports.low-attendance')));
        $this->assertSame([], $this->names($chem));
    }

    public function test_class_teacher_can_pick_a_single_subject(): void
    {
        $this->classes($this->math, $this->rahul, 2, 3);
        $this->classes($this->chemistry, $this->rahul, 5, 0);

        $maths = $this->noticeFrom($this->openAsClassTeacher(['subject_id' => $this->math->id]));
        $this->assertSame(['Rahul Sharma'], $this->names($maths));
        $this->assertSame('Mathematics', $maths->scopeLabel);
        $this->assertTrue($maths->rows()->first()['weak']->isEmpty(), 'no breakdown for a single subject');

        $chem = $this->noticeFrom($this->openAsClassTeacher(['subject_id' => $this->chemistry->id]));
        $this->assertSame([], $this->names($chem));
    }

    public function test_scope_label_describes_what_the_notice_covers(): void
    {
        $this->assertSame('All subjects', $this->noticeFrom($this->openAsClassTeacher())->scopeLabel);

        $this->assertSame(
            'Mathematics',
            $this->noticeFrom($this->actingAs($this->mathTeacher)->get(route('reports.low-attendance')))->scopeLabel
        );

        $physics = Subject::create(['name' => 'Physics', 'code' => 'PHY101', 'teacher_id' => $this->mathTeacher->id]);

        $this->assertSame(
            'Mathematics, Physics',
            $this->noticeFrom($this->actingAs($this->mathTeacher)->get(route('reports.low-attendance')))->scopeLabel
        );
        $this->assertNotNull($physics);
    }

    // ---------- who is listed: rules ----------

    public function test_a_student_exactly_at_the_threshold_is_not_listed(): void
    {
        $this->classes($this->math, $this->amit, 3, 1);    // exactly 75%
        $this->classes($this->math, $this->priya, 1, 1);   // 50%

        $notice = $this->noticeFrom($this->openAsClassTeacher());

        $this->assertSame(['Priya Patil'], $this->names($notice));
    }

    public function test_a_higher_threshold_lists_more_students(): void
    {
        $this->classes($this->math, $this->amit, 3, 1);    // 75%

        $notice = $this->noticeFrom($this->openAsClassTeacher(['threshold' => 80]));

        $this->assertSame(['Amit Verma'], $this->names($notice));
        $this->assertSame(80, $notice->threshold);
    }

    public function test_a_lower_threshold_lists_fewer_students(): void
    {
        $this->classes($this->math, $this->priya, 2, 1);   // 66.7%
        $this->classes($this->math, $this->rahul, 1, 3);   // 25%

        $this->assertSame(
            ['Rahul Sharma', 'Priya Patil'],
            $this->names($this->noticeFrom($this->openAsClassTeacher()))
        );
        $this->assertSame(
            ['Rahul Sharma'],
            $this->names($this->noticeFrom($this->openAsClassTeacher(['threshold' => 60])))
        );
    }

    public function test_blank_form_fields_fall_back_to_the_defaults(): void
    {
        // a browser sends threshold= and min_classes= when the boxes are emptied
        $notice = $this->noticeFrom($this->openAsClassTeacher(['threshold' => '', 'min_classes' => '', 'from' => '', 'to' => '']));

        $this->assertSame(75, $notice->threshold);
        $this->assertSame(1, $notice->minClasses);
        $this->assertNull($notice->from);
        $this->assertNull($notice->to);
    }

    public function test_invalid_thresholds_are_rejected(): void
    {
        foreach ([0, 101, -5, 'abc', '75.5'] as $bad) {
            $this->openAsClassTeacher(['threshold' => $bad])->assertSessionHasErrors('threshold');
        }
    }

    public function test_invalid_minimum_classes_are_rejected(): void
    {
        foreach ([0, 201, 'x'] as $bad) {
            $this->openAsClassTeacher(['min_classes' => $bad])->assertSessionHasErrors('min_classes');
        }
    }

    public function test_a_non_numeric_subject_id_is_rejected(): void
    {
        $this->openAsClassTeacher(['subject_id' => 'abc'])->assertSessionHasErrors('subject_id');
    }

    public function test_students_with_too_few_classes_are_hidden_by_the_minimum_classes_rule(): void
    {
        $this->classes($this->math, $this->priya, 0, 2);   // 0% but only 2 classes
        $this->classes($this->math, $this->rahul, 1, 9);   // 10% over 10 classes

        $this->assertSame(
            ['Priya Patil', 'Rahul Sharma'],
            $this->names($this->noticeFrom($this->openAsClassTeacher()))
        );
        $this->assertSame(
            ['Rahul Sharma'],
            $this->names($this->noticeFrom($this->openAsClassTeacher(['min_classes' => 5])))
        );
    }

    public function test_students_with_no_attendance_records_are_never_listed(): void
    {
        $this->classes($this->math, $this->rahul, 0, 2);

        $names = $this->names($this->noticeFrom($this->openAsClassTeacher()));

        $this->assertSame(['Rahul Sharma'], $names);   // Priya and Amit have no records at all
    }

    public function test_the_date_range_limits_which_classes_are_counted(): void
    {
        $this->classes($this->math, $this->rahul, 5, 0, 0);    // 5 recent classes, all present
        $this->classes($this->math, $this->rahul, 0, 10, 60);  // 10 old classes, all absent

        // everything: 5/15 = 33.3%
        $this->assertSame(['Rahul Sharma'], $this->names($this->noticeFrom($this->openAsClassTeacher())));

        // only the last 10 days: 5/5 = 100%
        $recent = $this->noticeFrom($this->openAsClassTeacher(['from' => $this->daysAgo(10)]));
        $this->assertSame([], $this->names($recent));

        // only up to 50 days ago: just the old absences, 0/10
        $old = $this->noticeFrom($this->openAsClassTeacher(['to' => $this->daysAgo(50)]));
        $this->assertSame(['Rahul Sharma'], $this->names($old));
        $this->assertEquals(0.0, $old->rows()->first()['percentage']);
    }

    public function test_a_from_date_after_the_to_date_is_rejected(): void
    {
        $this->openAsClassTeacher(['from' => $this->daysAgo(0), 'to' => $this->daysAgo(5)])
            ->assertSessionHasErrors('to');
    }

    public function test_the_date_range_boundaries_are_inclusive(): void
    {
        $this->record($this->math, $this->rahul, 'absent', $this->daysAgo(3));

        $notice = $this->noticeFrom($this->openAsClassTeacher(['from' => $this->daysAgo(3), 'to' => $this->daysAgo(3)]));

        $this->assertSame(['Rahul Sharma'], $this->names($notice));
    }

    public function test_students_are_listed_worst_first_then_by_roll_number(): void
    {
        $ten = Student::create(['name' => 'Tenth Student', 'roll_no' => '10', 'course' => 'BCA', 'year' => 2]);

        $this->classes($this->math, $ten, 1, 3);           // 25%, roll 10
        $this->classes($this->math, $this->priya, 1, 3);   // 25%, roll 2
        $this->classes($this->math, $this->rahul, 1, 3);   // 25%, roll 1
        $this->classes($this->math, $this->amit, 0, 4);    // 0%

        $this->assertSame(
            ['Amit Verma', 'Rahul Sharma', 'Priya Patil', 'Tenth Student'],
            $this->names($this->noticeFrom($this->openAsClassTeacher()))
        );
    }

    public function test_the_weak_subject_breakdown_only_lists_subjects_below_the_threshold(): void
    {
        $this->classes($this->math, $this->rahul, 0, 4);        // 0%   -> weak
        $this->classes($this->chemistry, $this->rahul, 3, 1);   // 75%  -> not weak

        $row = $this->noticeFrom($this->openAsClassTeacher())->rows()->first();

        $this->assertEquals(37.5, $row['percentage']);
        $this->assertSame(['Mathematics'], $row['weak']->pluck('subject')->all());
    }

    public function test_the_minimum_classes_rule_also_applies_to_the_breakdown(): void
    {
        $this->classes($this->math, $this->rahul, 0, 4);
        $this->classes($this->chemistry, $this->rahul, 3, 1);

        // 8 classes overall (listed), but only 4 per subject (< 5), so no subject is called out
        $row = $this->noticeFrom($this->openAsClassTeacher(['min_classes' => 5]))->rows()->first();

        $this->assertNotNull($row);
        $this->assertTrue($row['weak']->isEmpty());
    }

    // ---------- WhatsApp message ----------

    public function test_the_whatsapp_message_contains_all_the_details(): void
    {
        $this->classes($this->math, $this->rahul, 2, 3);

        $message = $this->openAsClassTeacher()->viewData('message');

        $this->assertStringContainsString('*LOW ATTENDANCE NOTICE*', $message);
        $this->assertStringContainsString('Subject: All subjects', $message);
        $this->assertStringContainsString('below 75%', $message);
        $this->assertStringContainsString('Rahul Sharma (Roll 1)', $message);
        $this->assertStringContainsString('*40%*', $message);
        $this->assertStringContainsString('(2/5 classes)', $message);
        $this->assertStringContainsString('Total: 1 student(s)', $message);
        $this->assertStringContainsString('Class Teacher Ann', $message);
        $this->assertStringContainsString(now()->format('d-m-Y'), $message);
    }

    public function test_percentages_are_formatted_without_pointless_decimals(): void
    {
        $this->classes($this->math, $this->priya, 1, 1);   // 50
        $this->classes($this->math, $this->rahul, 2, 1);   // 66.7

        $message = $this->openAsClassTeacher()->viewData('message');

        $this->assertStringContainsString('*50%*', $message);
        $this->assertStringContainsString('*66.7%*', $message);
        $this->assertStringNotContainsString('50.0', $message);
    }

    public function test_the_message_lists_weak_subjects_for_combined_notices(): void
    {
        $this->classes($this->math, $this->rahul, 0, 4);
        $this->classes($this->chemistry, $this->rahul, 3, 1);

        $message = $this->openAsClassTeacher()->viewData('message');

        $this->assertStringContainsString('Low in: Mathematics 0%', $message);
        $this->assertStringNotContainsString('Chemistry 75%', $message);
    }

    public function test_the_message_is_signed_by_the_logged_in_teacher_and_names_their_subject(): void
    {
        $this->classes($this->math, $this->rahul, 1, 3);

        $message = $this->actingAs($this->mathTeacher)->get(route('reports.low-attendance'))->viewData('message');

        $this->assertStringContainsString('Subject: Mathematics', $message);
        $this->assertStringContainsString('Math Teacher Mia', $message);
        $this->assertStringNotContainsString('Chemistry', $message);
    }

    public function test_when_nobody_is_below_the_threshold_the_message_is_a_good_news_note(): void
    {
        $this->classes($this->math, $this->rahul, 5, 0);

        $response = $this->openAsClassTeacher();

        $this->assertStringContainsString('No student is below 75%', $response->viewData('message'));
        $this->assertNull($response->viewData('shareUrl'));
        $response->assertSee('Good news! No student is below 75%')
            ->assertDontSee('Share this notice');
    }

    public function test_period_labels_for_every_combination_of_dates(): void
    {
        $make = fn (?string $from, ?string $to) => new LowAttendanceNotice(
            subjects: collect([$this->math]), scopeLabel: 'Mathematics', from: $from, to: $to
        );

        $this->assertSame('01-09-2026 to 30-09-2026', $make('2026-09-01', '2026-09-30')->periodLabel());
        $this->assertSame('Up to 30-09-2026', $make(null, '2026-09-30')->periodLabel());
        $this->assertSame(
            'From 01-09-2026 to ' . now()->format('d-m-Y'),
            $make('2026-09-01', null)->periodLabel()
        );
        $this->assertSame('All classes up to ' . now()->format('d-m-Y'), $make(null, null)->periodLabel());
    }

    public function test_the_share_link_carries_exactly_the_message(): void
    {
        $this->classes($this->math, $this->rahul, 1, 3);

        $response = $this->openAsClassTeacher();
        $url = $response->viewData('shareUrl');
        $prefix = 'https://wa.me/?text=';

        $this->assertStringStartsWith($prefix, $url);
        $this->assertSame($response->viewData('message'), rawurldecode(substr($url, strlen($prefix))));
    }

    public function test_the_share_link_is_dropped_when_the_message_is_too_long(): void
    {
        for ($i = 1; $i <= 150; $i++) {
            $student = Student::create([
                'name' => 'Student Number ' . $i, 'roll_no' => (string) (100 + $i), 'course' => 'BCA', 'year' => 2,
            ]);
            $this->record($this->math, $student, 'absent', $this->daysAgo(0));
        }

        $response = $this->openAsClassTeacher();

        $this->assertNull($response->viewData('shareUrl'));
        $this->assertStringContainsString('Student Number 150', $response->viewData('message'));   // text is still there to copy
        $response->assertOk()->assertSee('too long for a share link');
    }

    // ---------- page ----------

    public function test_the_page_offers_whatsapp_pdf_and_print_actions(): void
    {
        $this->classes($this->math, $this->rahul, 1, 3);

        $this->openAsClassTeacher()
            ->assertOk()
            ->assertSee('Rahul Sharma')
            ->assertSee('25%')
            ->assertSee('https://wa.me/?text=')
            ->assertSee(route('reports.low-attendance.pdf'))
            ->assertSee('Copy message')
            ->assertSee('Print this page');
    }

    public function test_the_pdf_link_keeps_the_current_filters(): void
    {
        $this->classes($this->math, $this->rahul, 1, 3);

        $this->openAsClassTeacher(['threshold' => 60, 'subject_id' => $this->math->id])
            ->assertSee('threshold=60', false)
            ->assertSee('subject_id=' . $this->math->id, false);
    }

    public function test_student_names_cannot_inject_html_into_the_page_or_the_message_box(): void
    {
        $evil = Student::create([
            'name' => '</textarea><script>alert(1)</script>', 'roll_no' => '66', 'course' => 'BCA', 'year' => 2,
        ]);
        $this->record($this->math, $evil, 'absent', $this->daysAgo(0));

        $this->openAsClassTeacher()
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    // ---------- PDF ----------

    public function test_the_pdf_is_a_real_pdf_file(): void
    {
        $this->classes($this->math, $this->rahul, 1, 3);

        $response = $this->openPdf();

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('low-attendance-notice-', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_the_pdf_also_works_when_nobody_is_below_the_threshold(): void
    {
        $response = $this->openPdf();

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_a_subject_teacher_can_download_their_own_pdf(): void
    {
        $this->classes($this->math, $this->rahul, 1, 3);

        $response = $this->actingAs($this->mathTeacher)->get(route('reports.low-attendance.pdf'));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_invalid_filters_are_rejected_for_the_pdf_too(): void
    {
        $this->openPdf(['threshold' => 500])->assertSessionHasErrors('threshold');
    }

    public function test_the_pdf_template_contains_the_notice_text(): void
    {
        $this->classes($this->math, $this->rahul, 1, 3);

        $notice = new LowAttendanceNotice(subjects: collect([$this->math]), scopeLabel: 'Mathematics');
        $html = view('reports.low-attendance-pdf', [
            'notice' => $notice, 'issuedBy' => 'Class Teacher Ann', 'generatedOn' => now(),
        ])->render();

        $this->assertStringContainsString('LOW ATTENDANCE NOTICE', $html);
        $this->assertStringContainsString('Mathematics', $html);
        $this->assertStringContainsString('Rahul Sharma', $html);
        $this->assertStringContainsString('25%', $html);
        $this->assertStringContainsString('Attendance below 75%', $html);
        $this->assertStringContainsString('Class Teacher Ann', $html);
    }

    public function test_the_pdf_template_says_so_when_nobody_is_listed(): void
    {
        $notice = new LowAttendanceNotice(subjects: collect([$this->math]), scopeLabel: 'Mathematics');
        $html = view('reports.low-attendance-pdf', [
            'notice' => $notice, 'issuedBy' => 'Class Teacher Ann', 'generatedOn' => now(),
        ])->render();

        $this->assertStringContainsString('No student is below 75%', $html);
        $this->assertStringNotContainsString('Rahul Sharma', $html);
    }

    public function test_the_pdf_template_escapes_student_names(): void
    {
        $evil = Student::create(['name' => '<b>Bold</b>', 'roll_no' => '77', 'course' => 'BCA', 'year' => 2]);
        $this->record($this->math, $evil, 'absent', $this->daysAgo(0));

        $notice = new LowAttendanceNotice(subjects: collect([$this->math]), scopeLabel: 'Mathematics');
        $html = view('reports.low-attendance-pdf', [
            'notice' => $notice, 'issuedBy' => 'X', 'generatedOn' => now(),
        ])->render();

        $this->assertStringNotContainsString('<b>Bold</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;Bold&lt;/b&gt;', $html);
    }

    private function openPdf(array $query = [])
    {
        return $this->actingAs($this->classTeacher)->get(route('reports.low-attendance.pdf', $query));
    }
}
