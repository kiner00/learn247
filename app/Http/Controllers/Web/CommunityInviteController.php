<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\CommunityInviteMail;
use App\Models\Community;
use App\Models\CommunityInvite;
use App\Models\CommunityMember;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CommunityInviteController extends Controller
{
    /**
     * Send invite(s) — single email or CSV batch.
     * Only community owner can call this.
     */
    public function store(Request $request, Community $community): RedirectResponse
    {
        abort_if(auth()->id() !== $community->owner_id, 403);

        $emails = [];

        if ($request->hasFile('csv')) {
            $request->validate(['csv' => 'file|mimes:csv,txt|max:2048']);

            $lines = file($request->file('csv')->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Support single-column or multi-column CSV — grab first column
                $col = trim(str_getcsv($line)[0] ?? '');
                if (filter_var($col, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = strtolower($col);
                }
            }
        } else {
            $request->validate(['email' => 'required|email']);
            $emails[] = strtolower(trim($request->email));
        }

        $emails = array_unique($emails);

        if (empty($emails)) {
            return back()->with('error', 'No valid email addresses found.');
        }

        $sent    = 0;
        $skipped = 0;

        foreach ($emails as $email) {
            // Skip if already an active member
            $alreadyMember = CommunityMember::where('community_id', $community->id)
                ->whereHas('user', fn ($q) => $q->where('email', $email))
                ->exists();

            if ($alreadyMember) {
                $skipped++;
                continue;
            }

            $invite = CommunityInvite::updateOrCreate(
                ['community_id' => $community->id, 'email' => $email],
                [
                    'token'       => Str::random(64),
                    'accepted_at' => null,
                    'expires_at'  => now()->addDays(7),
                ]
            );

            Mail::to($email)->send(new CommunityInviteMail($invite));
            $sent++;
        }

        $message = "Invite" . ($sent !== 1 ? 's' : '') . " sent to {$sent} email" . ($sent !== 1 ? 's' : '') . ".";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (already members).";
        }

        return back()->with('success', $message);
    }

    /**
     * Accept an invite — anyone with the link can land here.
     * Not-logged-in users are redirected to login first.
     */
    public function accept(string $token): RedirectResponse
    {
        $invite = CommunityInvite::with('community')->where('token', $token)->firstOrFail();

        if (! auth()->check()) {
            return redirect()->route('login', ['redirect' => "/invite/{$token}"]);
        }

        $user      = auth()->user();
        $community = $invite->community;

        if ($invite->isExpired()) {
            return redirect()->route('communities.about', $community->slug)
                ->with('error', 'This invite link has expired.');
        }

        if ($invite->isAccepted()) {
            return redirect()->route('communities.show', $community->slug)
                ->with('info', 'This invite has already been accepted.');
        }

        if (strtolower($user->email) !== strtolower($invite->email)) {
            return redirect()->route('communities.index')
                ->with('error', "This invite was sent to {$invite->email}. Please log in with that account.");
        }

        // Add as community member (idempotent)
        CommunityMember::firstOrCreate(
            ['community_id' => $community->id, 'user_id' => $user->id],
            ['role' => CommunityMember::ROLE_MEMBER, 'joined_at' => now()]
        );

        // For paid communities, grant a complimentary active subscription
        if (! $community->isFree()) {
            Subscription::firstOrCreate(
                ['community_id' => $community->id, 'user_id' => $user->id],
                [
                    'status'     => Subscription::STATUS_ACTIVE,
                    'expires_at' => null, // no expiry for invited members
                ]
            );
        }

        $invite->update(['accepted_at' => now()]);

        return redirect()->route('communities.show', $community->slug)
            ->with('success', "Welcome to {$community->name}!");
    }
}
