<?php

namespace Tests\Feature\Web;

use App\Models\Certificate;
use App\Models\CertificationAttempt;
use App\Models\CertificationPurchase;
use App\Models\CertificationQuestion;
use App\Models\CertificationQuestionOption;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CourseCertification;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CertificationExamControllerTest extends TestCase
{
    use RefreshDatabase;

    private function setupCommunity(User $owner): Community
    {
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
        ]);

        return $community;
    }

    private function setupMember(Community $community): User
    {
        $member = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        return $member;
    }

    private function createCertificationWithQuestions(Community $community, array $overrides = []): CourseCertification
    {
        $cert = CourseCertification::factory()->create(array_merge([
            'community_id' => $community->id,
        ], $overrides));

        $q1 = CertificationQuestion::factory()->create([
            'certification_id' => $cert->id,
            'question' => 'What is 2+2?',
            'position' => 0,
        ]);
        CertificationQuestionOption::factory()->correct()->create(['question_id' => $q1->id, 'label' => '4']);
        CertificationQuestionOption::factory()->create(['question_id' => $q1->id, 'label' => '5']);

        $q2 = CertificationQuestion::factory()->create([
            'certification_id' => $cert->id,
            'question' => 'What is 3+3?',
            'position' => 1,
        ]);
        CertificationQuestionOption::factory()->correct()->create(['question_id' => $q2->id, 'label' => '6']);
        CertificationQuestionOption::factory()->create(['question_id' => $q2->id, 'label' => '7']);

        return $cert;
    }

    private function certificationPayload(): array
    {
        return [
            'title' => 'PHP Basics Exam',
            'cert_title' => 'PHP Basics Certificate',
            'description' => 'Test your PHP knowledge',
            'pass_score' => 70,
            'price' => 0,
            'questions' => [
                [
                    'question' => 'What is PHP?',
                    'type' => 'multiple_choice',
                    'options' => [
                        ['label' => 'A language', 'is_correct' => true],
                        ['label' => 'A framework', 'is_correct' => false],
                    ],
                ],
                [
                    'question' => 'PHP is open source',
                    'type' => 'true_false',
                    'options' => [
                        ['label' => 'True', 'is_correct' => true],
                        ['label' => 'False', 'is_correct' => false],
                    ],
                ],
            ],
        ];
    }

    // ─── INDEX ──────────────────────────────────────────────────────────────────

    public function test_index_renders_certifications_page_for_member(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $member = $this->setupMember($community);
        $cert = $this->createCertificationWithQuestions($community);

        $response = $this->actingAs($member)->get(route('communities.certifications', $community));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Certifications/Index')
            ->has('certifications', 1)
            ->where('canManage', false)
        );
    }

    public function test_index_shows_manage_flag_for_owner(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $this->createCertificationWithQuestions($community);

        $response = $this->actingAs($owner)->get(route('communities.certifications', $community));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('canManage', true)
            ->has('issuedCertificates')
        );
    }

    public function test_index_includes_user_attempts_and_purchases(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $member = $this->setupMember($community);
        $cert = $this->createCertificationWithQuestions($community, ['price' => 100]);

        CertificationAttempt::factory()->passed()->create([
            'certification_id' => $cert->id,
            'user_id' => $member->id,
        ]);

        CertificationPurchase::factory()->paid()->create([
            'certification_id' => $cert->id,
            'user_id' => $member->id,
        ]);

        Certificate::factory()->create([
            'certification_id' => $cert->id,
            'user_id' => $member->id,
        ]);

        $response = $this->actingAs($member)->get(route('communities.certifications', $community));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('attempts')
            ->has('purchases')
            ->has('userCertificates')
        );
    }

    public function test_index_hides_correct_answers_for_non_managers(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $member = $this->setupMember($community);
        $this->createCertificationWithQuestions($community);

        $response = $this->actingAs($member)->get(route('communities.certifications', $community));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('certifications.0.questions.0.options.0.is_correct', false)
        );
    }

    // ─── STORE ──────────────────────────────────────────────────────────────────

    public function test_store_creates_certification_for_owner(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);

        $response = $this->actingAs($owner)->post(
            route('certification.store', $community),
            $this->certificationPayload()
        );

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Certification exam saved!');

        $this->assertDatabaseHas('course_certifications', [
            'community_id' => $community->id,
            'title' => 'PHP Basics Exam',
        ]);

        $cert = CourseCertification::where('community_id', $community->id)->first();
        $this->assertCount(2, $cert->questions);
        $this->assertCount(2, $cert->questions->first()->options);
    }

    public function test_store_is_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $member = $this->setupMember($community);

        $response = $this->actingAs($member)->post(
            route('certification.store', $community),
            $this->certificationPayload()
        );

        $response->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);

        $response = $this->actingAs($owner)->post(
            route('certification.store', $community),
            []
        );

        $response->assertSessionHasErrors(['title', 'cert_title', 'pass_score', 'questions']);
    }

    public function test_store_validates_pass_score_range(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);

        $payload = $this->certificationPayload();
        $payload['pass_score'] = 49; // below minimum of 50

        $response = $this->actingAs($owner)->post(
            route('certification.store', $community),
            $payload
        );

        $response->assertSessionHasErrors(['pass_score']);
    }

    // ─── UPDATE ─────────────────────────────────────────────────────────────────

    public function test_update_modifies_existing_certification(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $cert = $this->createCertificationWithQuestions($community);

        $payload = $this->certificationPayload();
        $payload['title'] = 'Updated Exam Title';

        $response = $this->actingAs($owner)->post(
            route('certification.update', [$community, $cert]),
            $payload
        );

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Certification exam updated!');

        $this->assertDatabaseHas('course_certifications', [
            'id' => $cert->id,
            'title' => 'Updated Exam Title',
        ]);
    }

    public function test_update_returns_404_for_wrong_community(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $community2 = $this->setupCommunity($owner);
        $cert = $this->createCertificationWithQuestions($community2);

        $response = $this->actingAs($owner)->post(
            route('certification.update', [$community, $cert]),
            $this->certificationPayload()
        );

        $response->assertNotFound();
    }

    public function test_update_is_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $cert = $this->createCertificationWithQuestions($community);
        $member = $this->setupMember($community);

        $response = $this->actingAs($member)->post(
            route('certification.update', [$community, $cert]),
            $this->certificationPayload()
        );

        $response->assertForbidden();
    }

    // ─── SUBMIT ─────────────────────────────────────────────────────────────────

    public function test_submit_records_attempt_and_returns_result(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $member = $this->setupMember($community);
        $cert = $this->createCertificationWithQuestions($community, ['pass_score' => 50]);

        $questions = $cert->questions()->with('options')->get();
        $answers = [];
        foreach ($questions as $q) {
            $correctOption = $q->options->firstWhere('is_correct', true);
            $answers[$q->id] = $correctOption->id;
        }

        $response = $this->actingAs($member)->post(
            route('certification.submit', [$community, $cert]),
            ['answers' => $answers]
        );

        $response->assertRedirect();
        $response->assertSessionHas('cert_exam_result');

        $this->assertDatabaseHas('certification_attempts', [
            'certification_id' => $cert->id,
            'user_id' => $member->id,
            'score' => 100,
            'passed' => true,
        ]);
    }

    public function test_submit_fails_for_paid_cert_without_purchase(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $member = $this->setupMember($community);
        $cert = $this->createCertificationWithQuestions($community, ['price' => 500]);

        $response = $this->actingAs($member)->post(
            route('certification.submit', [$community, $cert]),
            ['answers' => [1 => 1]]
        );

        $response->assertForbidden();
    }

    public function test_submit_succeeds_for_paid_cert_with_purchase(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $member = $this->setupMember($community);
        $cert = $this->createCertificationWithQuestions($community, ['price' => 500, 'pass_score' => 50]);

        CertificationPurchase::factory()->paid()->create([
            'user_id' => $member->id,
            'certification_id' => $cert->id,
        ]);

        $questions = $cert->questions()->with('options')->get();
        $answers = [];
        foreach ($questions as $q) {
            $answers[$q->id] = $q->options->firstWhere('is_correct', true)->id;
        }

        $response = $this->actingAs($member)->post(
            route('certification.submit', [$community, $cert]),
            ['answers' => $answers]
        );

        $response->assertRedirect();
        $response->assertSessionHas('cert_exam_result');
    }

    public function test_submit_validates_answers_required(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $member = $this->setupMember($community);
        $cert = $this->createCertificationWithQuestions($community);

        $response = $this->actingAs($member)->post(
            route('certification.submit', [$community, $cert]),
            []
        );

        $response->assertSessionHasErrors(['answers']);
    }

    public function test_submit_returns_404_for_wrong_community(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $community2 = $this->setupCommunity($owner);
        $member = $this->setupMember($community);
        $cert = $this->createCertificationWithQuestions($community2);

        $response = $this->actingAs($member)->post(
            route('certification.submit', [$community, $cert]),
            ['answers' => [1 => 1]]
        );

        $response->assertNotFound();
    }

    // ─── CHECKOUT ───────────────────────────────────────────────────────────────

    public function test_checkout_redirects_to_xendit_invoice(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $member = $this->setupMember($community);
        $cert = $this->createCertificationWithQuestions($community, ['price' => 500]);

        $this->mock(XenditService::class, function ($mock) {
            $mock->shouldReceive('createInvoice')->once()->andReturn([
                'id' => 'inv_test_123',
                'invoice_url' => 'https://checkout.xendit.co/test',
            ]);
        });

        $response = $this->actingAs($member)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('certification.checkout', [$community, $cert]));

        $response->assertStatus(409);

        $this->assertDatabaseHas('certification_purchases', [
            'user_id' => $member->id,
            'certification_id' => $cert->id,
            'status' => CertificationPurchase::STATUS_PENDING,
        ]);
    }

    public function test_checkout_returns_404_for_wrong_community(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $community2 = $this->setupCommunity($owner);
        $member = $this->setupMember($community);
        $cert = $this->createCertificationWithQuestions($community2, ['price' => 500]);

        $response = $this->actingAs($member)->post(
            route('certification.checkout', [$community, $cert])
        );

        $response->assertNotFound();
    }

    // ─── DESTROY ────────────────────────────────────────────────────────────────

    public function test_destroy_deletes_certification_for_owner(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $cert = $this->createCertificationWithQuestions($community);

        $response = $this->actingAs($owner)->delete(
            route('certification.destroy', [$community, $cert])
        );

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Certification exam deleted!');
        $this->assertDatabaseMissing('course_certifications', ['id' => $cert->id]);
    }

    public function test_destroy_is_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $cert = $this->createCertificationWithQuestions($community);
        $member = $this->setupMember($community);

        $response = $this->actingAs($member)->delete(
            route('certification.destroy', [$community, $cert])
        );

        $response->assertForbidden();
    }

    public function test_destroy_returns_404_for_wrong_community(): void
    {
        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);
        $community2 = $this->setupCommunity($owner);
        $cert = $this->createCertificationWithQuestions($community2);

        $response = $this->actingAs($owner)->delete(
            route('certification.destroy', [$community, $cert])
        );

        $response->assertNotFound();
    }

    // ─── INDEX: catch block logs and rethrows (lines 127-129) ───────────────

    public function test_index_catch_block_logs_error_and_rethrows(): void
    {
        Log::spy();

        $owner = User::factory()->create();
        $community = $this->setupCommunity($owner);

        // Force an error in the try block by intercepting the certifications query.
        $threw = false;
        \Illuminate\Support\Facades\DB::connection()->beforeExecuting(function ($query) {
            if (str_contains($query, 'course_certifications')) {
                throw new \RuntimeException('Simulated DB failure');
            }
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($owner)
                ->get(route('communities.certifications', $community));
        } catch (\RuntimeException $e) {
            $threw = true;
            $this->assertSame('Simulated DB failure', $e->getMessage());
        }

        $this->assertTrue($threw, 'Expected exception was not thrown');

        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg) => str_contains($msg, 'CertificationExamController@index failed'))
            ->once();
    }
}
