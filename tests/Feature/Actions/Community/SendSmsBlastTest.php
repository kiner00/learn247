<?php

namespace Tests\Feature\Actions\Community;

use App\Actions\Community\SendSmsBlast;
use App\Contracts\SmsProvider;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendSmsBlastTest extends TestCase
{
    use RefreshDatabase;

    private function mockSmsProvider(array $returnValue = ['sent' => 1, 'failed' => 0, 'errors' => []]): SmsProvider
    {
        $mock = $this->createMock(SmsProvider::class);
        $mock->method('blast')->willReturn($returnValue);

        return $mock;
    }

    public function test_sends_to_all_members_with_phones(): void
    {
        $community = Community::factory()->create();

        $userWithPhone = User::factory()->create(['phone' => '+639123456789']);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $userWithPhone->id]);

        $userNoPhone = User::factory()->create(['phone' => null]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $userNoPhone->id]);

        $smsMock = $this->createMock(SmsProvider::class);
        $smsMock->expects($this->once())
            ->method('blast')
            ->with(
                $this->identicalTo($community),
                $this->callback(fn ($numbers) => count($numbers) === 1),
                'Hello members!'
            )
            ->willReturn(['sent' => 1, 'failed' => 0, 'errors' => []]);

        $action = new SendSmsBlast($smsMock);
        $result = $action->execute($community, [
            'filter_type' => 'all',
            'message'     => 'Hello members!',
        ]);

        $this->assertSame(1, $result['sent']);
        $this->assertFalse($result['no_recipients']);
    }

    public function test_returns_no_recipients_when_nobody_has_phone(): void
    {
        $community = Community::factory()->create();

        $user = User::factory()->create(['phone' => null]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $action = new SendSmsBlast($this->mockSmsProvider());
        $result = $action->execute($community, [
            'filter_type' => 'all',
            'message'     => 'Hello!',
        ]);

        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['failed']);
        $this->assertTrue($result['no_recipients']);
    }

    public function test_filters_new_members_by_days(): void
    {
        $community = Community::factory()->create();

        $newUser = User::factory()->create(['phone' => '+639111111111']);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $newUser->id,
            'joined_at'    => now()->subDays(3),
        ]);

        $oldUser = User::factory()->create(['phone' => '+639222222222']);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $oldUser->id,
            'joined_at'    => now()->subDays(30),
        ]);

        $smsMock = $this->createMock(SmsProvider::class);
        $smsMock->expects($this->once())
            ->method('blast')
            ->with(
                $this->identicalTo($community),
                $this->callback(fn ($numbers) => count($numbers) === 1),
                'Welcome!'
            )
            ->willReturn(['sent' => 1, 'failed' => 0, 'errors' => []]);

        $action = new SendSmsBlast($smsMock);
        $result = $action->execute($community, [
            'filter_type' => 'new_members',
            'filter_days' => 7,
            'message'     => 'Welcome!',
        ]);

        $this->assertSame(1, $result['sent']);
        $this->assertFalse($result['no_recipients']);
    }

    public function test_filters_by_course_enrollment(): void
    {
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id]);

        $enrolledUser = User::factory()->create(['phone' => '+639333333333']);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $enrolledUser->id]);
        CourseEnrollment::create([
            'user_id'   => $enrolledUser->id,
            'course_id' => $course->id,
            'status'    => CourseEnrollment::STATUS_PAID,
        ]);

        $notEnrolledUser = User::factory()->create(['phone' => '+639444444444']);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $notEnrolledUser->id]);

        $smsMock = $this->createMock(SmsProvider::class);
        $smsMock->expects($this->once())
            ->method('blast')
            ->with(
                $this->identicalTo($community),
                $this->callback(fn ($numbers) => count($numbers) === 1),
                'Course update!'
            )
            ->willReturn(['sent' => 1, 'failed' => 0, 'errors' => []]);

        $action = new SendSmsBlast($smsMock);
        $result = $action->execute($community, [
            'filter_type'      => 'course',
            'filter_course_id' => $course->id,
            'message'          => 'Course update!',
        ]);

        $this->assertSame(1, $result['sent']);
    }

    public function test_filters_out_short_phone_numbers(): void
    {
        $community = Community::factory()->create();

        $user = User::factory()->create(['phone' => '123']); // too short
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $action = new SendSmsBlast($this->mockSmsProvider());
        $result = $action->execute($community, [
            'filter_type' => 'all',
            'message'     => 'Test',
        ]);

        $this->assertTrue($result['no_recipients']);
    }

    public function test_strips_non_digits_from_phone_numbers(): void
    {
        $community = Community::factory()->create();

        $user = User::factory()->create(['phone' => '+63 (912) 345-6789']);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $smsMock = $this->createMock(SmsProvider::class);
        $smsMock->expects($this->once())
            ->method('blast')
            ->with(
                $this->identicalTo($community),
                $this->callback(fn ($numbers) => $numbers[0] === '639123456789'),
                'Test'
            )
            ->willReturn(['sent' => 1, 'failed' => 0, 'errors' => []]);

        $action = new SendSmsBlast($smsMock);
        $action->execute($community, [
            'filter_type' => 'all',
            'message'     => 'Test',
        ]);
    }

    public function test_new_members_filter_defaults_to_seven_days(): void
    {
        $community = Community::factory()->create();

        $user = User::factory()->create(['phone' => '+639555555555']);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'joined_at'    => now()->subDays(6),
        ]);

        $smsMock = $this->createMock(SmsProvider::class);
        $smsMock->expects($this->once())
            ->method('blast')
            ->willReturn(['sent' => 1, 'failed' => 0, 'errors' => []]);

        $action = new SendSmsBlast($smsMock);
        $result = $action->execute($community, [
            'filter_type' => 'new_members',
            // no filter_days key -- should default to 7
            'message'     => 'Default days!',
        ]);

        $this->assertSame(1, $result['sent']);
    }
}
