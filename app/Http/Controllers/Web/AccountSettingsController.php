<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AccountSettingsController extends Controller
{
    private array $defaultNotifPrefs = [
        'follower'  => true,
        'likes'     => true,
        'kaching'   => true,
        'affiliate' => true,
    ];

    private array $defaultChatPrefs = [
        'notifications'      => true,
        'email_notifications' => true,
    ];

    private array $defaultCommunityNotifPrefs = [
        'new_posts' => true,
        'comments'  => true,
        'mentions'  => true,
    ];

    public function show(Request $request): Response
    {
        $user = $request->user();

        $memberships = CommunityMember::where('user_id', $user->id)
            ->with('community:id,name,slug,avatar,price,owner_id')
            ->orderBy('joined_at')
            ->get()
            ->map(fn ($m) => [
                'community_id' => $m->community_id,
                'name'         => $m->community?->name,
                'slug'         => $m->community?->slug,
                'avatar'       => $m->community?->avatar,
                'price'        => $m->community?->price,
                'is_owner'     => $m->community?->owner_id === $user->id,
                'role'         => $m->role,
                'joined_at'    => $m->joined_at,
                'notif_prefs'     => array_merge($this->defaultCommunityNotifPrefs, $m->notif_prefs ?? []),
                'chat_enabled'    => $m->chat_enabled ?? true,
                'show_on_profile' => $m->show_on_profile ?? true,
            ]);

        return Inertia::render('Account/Settings', [
            'tab'          => $request->get('tab', 'communities'),
            'profileUser'  => [
                'first_name'       => explode(' ', $user->name, 2)[0] ?? '',
                'last_name'        => explode(' ', $user->name, 2)[1] ?? '',
                'username'         => $user->username,
                'bio'              => $user->bio,
                'email'            => $user->email,
                'avatar'           => $user->avatar,
                'location'         => $user->location,
                'social_links'     => $user->social_links ?? [],
                'hide_from_search' => $user->hide_from_search ?? false,
            ],
            'memberships'     => $memberships->values(),
            'affiliateLink'   => url('/register?ref=' . $user->username),
            'timezone'        => $user->timezone ?? 'Asia/Manila',
            'theme'           => $user->theme ?? 'light',
            'notifPrefs'      => array_merge($this->defaultNotifPrefs, $user->notification_prefs ?? []),
            'chatPrefs'       => array_merge($this->defaultChatPrefs, $user->chat_prefs ?? []),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name'       => ['required', 'string', 'max:255'],
            'last_name'        => ['required', 'string', 'max:255'],
            'bio'              => ['nullable', 'string', 'max:300'],
            'location'         => ['nullable', 'string', 'max:255'],
            'social_links'     => ['nullable', 'array'],
            'social_links.*'   => ['nullable', 'string', 'max:500'],
            'hide_from_search' => ['nullable', 'boolean'],
            'avatar'           => ['nullable', 'image', 'max:5120'],
        ]);

        $data['name'] = trim($data['first_name'] . ' ' . $data['last_name']);
        unset($data['first_name'], $data['last_name']);

        if ($request->hasFile('avatar')) {
            if ($user->avatar && str_starts_with($user->avatar, '/storage/')) {
                Storage::disk('public')->delete(ltrim(str_replace('/storage/', '', $user->avatar), '/'));
            }
            $path = $request->file('avatar')->store('user-avatars', 'public');
            $data['avatar'] = Storage::url($path);
        } else {
            unset($data['avatar']);
        }

        $user->update($data);

        return back()->with('success', 'Profile updated!');
    }

    public function updateMembershipVisibility(Request $request, int $communityId): RedirectResponse
    {
        $data = $request->validate(['show_on_profile' => ['required', 'boolean']]);

        $member = CommunityMember::where('user_id', $request->user()->id)
            ->where('community_id', $communityId)
            ->firstOrFail();

        $member->update($data);

        return back()->with('success', 'Membership visibility updated!');
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update(['email' => $data['email']]);

        return back()->with('success', 'Email updated!');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password updated!');
    }

    public function updateTimezone(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        $request->user()->update(['timezone' => $data['timezone']]);

        return back()->with('success', 'Timezone saved!');
    }

    public function logoutEverywhere(Request $request): RedirectResponse
    {
        // Cycle remember_token to invalidate all "remember me" sessions
        $request->user()->forceFill([
            'remember_token' => Str::random(60),
        ])->save();

        // Regenerate current session so this device stays logged in
        $request->session()->regenerate();

        return back()->with('success', 'Logged out of all other devices.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'follower'  => ['required', 'boolean'],
            'likes'     => ['required', 'boolean'],
            'kaching'   => ['required', 'boolean'],
            'affiliate' => ['required', 'boolean'],
        ]);

        $request->user()->update(['notification_prefs' => $data]);

        return back()->with('success', 'Notification preferences saved!');
    }

    public function updateCommunityNotifications(Request $request, int $communityId): RedirectResponse
    {
        $data = $request->validate([
            'new_posts' => ['required', 'boolean'],
            'comments'  => ['required', 'boolean'],
            'mentions'  => ['required', 'boolean'],
        ]);

        $member = CommunityMember::where('user_id', $request->user()->id)
            ->where('community_id', $communityId)
            ->firstOrFail();

        $member->update(['notif_prefs' => $data]);

        return back()->with('success', 'Community notification preferences saved!');
    }

    public function updateChat(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'notifications'       => ['required', 'boolean'],
            'email_notifications' => ['required', 'boolean'],
        ]);

        $request->user()->update(['chat_prefs' => $data]);

        return back()->with('success', 'Chat preferences saved!');
    }

    public function updateCommunityChat(Request $request, int $communityId): RedirectResponse
    {
        $data = $request->validate([
            'chat_enabled' => ['required', 'boolean'],
        ]);

        $member = CommunityMember::where('user_id', $request->user()->id)
            ->where('community_id', $communityId)
            ->firstOrFail();

        $member->update(['chat_enabled' => $data['chat_enabled']]);

        return back()->with('success', 'Chat preference saved!');
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'string', 'in:light,dark'],
        ]);

        $request->user()->update(['theme' => $data['theme']]);

        return back()->with('success', 'Theme saved!');
    }
}
