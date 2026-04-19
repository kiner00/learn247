<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Admin\SendGlobalAnnouncement;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Admin/GlobalAnnouncement');
    }

    public function send(Request $request, SendGlobalAnnouncement $action): RedirectResponse
    {
        $data = $request->validate([
            'subject'  => 'required|string|max:255',
            'message'  => 'required|string',
            'audience' => 'required|in:affiliates,creators,members,all',
        ]);

        $count = $action->execute($request->user(), $data['subject'], $data['message'], $data['audience']);

        return back()->with('success', "Announcement queued for {$count} recipients.");
    }
}
