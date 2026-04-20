<x-pulse::card :cols="$cols" :rows="$rows ?? 3" :class="$class" wire:poll.60s>
    <x-pulse::card-header name="AI spend by model" />

    <x-pulse::scroll :expand="$expand">
        @if ($models->isEmpty())
            <x-pulse::no-results />
        @else
            <x-pulse::table>
                <x-pulse::thead>
                    <tr>
                        <x-pulse::th>Model</x-pulse::th>
                        <x-pulse::th>Kind</x-pulse::th>
                        <x-pulse::th class="text-right">Calls</x-pulse::th>
                        <x-pulse::th class="text-right">Tokens</x-pulse::th>
                        <x-pulse::th class="text-right">Cost (USD)</x-pulse::th>
                    </tr>
                </x-pulse::thead>
                <tbody>
                    @foreach ($models as $row)
                        <tr>
                            <x-pulse::td class="font-mono text-xs">{{ $row->model }}</x-pulse::td>
                            <x-pulse::td>
                                <span class="text-xs px-1.5 py-0.5 rounded {{ $row->kind === 'image' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' }}">
                                    {{ $row->kind }}
                                </span>
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
