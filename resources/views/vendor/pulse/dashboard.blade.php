<x-pulse>
    <livewire:pulse.servers cols="full" />

    {{-- Top row: Usage (rowspan 2) + AI spend cards aligned beside it --}}
    <livewire:pulse.usage cols="4" rows="2" />
    <livewire:ai-spend cols="4" rows="2" />
    <livewire:ai-spend-users cols="4" rows="2" />

    {{-- Per-model breakdown spans the full width --}}
    <livewire:ai-spend-models cols="12" rows="2" />

    {{-- Standard Pulse cards below --}}
    <livewire:pulse.queues cols="4" />
    <livewire:pulse.cache cols="4" />
    <livewire:pulse.exceptions cols="4" />

    <livewire:pulse.slow-queries cols="6" />
    <livewire:pulse.slow-requests cols="6" />

    <livewire:pulse.slow-jobs cols="6" />
    <livewire:pulse.slow-outgoing-requests cols="6" />
</x-pulse>
