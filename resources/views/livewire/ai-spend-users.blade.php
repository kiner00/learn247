<x-pulse::card :cols="$cols" :rows="$rows ?? 3" :class="$class" wire:poll.60s>
    <x-pulse::card-header name="AI spend by user">
        <x-slot:icon>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
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
        @if ($users->isEmpty())
            <x-pulse::no-results />
        @else
            <x-pulse::table>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>User</x-pulse::th>
                        <x-pulse::th class="text-right">Calls</x-pulse::th>
                        <x-pulse::th class="text-right">Tokens</x-pulse::th>
                        <x-pulse::th class="text-right">Cost (USD)</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($users as $row)
                        <tr>
                            <x-pulse::td>
                                @if ($row->user)
                                    <div class="flex flex-col">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $row->user->name }}</span>
                                        <span class="text-xs text-gray-500">{{ $row->user->email }}</span>
                                    </div>
                                @else
                                    <span class="text-gray-400">User #{{ $row->user_id }}</span>
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
