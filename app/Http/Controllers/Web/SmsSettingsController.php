<?php

namespace App\Http\Controllers\Web;

use App\Actions\Community\SendSmsBlast;
use App\Contracts\SmsProvider;
use App\Http\Controllers\Controller;
use App\Models\Community;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SmsSettingsController extends Controller
{
    public function update(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'sms_provider'    => ['nullable', 'string', 'in:semaphore,philsms,xtreme_sms'],
            'sms_api_key'     => ['nullable', 'string', 'max:255'],
            'sms_sender_name' => ['nullable', 'string', 'max:11'],
            'sms_device_url'  => ['nullable', 'string', 'url', 'max:500'],
        ]);

        $community->update($data);

        return back()->with('success', 'SMS settings saved.');
    }

    public function test(Request $request, Community $community, SmsProvider $sms): RedirectResponse
    {
        $this->authorize('update', $community);

        if (! $community->sms_provider || ! $community->sms_api_key) {
            return back()->withErrors(['sms_test' => 'Save your SMS settings first before testing.']);
        }

        $data  = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = preg_replace('/\D/', '', $data['phone']);

        if (strlen($phone) < 10) {
            return back()->withErrors(['sms_test' => 'Please enter a valid phone number.']);
        }

        $result = $sms->blast($community, [$phone], "This is a test message from {$community->name} via Curzzo. Your SMS integration is working!");

        if ($result['sent'] > 0) {
            return back()->with('success', "Test SMS sent to {$data['phone']}.");
        }

        return back()->withErrors(['sms_test' => 'Test failed: ' . ($result['errors'][0] ?? 'Unknown error.')]);
    }

    public function blast(Request $request, Community $community, SendSmsBlast $action): RedirectResponse
    {
        $this->authorize('update', $community);

        $data = $request->validate([
            'message'          => ['required', 'string', 'max:1600'],
            'filter_type'      => ['required', 'string', 'in:all,new_members,course'],
            'filter_days'      => ['nullable', 'integer', 'in:7,14,30'],
            'filter_course_id' => ['nullable', 'integer', 'exists:courses,id'],
        ]);

        if (! $community->sms_provider || ! $community->sms_api_key) {
            return back()->withErrors(['message' => 'SMS provider not configured. Go to Settings → SMS to set it up.']);
        }

        $result = $action->execute($community, $data);

        if ($result['no_recipients']) {
            return back()->withErrors(['message' => 'No recipients found with phone numbers for the selected audience.']);
        }

        $msg = "SMS sent to {$result['sent']} member(s).";
        if ($result['failed'] > 0) {
            $msg .= " {$result['failed']} failed.";
        }

        return back()->with('success', $msg);
    }
}
