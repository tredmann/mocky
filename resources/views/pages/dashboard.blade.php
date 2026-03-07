<?php

use App\Services\CollectionImportService;
use App\Services\OpenApiImportService;
use App\Services\PostmanImportService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Dashboard')] class extends Component {
    use WithFileUploads;

    public $importFile = null;
    public bool $showImport = false;
    public ?string $importError = null;
    public string $importType = 'native';

    #[Computed]
    public function collections()
    {
        return auth()->user()->endpointCollections()->withCount('endpoints')->latest()->get();
    }

    public function importCollection(
        CollectionImportService $collectionService,
        OpenApiImportService $openApiService,
        PostmanImportService $postmanService,
    ): void {
        $rules = $this->importType === 'openapi'
            ? ['required', 'file', 'max:5120']
            : ['required', 'file', 'mimes:json', 'max:5120'];
        $this->validate(['importFile' => $rules]);

        try {
            if ($this->importType === 'openapi') {
                $openApiService->importFromFile(auth()->user(), $this->importFile->getRealPath());
            } elseif ($this->importType === 'postman') {
                $data = json_decode(file_get_contents($this->importFile->getRealPath()), true);

                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
                    $this->importError = 'Invalid JSON file.';

                    return;
                }

                $postmanService->import(auth()->user(), $data);
            } else {
                $data = json_decode(file_get_contents($this->importFile->getRealPath()), true);

                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
                    $this->importError = 'Invalid JSON file.';

                    return;
                }

                if (empty($data['name'])) {
                    $this->importError = 'Missing required field: name';

                    return;
                }

                $collectionService->import(auth()->user(), $data);
            }
        } catch (\InvalidArgumentException $e) {
            $this->importError = $e->getMessage();

            return;
        }

        $this->showImport = false;
        $this->importFile = null;
        $this->importError = null;
        $this->importType = 'native';
        unset($this->collections);
    }

    public function cancelImport(): void
    {
        $this->showImport = false;
        $this->importFile = null;
        $this->importError = null;
        $this->importType = 'native';
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">Collections</flux:heading>
        <div class="flex items-center gap-2">
            <flux:button wire:click="$set('showImport', true)" variant="ghost" icon="arrow-up-tray" />
            <flux:button href="{{ route('collections.create') }}" variant="primary" icon="plus">
                New Collection
            </flux:button>
        </div>
    </div>

    @if ($showImport)
        <flux:card class="space-y-4">
            <flux:heading size="lg">Import Collection</flux:heading>

            <flux:field>
                <flux:label>Format</flux:label>
                <flux:select wire:model.live="importType">
                    <flux:select.option value="native">Native JSON</flux:select.option>
                    <flux:select.option value="openapi">OpenAPI (JSON / YAML)</flux:select.option>
                    <flux:select.option value="postman">Postman Collection</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>File</flux:label>
                <flux:input wire:key="import-file-{{ $importType }}" wire:model="importFile" type="file" accept="{{ $importType === 'openapi' ? '.json,.yaml,.yml' : '.json' }}" />
                <flux:error name="importFile" />
            </flux:field>

            @if ($importError)
                <p class="text-sm text-red-500">{{ $importError }}</p>
            @endif

            <div class="flex justify-end gap-3">
                <flux:button wire:click="cancelImport" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="importCollection" variant="primary" icon="arrow-up-tray">Import</flux:button>
            </div>
        </flux:card>
    @endif

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
