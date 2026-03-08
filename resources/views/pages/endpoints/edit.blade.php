<?php

use App\Actions\UpdateEndpoint;
use App\Concerns\EndpointValidationRules;
use App\Models\Endpoint;
use App\Models\EndpointCollection;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Endpoint')] class extends Component {
    use EndpointValidationRules;

    public EndpointCollection $collection;
    public Endpoint $endpoint;

    // Endpoint fields
    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public string $method = 'GET';

    // Default response fields
    public int $status_code = 200;
    public string $content_type = 'application/json';
    public string $response_body = '';

    public bool $saved = false;

    public function mount(): void
    {
        $this->authorize('update', $this->endpoint);
        $this->endpoint->load('collection');

        $this->name          = $this->endpoint->name;
        $this->slug          = $this->endpoint->slug;
        $this->description   = $this->endpoint->description ?? '';
        $this->method        = $this->endpoint->method;
        $this->status_code   = $this->endpoint->status_code;
        $this->content_type  = $this->endpoint->content_type;
        $this->response_body = $this->endpoint->response_body ?? '';
    }

    public function save(UpdateEndpoint $action): void
    {
        $this->validate($this->endpointRules(
            $this->endpoint->collection_id,
            $this->method,
            $this->endpoint->id,
        ));

        $action->handle(
            endpoint: $this->endpoint,
            name: $this->name,
            slug: $this->slug,
            method: $this->method,
            statusCode: $this->status_code,
            contentType: $this->content_type,
            description: $this->description ?: null,
            responseBody: $this->response_body ?: null,
        );

        $this->redirectRoute('endpoints.show', [$this->endpoint->collection, $this->endpoint], navigate: true);
    }
}; ?>

<div class="w-full space-y-6" x-data="{ responseBodyError: false }" @editor-error.window="if ($event.detail.field === 'response_body') responseBodyError = $event.detail.hasError">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <flux:button href="{{ route('endpoints.show', [$endpoint->collection, $endpoint]) }}" variant="ghost" icon="arrow-left" size="sm" />
        <flux:heading size="xl">Edit Endpoint</flux:heading>
    </div>

    {{-- Mock URL --}}
    <div class="flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800">
        <code class="flex-1 text-sm">{{ $endpoint->mock_url }}</code>
        <flux:button
            size="sm"
            variant="ghost"
            icon="clipboard"
            x-on:click="navigator.clipboard.writeText('{{ $endpoint->mock_url }}')"
        />
    </div>

    <form wire:submit="save" class="space-y-4">

        {{-- Request --}}
        <flux:card class="space-y-4">
            <flux:heading size="lg">Request</flux:heading>

            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model="name" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Slug</flux:label>
                <flux:input wire:model.live.debounce.300ms="slug" />
                <flux:description>The URL path for this endpoint: <code>{{ $endpoint->collection->mock_url_prefix }}/{{ $slug ?: 'your-slug' }}</code></flux:description>
                <flux:error name="slug" />
            </flux:field>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:textarea wire:model="description" rows="2" placeholder="Optional description for this endpoint" />
                <flux:error name="description" />
            </flux:field>

            <flux:field>
                <flux:label>Method</flux:label>
                <flux:select wire:model.live="method">
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

        <div class="flex items-center justify-end gap-3">
            @if ($saved)
                <flux:text class="text-green-600 dark:text-green-400">Saved!</flux:text>
            @endif
            <flux:button href="{{ route('endpoints.show', [$endpoint->collection, $endpoint]) }}" variant="ghost">Cancel</flux:button>
            <flux:button type="submit" variant="primary" wire:click="$set('saved', false)" x-bind:disabled="responseBodyError">Save Changes</flux:button>
        </div>

    </form>

    {{-- Conditional Responses --}}
    <livewire:conditional-response-manager :endpoint="$endpoint" :method="$method" />

</div>
