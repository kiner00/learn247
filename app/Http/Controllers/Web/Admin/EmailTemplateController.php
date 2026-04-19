<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\Admin\EmailTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/EmailTemplates', [
            'templates' => EmailTemplate::orderBy('name')->get(['id', 'key', 'name', 'subject', 'updated_at']),
        ]);
    }

    public function edit(string $key): Response
    {
        return Inertia::render('Admin/EmailTemplateEdit', [
            'template' => EmailTemplate::where('key', $key)->firstOrFail(),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $data = $request->validate([
            'subject'   => 'required|string|max:255',
            'html_body' => 'required|string',
        ]);

        EmailTemplate::where('key', $key)->firstOrFail()->update($data);

        return back()->with('success', 'Email template saved.');
    }

    public function preview(Request $request, string $key, EmailTemplateService $service): \Illuminate\Http\Response
    {
        $data = $request->validate([
            'subject'   => 'required|string|max:255',
            'html_body' => 'required|string',
        ]);

        return response($service->preview($key, $data['html_body']));
    }
}
