<?php

namespace Tests\Feature\Actions\Classroom;

use App\Actions\Classroom\SubmitCertificationExam;
use App\Models\Certificate;
use App\Models\CertificationAttempt;
use App\Models\CertificationQuestion;
use App\Models\CertificationQuestionOption;
use App\Models\Community;
use App\Models\CourseCertification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmitCertificationExamTest extends TestCase
{
    use RefreshDatabase;

    private function createCertWithQuestions(int $passScore = 70): array
    {
        $community = Community::factory()->create();
        $cert = CourseCertification::factory()->create([
            'community_id' => $community->id,
            'pass_score' => $passScore,
        ]);

        // Question 1: correct answer = option A
        $q1 = CertificationQuestion::factory()->create([
            'certification_id' => $cert->id,
            'position' => 0,
        ]);
        $q1CorrectOption = CertificationQuestionOption::factory()->correct()->create(['question_id' => $q1->id, 'label' => 'A']);
        $q1WrongOption = CertificationQuestionOption::factory()->create(['question_id' => $q1->id, 'label' => 'B']);

        // Question 2: correct answer = option C
        $q2 = CertificationQuestion::factory()->create([
            'certification_id' => $cert->id,
            'position' => 1,
        ]);
        $q2CorrectOption = CertificationQuestionOption::factory()->correct()->create(['question_id' => $q2->id, 'label' => 'C']);
        $q2WrongOption = CertificationQuestionOption::factory()->create(['question_id' => $q2->id, 'label' => 'D']);

        return [
            'cert' => $cert,
            'q1' => $q1,
            'q1Right' => $q1CorrectOption,
            'q1Wrong' => $q1WrongOption,
            'q2' => $q2,
            'q2Right' => $q2CorrectOption,
            'q2Wrong' => $q2WrongOption,
        ];
    }

    public function test_all_correct_answers_gives_100_and_passes(): void
    {
        $data = $this->createCertWithQuestions(70);
        $user = User::factory()->create();

        $action = app(SubmitCertificationExam::class);
        $result = $action->execute($user, $data['cert'], [
            $data['q1']->id => $data['q1Right']->id,
            $data['q2']->id => $data['q2Right']->id,
        ]);

        $this->assertEquals(100, $result['score']);
        $this->assertTrue($result['passed']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(2, $result['correct']);
        $this->assertNotNull($result['certificate_uuid']);

        $this->assertDatabaseHas('certification_attempts', [
            'user_id' => $user->id,
            'certification_id' => $data['cert']->id,
            'score' => 100,
            'passed' => true,
        ]);

        $this->assertDatabaseHas('certificates', [
            'user_id' => $user->id,
            'certification_id' => $data['cert']->id,
        ]);
    }

    public function test_all_wrong_answers_gives_0_and_fails(): void
    {
        $data = $this->createCertWithQuestions(70);
        $user = User::factory()->create();

        $action = app(SubmitCertificationExam::class);
        $result = $action->execute($user, $data['cert'], [
            $data['q1']->id => $data['q1Wrong']->id,
            $data['q2']->id => $data['q2Wrong']->id,
        ]);

        $this->assertEquals(0, $result['score']);
        $this->assertFalse($result['passed']);
        $this->assertEquals(0, $result['correct']);
        $this->assertNull($result['certificate_uuid']);

        $this->assertDatabaseMissing('certificates', [
            'user_id' => $user->id,
            'certification_id' => $data['cert']->id,
        ]);
    }

    public function test_partial_correct_answers_calculates_score(): void
    {
        $data = $this->createCertWithQuestions(70);
        $user = User::factory()->create();

        $action = app(SubmitCertificationExam::class);
        $result = $action->execute($user, $data['cert'], [
            $data['q1']->id => $data['q1Right']->id,
            $data['q2']->id => $data['q2Wrong']->id,
        ]);

        $this->assertEquals(50, $result['score']);
        $this->assertFalse($result['passed']); // 50 < 70
        $this->assertEquals(1, $result['correct']);
    }

    public function test_passing_creates_certificate(): void
    {
        $data = $this->createCertWithQuestions(50);
        $user = User::factory()->create();

        $action = app(SubmitCertificationExam::class);
        $result = $action->execute($user, $data['cert'], [
            $data['q1']->id => $data['q1Right']->id,
            $data['q2']->id => $data['q2Wrong']->id,
        ]);

        $this->assertTrue($result['passed']); // 50 >= 50
        $this->assertNotNull($result['certificate_uuid']);
    }

    public function test_re_passing_updates_existing_certificate(): void
    {
        $data = $this->createCertWithQuestions(50);
        $user = User::factory()->create();

        $action = app(SubmitCertificationExam::class);

        // First pass
        $result1 = $action->execute($user, $data['cert'], [
            $data['q1']->id => $data['q1Right']->id,
            $data['q2']->id => $data['q2Right']->id,
        ]);

        // Update cert title
        $data['cert']->update(['cert_title' => 'Updated Title']);

        // Second pass
        $result2 = $action->execute($user, $data['cert']->fresh(), [
            $data['q1']->id => $data['q1Right']->id,
            $data['q2']->id => $data['q2Right']->id,
        ]);

        // Should reuse same certificate UUID
        $this->assertEquals($result1['certificate_uuid'], $result2['certificate_uuid']);

        // But cert_title should be updated
        $cert = Certificate::where('uuid', $result2['certificate_uuid'])->first();
        $this->assertEquals('Updated Title', $cert->cert_title);

        // Should have two attempts
        $this->assertEquals(2, CertificationAttempt::where('user_id', $user->id)->count());

        // But only one certificate
        $this->assertEquals(1, Certificate::where('user_id', $user->id)->count());
    }

    public function test_missing_answers_are_counted_as_wrong(): void
    {
        $data = $this->createCertWithQuestions(50);
        $user = User::factory()->create();

        $action = app(SubmitCertificationExam::class);
        $result = $action->execute($user, $data['cert'], [
            // Only answer q1, skip q2
            $data['q1']->id => $data['q1Right']->id,
        ]);

        $this->assertEquals(50, $result['score']); // 1 out of 2
        $this->assertEquals(1, $result['correct']);
        $this->assertEquals(2, $result['total']);
    }

    public function test_empty_answers_gives_zero(): void
    {
        $data = $this->createCertWithQuestions(50);
        $user = User::factory()->create();

        $action = app(SubmitCertificationExam::class);
        $result = $action->execute($user, $data['cert'], []);

        $this->assertEquals(0, $result['score']);
        $this->assertFalse($result['passed']);
        $this->assertEquals(0, $result['correct']);
    }
}
