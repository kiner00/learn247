<x-pulse::card :cols="$cols" :rows="$rows ?? 3" :class="$class" wire:poll.60s>
    <x-pulse::card-header name="AI spend by community">
        <x-slot:icon>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-2.25 0-4-1-4-2.5S9.75 7 12 7s4 1 4 2.5v.5" />
            </svg>
        </x-slot:icon>
        <x-slot:actions>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                Total ${{ number_format((float) ($totals->cost ?? 0), 2) }}
                · {{ number_format((int) ($totals->tokens ?? 0)) }} tokens
                · {{ number_format((int) ($totals->calls ?? 0)) }} calls
            </div>
        </x-slot:actions>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand">
        @if ($communities->isEmpty())
            <x-pulse::no-results />
        @else
            <x-pulse::table>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>Community</x-pulse::th>
                        <x-pulse::th class="text-right">Calls</x-pulse::th>
                        <x-pulse::th class="text-right">Tokens</x-pulse::th>
                        <x-pulse::th class="text-right">Cost (USD)</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($communities as $row)
                        <tr>
                            <x-pulse::td>
                                @if ($row->community)
                                    <a href="/c/{{ $row->community->slug }}" class="font-medium text-gray-900 dark:text-gray-100 hover:underline">
                                        {{ $row->community->name }}
                                    </a>
                                @else
                                    <span class="text-gray-400">Community #{{ $row->community_id }}</span>
                                @endif
                            </x-pulse::td>
                            <x-pulse::td class="text-right tabular-nums">{{ number_format($row->calls) }}</x-pulse::td>
                            <x-pulse::td class="text-right tabular-nums">{{ number_format($row->tokens) }}</x-pulse::td>
                            <x-pulse::td class="text-right tabular-nums font-medium">${{ number_format((float) $row->cost, 4) }}</x-pulse::td>
                        </tr>
                    @endforeach
                </tbody>
            </x-pulse::table>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
