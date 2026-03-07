<?php

use App\Models\EndpointCollection;
use App\Services\EndpointImportService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Collection')] class extends Component {
    use WithFileUploads;

    public EndpointCollection $collection;

    public $importFile = null;
    public bool $showImport = false;
    public ?string $importError = null;

    public function mount(): void
    {
        abort_unless($this->collection->user_id === auth()->id(), 403);
    }

    #[Computed]
    public function endpoints()
    {
        return $this->collection->endpoints()->latest()->get();
    }

    public function delete(): void
    {
        $this->collection->delete();

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function toggleActive(string $id): void
    {
        $endpoint = $this->collection->endpoints()->where('id', $id)->firstOrFail();
        $endpoint->update(['is_active' => ! $endpoint->is_active]);
        unset($this->endpoints);
    }

    public function import(EndpointImportService $service): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:json', 'max:512']]);

        $data = json_decode(file_get_contents($this->importFile->getRealPath()), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            $this->importError = 'Invalid JSON file.';

            return;
        }

        $required = ['name', 'method', 'status_code', 'content_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->importError = "Missing required field: {$field}";

                return;
            }
        }

        $service->import(auth()->user(), $data, $this->collection);

        $this->showImport = false;
        $this->importFile = null;
        $this->importError = null;
        unset($this->endpoints);
    }

    public function cancelImport(): void
    {
        $this->showImport = false;
        $this->importFile = null;
        $this->importError = null;
    }
}; ?>

<div class="w-full space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <flux:button href="{{ route('dashboard') }}" variant="ghost" icon="arrow-left" size="sm" />
            <flux:heading size="xl">{{ $collection->name }}</flux:heading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button wire:click="$set('showImport', true)" variant="ghost" icon="arrow-up-tray" />
            <flux:button wire:click="delete" wire:confirm="Delete this collection and all its endpoints?" variant="ghost" icon="trash" />
            <flux:button href="{{ route('collections.edit', $collection) }}" variant="ghost" icon="pencil" />
            <flux:button href="{{ route('endpoints.create', $collection) }}" variant="primary" icon="plus">
                New Endpoint
            </flux:button>
        </div>
    </div>

    {{-- Meta --}}
    <flux:card class="space-y-2">
        @if ($collection->description)
            <p class="text-sm text-neutral-500">{{ $collection->description }}</p>
        @endif
        <div>
            <p class="mb-1 text-xs font-medium text-neutral-400">Mock URL Prefix</p>
            <div class="flex items-center gap-2">
                <code class="text-sm">{{ $collection->mock_url_prefix }}</code>
                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="clipboard"
                    x-on:click="navigator.clipboard.writeText('{{ $collection->mock_url_prefix }}')"
                />
            </div>
        </div>
    </flux:card>

    {{-- Import form --}}
    @if ($showImport)
        <flux:card class="space-y-4">
            <flux:heading size="lg">Import Endpoint</flux:heading>

            <flux:field>
                <flux:label>JSON File</flux:label>
                <flux:input wire:model="importFile" type="file" accept=".json" />
                <flux:error name="importFile" />
            </flux:field>

            @if ($importError)
                <p class="text-sm text-red-500">{{ $importError }}</p>
            @endif

            <div class="flex justify-end gap-3">
                <flux:button wire:click="cancelImport" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="import" variant="primary" icon="arrow-up-tray">Import</flux:button>
            </div>
        </flux:card>
    @endif

    {{-- Endpoints --}}
    @if ($this->endpoints->isEmpty())
        <div class="flex flex-1 items-center justify-center rounded-xl border border-dashed border-neutral-200 px-4 py-12 dark:border-neutral-700">
            <div class="text-center">
                <flux:icon name="globe-alt" class="mx-auto mb-3 size-10 text-neutral-400" />
                <flux:heading>No endpoints yet</flux:heading>
                <flux:subheading class="mt-1">Create your first endpoint in this collection.</flux:subheading>
                <div class="mt-4">
                    <flux:button href="{{ route('endpoints.create', $collection) }}" variant="primary" icon="plus">
                        New Endpoint
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
                        <th class="px-4 py-3 text-left font-medium text-neutral-600 dark:text-neutral-400">Method</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-600 dark:text-neutral-400">Mock URL</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-600 dark:text-neutral-400">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-neutral-600 dark:text-neutral-400">Active</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @foreach ($this->endpoints as $endpoint)
                        <tr class="bg-white dark:bg-neutral-900">
                            <td class="px-4 py-3">
                                <a href="{{ route('endpoints.show', [$collection, $endpoint]) }}" class="font-medium hover:underline">{{ $endpoint->name }}</a>
                                @if ($endpoint->description)
                                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ Str::limit($endpoint->description, 80) }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" color="{{ match($endpoint->method) {
                                    'GET' => 'green',
                                    'POST' => 'blue',
                                    'PUT', 'PATCH' => 'yellow',
                                    'DELETE' => 'red',
                                    default => 'zinc',
                                } }}">{{ $endpoint->method }}</flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <code class="rounded bg-neutral-100 px-2 py-0.5 text-xs dark:bg-neutral-800">
                                    {{ $endpoint->mock_url }}
                                </code>
                            </td>
                            <td class="px-4 py-3 text-neutral-500">{{ $endpoint->status_code }}</td>
                            <td class="px-4 py-3">
                                <flux:switch
                                    wire:click="toggleActive('{{ $endpoint->id }}')"
                                    :checked="$endpoint->is_active"
                                    class="[--color-accent:var(--color-green-500)]"
                                />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
