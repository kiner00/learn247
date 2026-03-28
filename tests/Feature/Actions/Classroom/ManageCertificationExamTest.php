<?php

namespace Tests\Feature\Actions\Classroom;

use App\Actions\Classroom\ManageCertificationExam;
use App\Contracts\FileStorage;
use App\Models\CertificationQuestion;
use App\Models\CertificationQuestionOption;
use App\Models\Community;
use App\Models\CourseCertification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManageCertificationExamTest extends TestCase
{
    use RefreshDatabase;

    private function certData(array $overrides = []): array
    {
        return array_merge([
            'title'               => 'Test Exam',
            'cert_title'          => 'Test Certificate',
            'description'         => 'A description',
            'pass_score'          => 70,
            'randomize_questions' => false,
            'price'               => 0,
            'questions'           => [
                [
                    'question' => 'Question 1?',
                    'type'     => 'multiple_choice',
                    'options'  => [
                        ['label' => 'A', 'is_correct' => true],
                        ['label' => 'B', 'is_correct' => false],
                    ],
                ],
            ],
        ], $overrides);
    }

    public function test_store_creates_new_certification(): void
    {
        $community = Community::factory()->create();

        $action = app(ManageCertificationExam::class);
        $cert   = $action->store($community, $this->certData());

        $this->assertDatabaseHas('course_certifications', [
            'community_id' => $community->id,
            'title'        => 'Test Exam',
            'cert_title'   => 'Test Certificate',
            'pass_score'   => 70,
        ]);

        $this->assertCount(1, $cert->questions);
        $this->assertCount(2, $cert->questions->first()->options);
    }

    public function test_store_creates_multiple_questions(): void
    {
        $community = Community::factory()->create();
        $data      = $this->certData([
            'questions' => [
                [
                    'question' => 'Q1?',
                    'type'     => 'multiple_choice',
                    'options'  => [
                        ['label' => 'A', 'is_correct' => true],
                        ['label' => 'B', 'is_correct' => false],
                    ],
                ],
                [
                    'question' => 'Q2?',
                    'type'     => 'true_false',
                    'options'  => [
                        ['label' => 'True', 'is_correct' => true],
                        ['label' => 'False', 'is_correct' => false],
                    ],
                ],
            ],
        ]);

        $action = app(ManageCertificationExam::class);
        $cert   = $action->store($community, $data);

        $this->assertCount(2, $cert->questions);
        $this->assertEquals(0, $cert->questions[0]->position);
        $this->assertEquals(1, $cert->questions[1]->position);
    }

    public function test_store_updates_existing_certification(): void
    {
        $community = Community::factory()->create();
        $action    = app(ManageCertificationExam::class);

        $existing = $action->store($community, $this->certData());

        $updatedData              = $this->certData();
        $updatedData['title']     = 'Updated Title';
        $updatedData['questions'] = [
            [
                'question' => 'New Question?',
                'type'     => 'multiple_choice',
                'options'  => [
                    ['label' => 'X', 'is_correct' => true],
                    ['label' => 'Y', 'is_correct' => false],
                    ['label' => 'Z', 'is_correct' => false],
                ],
            ],
        ];

        $updated = $action->store($community, $updatedData, null, $existing);

        $this->assertEquals($existing->id, $updated->id);
        $this->assertEquals('Updated Title', $updated->title);
        $this->assertCount(1, $updated->questions);
        $this->assertCount(3, $updated->questions->first()->options);

        // Old questions should be deleted
        $this->assertDatabaseMissing('certification_questions', [
            'certification_id' => $existing->id,
            'question'         => 'Question 1?',
        ]);
    }

    public function test_store_with_cover_image(): void
    {
        Storage::fake(config('filesystems.default'));
        $community = Community::factory()->create();

        $file = UploadedFile::fake()->image('cover.jpg');

        $action = app(ManageCertificationExam::class);
        $cert   = $action->store($community, $this->certData(), $file);

        $this->assertNotNull($cert->cover_image);
    }

    public function test_store_with_remove_cover_image(): void
    {
        $community = Community::factory()->create();

        $mock = $this->mock(FileStorage::class);
        $mock->shouldReceive('delete')->once();
        $mock->shouldReceive('upload')->never();

        $existing = CourseCertification::factory()->create([
            'community_id' => $community->id,
            'cover_image'  => 'old-cover.jpg',
        ]);

        $data                       = $this->certData();
        $data['remove_cover_image'] = true;

        $action = new ManageCertificationExam($mock);
        $cert   = $action->store($community, $data, null, $existing);

        $this->assertNull($cert->cover_image);
    }

    public function test_destroy_deletes_certification(): void
    {
        $community = Community::factory()->create();
        $action    = app(ManageCertificationExam::class);
        $cert      = $action->store($community, $this->certData());

        $certId = $cert->id;
        $action->destroy($cert);

        $this->assertDatabaseMissing('course_certifications', ['id' => $certId]);
    }

    public function test_destroy_with_cover_image_calls_delete(): void
    {
        $mock = $this->mock(FileStorage::class);
        $mock->shouldReceive('delete')->once()->with('some-cover.jpg');

        $community = Community::factory()->create();
        $cert      = CourseCertification::factory()->create([
            'community_id' => $community->id,
            'cover_image'  => 'some-cover.jpg',
        ]);

        $action = new ManageCertificationExam($mock);
        $action->destroy($cert);

        $this->assertDatabaseMissing('course_certifications', ['id' => $cert->id]);
    }

    public function test_store_sets_price_and_affiliate_rate(): void
    {
        $community = Community::factory()->create();

        $data = $this->certData([
            'price'                     => 199.99,
            'affiliate_commission_rate' => 25,
        ]);

        $action = app(ManageCertificationExam::class);
        $cert   = $action->store($community, $data);

        $this->assertEquals(199.99, (float) $cert->price);
        $this->assertEquals(25, $cert->affiliate_commission_rate);
    }
}
