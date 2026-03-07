<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function collections()
    {
        return auth()->user()->endpointCollections()->withCount('endpoints')->latest()->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Collections</flux:heading>
        <flux:button href="{{ route('collections.create') }}" variant="primary" icon="plus">
            New Collection
        </flux:button>
    </div>

    @if ($this->collections->isEmpty())
        <div class="flex flex-1 items-center justify-center rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="text-center">
                <flux:icon name="folder" class="mx-auto mb-3 size-10 text-neutral-400" />
                <flux:heading>No collections yet</flux:heading>
                <flux:subheading class="mt-1">Create your first collection to start grouping endpoints.</flux:subheading>
                <div class="mt-4">
                    <flux:button href="{{ route('collections.create') }}" variant="primary" icon="plus">
                        New Collection
                    </flux:button>
                </div>
            </div>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <table class="w-full text-sm">
                <thead class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-neutral-600 dark:text-neutral-400">Name</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-600 dark:text-neutral-400">URL Prefix</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-600 dark:text-neutral-400">Endpoints</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @foreach ($this->collections as $collection)
                        <tr class="bg-white dark:bg-neutral-900">
                            <td class="px-4 py-3">
                                <a href="{{ route('collections.show', $collection) }}" class="font-medium hover:underline">{{ $collection->name }}</a>
                                @if ($collection->description)
                                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ Str::limit($collection->description, 80) }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <code class="rounded bg-neutral-100 px-2 py-0.5 text-xs dark:bg-neutral-800">
                                    /mock/{{ $collection->slug }}
                                </code>
                            </td>
                            <td class="px-4 py-3 text-neutral-500">{{ $collection->endpoints_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
