<x-mail::message>
# AI spend alert

**{{ ucfirst($scope) }}:** {{ $scopeLabel }} (#{{ $scopeId }})

Spent **${{ number_format($spent, 4) }}** on AI in the last **{{ $windowMinutes }} minutes**, crossing the **${{ number_format($threshold, 2) }}** threshold.

<x-mail::button :url="url('/admin/ai-usage?'.http_build_query([$scope.'_id' => $scopeId]))">
Investigate in admin
</x-mail::button>

If this is unexpected, check for a runaway loop, misuse of `gemini-2.5-pro` where flash would suffice, or abuse of the Curzzos chatbot.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
