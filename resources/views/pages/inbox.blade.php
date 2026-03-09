<?php

use App\Models\FileInboxLog;
use App\Services\InboxImportService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Inbox')] class extends Component {

    public bool $autoImport = false;

    public ?string $importMessage = null;

    public ?string $importError = null;

    public function mount(): void
    {
        $this->autoImport = (bool) Auth::user()->inbox_auto_import;
    }

    public function updatedAutoImport(bool $value): void
    {
        Auth::user()->update(['inbox_auto_import' => $value]);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    #[Computed]
    public function files(): \Illuminate\Support\Collection
    {
        $diskFiles = app(InboxImportService::class)->listInboxFiles();

        $logs = FileInboxLog::orderByDesc('created_at')->get()->keyBy('filename');

        return $diskFiles->map(function (string $path) use ($logs) {
            $filename = basename($path);
            $log = $logs->get($filename);

            return [
                'filename' => $filename,
                'disk_path' => $path,
                'status' => $log?->status ?? 'pending',
                'error' => $log?->error_message,
                'date' => $log?->created_at,
            ];
        })->sortBy('filename')->values();
    }

    public function importFile(string $diskPath): void
    {
        $this->importMessage = null;
        $this->importError = null;

        $log = app(InboxImportService::class)->processFile($diskPath, Auth::user(), force: true);

        if ($log === null) {
            $this->importError = 'Could not read file from disk.';

            return;
        }

        if ($log->status === 'imported') {
            $this->importMessage = "'{$log->filename}' imported successfully.";
        } else {
            $this->importError = $log->error_message ?? 'Import failed.';
        }

        unset($this->files);
    }
}; ?>

<div class="w-full space-y-6">

    {{-- Breadcrumbs --}}
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>Dashboard</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Inbox</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    {{-- Header --}}
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Inbox</flux:heading>
            <flux:subheading>Files in the inbox folder. Click Import to bring a file into your account.</flux:subheading>
        </div>
        <flux:switch wire:model.live="autoImport" label="Auto-import for my account" />
    </div>

    @if ($importMessage)
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
            {{ $importMessage }}
        </div>
    @endif

    @if ($importError)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
            {{ $importError }}
        </div>
    @endif

    @if ($this->files->isEmpty())
        <div class="flex flex-1 items-center justify-center rounded-xl border border-dashed border-neutral-200 py-16 dark:border-neutral-700">
            <div class="text-center">
                <flux:icon name="inbox-arrow-down" class="mx-auto mb-3 size-10 text-neutral-400" />
                <flux:heading>No files in inbox</flux:heading>
                <flux:subheading class="mt-1">Place .json collection files in the inbox folder to import them.</flux:subheading>
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
                        <th class="px-4 py-3 text-left text-xs font-medium text-neutral-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @foreach ($this->files as $file)
                        <tr>
                            <td class="px-4 py-3 text-sm font-mono">{{ $file['filename'] }}</td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" color="{{ match($file['status']) { 'imported' => 'green', 'failed' => 'red', default => 'zinc' } }}">
                                    {{ $file['status'] }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-neutral-500">{{ $file['error'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-neutral-500">
                                {{ $file['date'] ? $file['date']->diffForHumans() : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <flux:button size="sm" wire:click="importFile('{{ $file['disk_path'] }}')" wire:loading.attr="disabled">
                                    Import
                                </flux:button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
