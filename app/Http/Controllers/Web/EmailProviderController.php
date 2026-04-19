<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Services\Email\EmailProviderFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailProviderController extends Controller
{
    public function updateConfig(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        $providerIds = array_keys(EmailProviderFactory::PROVIDERS);

        $data = $request->validate([
            'email_provider'    => ['nullable', 'string', 'in:' . implode(',', $providerIds)],
            'resend_api_key'    => ['nullable', 'string', 'max:500'],
            'resend_from_email' => ['nullable', 'email', 'max:255'],
            'resend_from_name'  => ['nullable', 'string', 'max:255'],
            'resend_reply_to'   => ['nullable', 'email', 'max:255'],
        ]);

        if (! empty($data['resend_api_key']) && ! empty($data['email_provider'])) {
            $community->resend_api_key = $data['resend_api_key'];
            $community->email_provider = $data['email_provider'];

            try {
                $provider = EmailProviderFactory::make($community);
                if (! $provider->validateApiKey($community)) {
                    return back()->withErrors(['resend_api_key' => 'Invalid API key for ' . ($data['email_provider'] ?? 'provider') . '.']);
                }
            } catch (\Exception $e) {
                return back()->withErrors(['resend_api_key' => 'Could not validate the API key: ' . $e->getMessage()]);
            }
        }

        $community->update($data);

        return back()->with('success', 'Email settings saved.');
    }

    public function addDomain(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        if (! $community->resend_api_key) {
            return back()->withErrors(['resend_domain' => 'Save your Resend API key first.']);
        }

        $data = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
        ]);

        try {
            $provider = EmailProviderFactory::make($community);
            $domain = $provider->addDomain($community, $data['domain']);

            $community->update([
                'resend_domain_id'     => $domain['id'],
                'resend_domain_status' => $domain['status'] ?? 'pending',
            ]);

            return back()->with('success', 'Domain added. Please configure the DNS records shown below, then click Verify.');
        } catch (\Exception $e) {
            return back()->withErrors(['resend_domain' => 'Failed to add domain: ' . $e->getMessage()]);
        }
    }

    public function verifyDomain(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        if (! $community->resend_api_key || ! $community->resend_domain_id) {
            return back()->withErrors(['resend_domain' => 'No domain to verify.']);
        }

        try {
            $provider = EmailProviderFactory::make($community);
            $domain = $provider->verifyDomain($community, $community->resend_domain_id);

            $community->update([
                'resend_domain_status' => $domain['status'] ?? 'pending',
            ]);

            $status = $domain['status'] ?? 'pending';

            return $status === 'verified'
                ? back()->with('success', 'Domain verified successfully!')
                : back()->with('success', "Domain status: {$status}. DNS propagation may take a few minutes.");
        } catch (\Exception $e) {
            return back()->withErrors(['resend_domain' => 'Verification failed: ' . $e->getMessage()]);
        }
    }

    public function getDomain(Request $request, Community $community)
    {
        $this->authorize('update', $community);

        if (! $community->resend_api_key || ! $community->resend_domain_id) {
            return response()->json(['error' => 'No domain configured.'], 422);
        }

        try {
            $provider = EmailProviderFactory::make($community);
            $domain = $provider->getDomain($community, $community->resend_domain_id);

            $community->update([
                'resend_domain_status' => $domain['status'] ?? 'pending',
            ]);

            return response()->json([
                'id'      => $domain['id'],
                'name'    => $domain['name'],
                'status'  => $domain['status'],
                'records' => $domain['records'] ?? [],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function testEmail(Request $request, Community $community): RedirectResponse
    {
        $this->authorize('update', $community);

        if (! $community->resend_api_key) {
            return back()->withErrors(['resend_test' => 'Save your Resend API key first.']);
        }

        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        $fromName = $community->resend_from_name ?? $community->name;
        $fromEmail = $community->resend_from_email ?? 'onboarding@resend.dev';
        $isResend  = ($community->email_provider ?? 'resend') === 'resend';

        try {
            $provider = EmailProviderFactory::make($community);

            $replyTo = $community->resend_reply_to ? [$community->resend_reply_to] : [];

            try {
                $provider->sendEmail($community, [
                    'from'     => "{$fromName} <{$fromEmail}>",
                    'to'       => [$data['test_email']],
                    'subject'  => "Test email from {$community->name}",
                    'html'     => "<p>This is a test email from <strong>{$community->name}</strong> via Curzzo. Your email integration is working!</p>",
                    'reply_to' => $replyTo,
                ]);

                return back()->with('success', "Test email sent to {$data['test_email']}.");
            } catch (\Exception $e) {
                if ($isResend && str_contains($e->getMessage(), 'not verified')) {
                    $provider->sendEmail($community, [
                        'from'    => "Curzzo <onboarding@resend.dev>",
                        'to'      => [$data['test_email']],
                        'subject' => "[Test] Email from {$community->name}",
                        'html'    => "<p>This is a test email from <strong>{$community->name}</strong> via Curzzo.</p><p style='color:#666;font-size:13px;'>Sent from Resend sandbox (onboarding@resend.dev) because your domain is not yet verified. Verify your domain at <a href='https://resend.com/domains'>resend.com/domains</a> to send from your own address.</p>",
                    ]);

                    return back()->with('success', "Test sent via Resend sandbox to {$data['test_email']}. Verify your domain at resend.com/domains to use your own from address.");
                }

                throw $e;
            }
        } catch (\Exception $e) {
            return back()->withErrors(['resend_test' => 'Test failed: ' . $e->getMessage()]);
        }
    }
}
