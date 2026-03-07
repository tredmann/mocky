<?php

use App\Models\EndpointCollection;
use App\Rules\ValidResponseSyntax;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New Endpoint')] class extends Component {
    public EndpointCollection $collection;

    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public string $method = 'GET';
    public int $status_code = 200;
    public string $content_type = 'application/json';
    public string $response_body = '';

    public function mount(): void
    {
        abort_unless($this->collection->user_id === auth()->id(), 403);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name'          => ['required', 'string', 'max:255'],
            'slug'          => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/', Rule::unique('endpoints', 'slug')->where(fn ($query) => $query->where('collection_id', $this->collection->id)->where('method', $this->method))],
            'description'   => ['nullable', 'string', 'max:1000'],
            'method'        => ['required', 'in:GET,POST,PUT,PATCH,DELETE'],
            'status_code'   => ['required', 'integer', 'min:100', 'max:599'],
            'content_type'  => ['required', 'string', 'max:255'],
            'response_body' => ['nullable', 'string', new ValidResponseSyntax],
        ]);

        $endpoint = $this->collection->endpoints()->create([
            ...$validated,
            'user_id' => auth()->id(),
        ]);

        $this->redirectRoute('endpoints.show', [$this->collection, $endpoint], navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-2xl space-y-6">
    <div class="flex items-center gap-3">
        <flux:button href="{{ route('collections.show', $collection) }}" variant="ghost" icon="arrow-left" size="sm" />
        <flux:heading size="xl">New Endpoint</flux:heading>
        <flux:badge color="zinc" size="sm">{{ $collection->name }}</flux:badge>
    </div>

    <div class="flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
        <p class="text-xs font-medium text-neutral-400">URL Prefix</p>
        <code class="text-sm">{{ $collection->mock_url_prefix }}/</code>
    </div>

    <form wire:submit="save" class="space-y-4" x-data="{ editorHasError: false }" @editor-error.window="if ($event.detail.field === 'response_body') editorHasError = $event.detail.hasError">

        {{-- Request --}}
        <flux:card class="space-y-4">
            <flux:heading size="lg">Request</flux:heading>

            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model="name" placeholder="e.g. Get user by ID" autofocus />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Slug</flux:label>
                <flux:input wire:model.live.debounce.300ms="slug" placeholder="e.g. get-user" />
                <flux:description>The URL path for this endpoint: <code>{{ $collection->mock_url_prefix }}/{{ $slug ?: 'your-slug' }}</code></flux:description>
                <flux:error name="slug" />
            </flux:field>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:textarea wire:model="description" rows="2" placeholder="Optional description for this endpoint" />
                <flux:error name="description" />
            </flux:field>

            <flux:field>
                <flux:label>Method</flux:label>
                <flux:select wire:model="method">
                    <flux:select.option value="GET">GET</flux:select.option>
                    <flux:select.option value="POST">POST</flux:select.option>
                    <flux:select.option value="PUT">PUT</flux:select.option>
                    <flux:select.option value="PATCH">PATCH</flux:select.option>
                    <flux:select.option value="DELETE">DELETE</flux:select.option>
                </flux:select>
                <flux:error name="method" />
            </flux:field>
        </flux:card>

        {{-- Default Response --}}
        <flux:card class="space-y-4">
            <div class="flex items-center gap-2">
                <flux:heading size="lg">Default Response</flux:heading>
                <flux:badge color="zinc" size="sm">Default</flux:badge>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Status Code</flux:label>
                    <flux:input wire:model="status_code" type="number" min="100" max="599" />
                    <flux:error name="status_code" />
                </flux:field>

                <flux:field>
                    <flux:label>Content Type</flux:label>
                    <flux:select wire:model.live="content_type">
                        <flux:select.option value="application/json">application/json</flux:select.option>
                        <flux:select.option value="text/plain">text/plain</flux:select.option>
                        <flux:select.option value="text/html">text/html</flux:select.option>
                        <flux:select.option value="application/xml">application/xml</flux:select.option>
                    </flux:select>
                    <flux:error name="content_type" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Response Body</flux:label>
                <x-code-editor wire="response_body" :content-type="$content_type" content-type-wire="content_type" />
                <flux:error name="response_body" />
            </flux:field>
        </flux:card>

        <div class="flex justify-end gap-3">
            <flux:button href="{{ route('collections.show', $collection) }}" variant="ghost">Cancel</flux:button>
            <flux:button type="submit" variant="primary" x-bind:disabled="editorHasError">Create Endpoint</flux:button>
        </div>

    </form>
</div>
