<?php

use App\Models\FileInboxLog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Import Log')] class extends Component {
    use WithPagination;

    #[Computed]
    public function logs()
    {
        return FileInboxLog::latest()->paginate(25);
    }
}; ?>

<div class="w-full space-y-6">

    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>Dashboard</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Import Log</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Header --}}
    <div>
        <flux:heading size="xl">Import Log</flux:heading>
        <flux:subheading>Files processed from the inbox folder.</flux:subheading>
    </div>

    @if ($this->logs->isEmpty())
        <div class="flex flex-1 items-center justify-center rounded-xl border border-dashed border-neutral-200 py-16 dark:border-neutral-700">
            <div class="text-center">
                <flux:icon name="inbox-arrow-down" class="mx-auto mb-3 size-10 text-neutral-400" />
                <flux:heading>No imports yet</flux:heading>
                <flux:subheading class="mt-1">Files placed in the inbox folder will be processed automatically.</flux:subheading>
            </div>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <table class="min-w-full divide-y divide-neutral-200 dark:divide-neutral-700">
                <thead class="bg-neutral-50 dark:bg-neutral-800/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500">Filename</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500">Error</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @foreach ($this->logs as $log)
                        <tr>
                            <td class="px-4 py-3 text-sm font-mono">{{ $log->filename }}</td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" color="{{ $log->status === 'imported' ? 'green' : 'red' }}">
                                    {{ $log->status }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-neutral-500">{{ $log->error_message ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-neutral-500">{{ $log->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>{{ $this->logs->links() }}</div>
    @endif

</div>
