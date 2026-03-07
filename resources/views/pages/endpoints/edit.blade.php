<?php

use App\Models\ConditionalResponse;
use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Rules\ValidResponseSyntax;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Endpoint')] class extends Component {
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

    // Conditional response form
    public bool $showConditionalForm = false;
    public string $cr_condition_source = 'body';
    public string $cr_condition_field = '';
    public string $cr_condition_operator = 'equals';
    public string $cr_condition_value = '';
    public int $cr_status_code = 200;
    public string $cr_content_type = 'application/json';
    public string $cr_response_body = '';

    public bool $saved = false;

    public function mount(): void
    {
        abort_unless($this->endpoint->user_id === auth()->id(), 403);
        $this->endpoint->load('collection');

        $this->cr_condition_source = $this->endpoint->method === 'GET' ? 'query' : 'body';

        $this->name          = $this->endpoint->name;
        $this->slug          = $this->endpoint->slug;
        $this->description   = $this->endpoint->description ?? '';
        $this->method        = $this->endpoint->method;
        $this->status_code   = $this->endpoint->status_code;
        $this->content_type  = $this->endpoint->content_type;
        $this->response_body = $this->endpoint->response_body ?? '';
    }

    public function updatedMethod(string $value): void
    {
        $this->cr_condition_source = $value === 'GET' ? 'query' : 'body';
    }

    #[Computed]
    public function conditionalResponses()
    {
        return $this->endpoint->conditionalResponses()->get();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name'          => ['required', 'string', 'max:255'],
            'slug'          => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/', Rule::unique('endpoints', 'slug')->where(fn ($query) => $query->where('collection_id', $this->endpoint->collection_id)->where('method', $this->method))->ignore($this->endpoint->id)],
            'description'   => ['nullable', 'string', 'max:1000'],
            'method'        => ['required', 'in:GET,POST,PUT,PATCH,DELETE'],
            'status_code'   => ['required', 'integer', 'min:100', 'max:599'],
            'content_type'  => ['required', 'string', 'max:255'],
            'response_body' => ['nullable', 'string', new ValidResponseSyntax],
        ]);

        $this->endpoint->update($validated);
        $this->redirectRoute('endpoints.show', [$this->endpoint->collection, $this->endpoint], navigate: true);
    }

    public function addConditionalResponse(): void
    {
        $this->validate([
            'cr_condition_source'   => ['required', 'in:body,query,header,path'],
            'cr_condition_field'    => ['required', 'string', 'max:255'],
            'cr_condition_operator' => ['required', 'in:equals,not_equals,contains'],
            'cr_condition_value'    => ['required', 'string', 'max:255'],
            'cr_status_code'        => ['required', 'integer', 'min:100', 'max:599'],
            'cr_content_type'       => ['required', 'string', 'max:255'],
            'cr_response_body'      => ['nullable', 'string', new ValidResponseSyntax('cr_content_type')],
        ], [], [
            'cr_condition_source'   => 'source',
            'cr_condition_field'    => 'field',
            'cr_condition_operator' => 'operator',
            'cr_condition_value'    => 'value',
            'cr_status_code'        => 'status code',
            'cr_content_type'       => 'content type',
            'cr_response_body'      => 'response body',
        ]);

        $priority = $this->endpoint->conditionalResponses()->max('priority') + 1;

        $this->endpoint->conditionalResponses()->create([
            'condition_source'   => $this->cr_condition_source,
            'condition_field'    => $this->cr_condition_field,
            'condition_operator' => $this->cr_condition_operator,
            'condition_value'    => $this->cr_condition_value,
            'status_code'        => $this->cr_status_code,
            'content_type'       => $this->cr_content_type,
            'response_body'      => $this->cr_response_body,
            'priority'           => $priority,
        ]);

        $this->resetConditionalForm();
        unset($this->conditionalResponses);
    }

    public function deleteConditionalResponse(int $id): void
    {
        ConditionalResponse::where('id', $id)
            ->where('endpoint_id', $this->endpoint->id)
            ->delete();

        unset($this->conditionalResponses);
    }

    public function resetConditionalForm(): void
    {
        $this->showConditionalForm   = false;
        $this->cr_condition_source   = $this->endpoint->method === 'GET' ? 'query' : 'body';
        $this->cr_condition_field    = '';
        $this->cr_condition_operator = 'equals';
        $this->cr_condition_value    = '';
        $this->cr_status_code        = 200;
        $this->cr_content_type       = 'application/json';
        $this->cr_response_body      = '';
    }
}; ?>

<div class="mx-auto w-full max-w-2xl space-y-6" x-data="{ responseBodyError: false, crResponseBodyError: false }" @editor-error.window="if ($event.detail.field === 'response_body') responseBodyError = $event.detail.hasError; if ($event.detail.field === 'cr_response_body') crResponseBodyError = $event.detail.hasError">

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
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Conditional Responses</flux:heading>
            @if (!$showConditionalForm)
                <flux:button wire:click="$set('showConditionalForm', true)" variant="ghost" icon="plus" size="sm">
                    Add Condition
                </flux:button>
            @endif
        </div>

        {{-- Existing conditional responses --}}
        @forelse ($this->conditionalResponses as $cr)
            <flux:card class="space-y-3">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-sm">
                            <flux:badge size="sm" color="blue">{{ strtoupper($cr->condition_source) }}</flux:badge>
                            <code class="text-xs">{{ $cr->condition_field }}</code>
                            <span class="text-neutral-400">{{ str_replace('_', ' ', $cr->condition_operator) }}</span>
                            <code class="text-xs">{{ $cr->condition_value }}</code>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-neutral-500">
                            <span>→</span>
                            <flux:badge size="sm" color="{{ $cr->status_code < 300 ? 'green' : ($cr->status_code < 500 ? 'yellow' : 'red') }}">
                                {{ $cr->status_code }}
                            </flux:badge>
                            <span class="text-xs">{{ $cr->content_type }}</span>
                        </div>
                    </div>
                    <flux:button
                        wire:click="deleteConditionalResponse({{ $cr->id }})"
                        wire:confirm="Delete this conditional response?"
                        size="sm"
                        variant="ghost"
                        icon="trash"
                    />
                </div>

                @if ($cr->response_body)
                    <pre class="overflow-auto rounded bg-neutral-100 px-3 py-2 text-xs dark:bg-neutral-800">{{ $cr->response_body }}</pre>
                @endif
            </flux:card>
        @empty
            @if (!$showConditionalForm)
                <div class="rounded-xl border border-dashed border-neutral-200 px-4 py-8 text-center dark:border-neutral-700">
                    <flux:subheading>No conditional responses yet.</flux:subheading>
                </div>
            @endif
        @endforelse

        {{-- Add conditional response form --}}
        @if ($showConditionalForm)
            <flux:card class="space-y-4">
                <flux:heading size="lg">New Conditional Response</flux:heading>

                <div class="space-y-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                    <flux:heading size="sm" class="text-neutral-500">Condition</flux:heading>

                    <div class="grid grid-cols-3 gap-3">
                        <flux:field>
                            <flux:label>Source</flux:label>
                            <flux:select wire:model.live="cr_condition_source">
                                @if ($endpoint->method !== 'GET')
                                    <flux:select.option value="body">Body</flux:select.option>
                                @endif
                                <flux:select.option value="query">Query</flux:select.option>
                                <flux:select.option value="header">Header</flux:select.option>
                                <flux:select.option value="path">Path</flux:select.option>
                            </flux:select>
                            <flux:error name="cr_condition_source" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Field</flux:label>
                            @if ($cr_condition_source === 'path')
                                <flux:input wire:model="cr_condition_field" type="number" min="0" placeholder="0" />
                                <flux:description>Segment index (0-based). {{ $endpoint->mock_url }}/foo/bar → 0=foo, 1=bar</flux:description>
                            @else
                                <flux:input wire:model="cr_condition_field" placeholder="{{ $cr_condition_source === 'header' ? 'e.g. X-Api-Key' : 'e.g. id' }}" />
                            @endif
                            <flux:error name="cr_condition_field" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Operator</flux:label>
                            <flux:select wire:model="cr_condition_operator">
                                <flux:select.option value="equals">Equals</flux:select.option>
                                <flux:select.option value="not_equals">Not equals</flux:select.option>
                                <flux:select.option value="contains">Contains</flux:select.option>
                            </flux:select>
                            <flux:error name="cr_condition_operator" />
                        </flux:field>
                    </div>

                    {{-- Contextual help --}}
                    <div class="rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                        @if ($cr_condition_source === 'body')
                            <p class="font-medium">Matching on request body (JSON)</p>
                            <p class="mt-1 text-xs">Use the JSON field name as the field. Supports dot notation for nested values.</p>
                            <div class="mt-2 space-y-1 font-mono text-xs">
                                <p><span class="opacity-60">flat</span> &nbsp;&nbsp;&nbsp; {"id": 1} &rarr; field: <strong>id</strong></p>
                                <p><span class="opacity-60">nested</span> {"user": {"id": 1}} &rarr; field: <strong>user.id</strong></p>
                            </div>
                        @elseif ($cr_condition_source === 'query')
                            <p class="font-medium">Matching on query parameter</p>
                            <p class="mt-1 text-xs">Use the query parameter name as the field.</p>
                            <div class="mt-2 font-mono text-xs">
                                <p>{{ $endpoint->mock_url }}?<strong>status</strong>=active &rarr; field: <strong>status</strong></p>
                            </div>
                        @elseif ($cr_condition_source === 'header')
                            <p class="font-medium">Matching on request header</p>
                            <p class="mt-1 text-xs">Use the header name as the field. Header names are case-insensitive.</p>
                            <div class="mt-2 font-mono text-xs">
                                <p>X-Api-Key: secret &rarr; field: <strong>X-Api-Key</strong></p>
                            </div>
                        @elseif ($cr_condition_source === 'path')
                            <p class="font-medium">Matching on URL path segment</p>
                            <p class="mt-1 text-xs">Use the segment index (0-based) as the field. Segments are the parts of the URL after the slug.</p>
                            <div class="mt-2 font-mono text-xs">
                                <p>{{ $endpoint->mock_url }}/<strong>foo</strong>/bar &rarr; index: <strong>0</strong></p>
                                <p>{{ $endpoint->mock_url }}/foo/<strong>bar</strong> &rarr; index: <strong>1</strong></p>
                            </div>
                        @endif
                    </div>

                    <flux:field>
                        <flux:label>Value</flux:label>
                        <flux:input wire:model="cr_condition_value" placeholder="e.g. 1" />
                        <flux:error name="cr_condition_value" />
                    </flux:field>
                </div>

                <div class="space-y-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                    <flux:heading size="sm" class="text-neutral-500">Response</flux:heading>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Status Code</flux:label>
                            <flux:input wire:model="cr_status_code" type="number" min="100" max="599" />
                            <flux:error name="cr_status_code" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Content Type</flux:label>
                            <flux:select wire:model.live="cr_content_type">
                                <flux:select.option value="application/json">application/json</flux:select.option>
                                <flux:select.option value="text/plain">text/plain</flux:select.option>
                                <flux:select.option value="text/html">text/html</flux:select.option>
                                <flux:select.option value="application/xml">application/xml</flux:select.option>
                            </flux:select>
                            <flux:error name="cr_content_type" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>Response Body</flux:label>
                        <x-code-editor wire="cr_response_body" :content-type="$cr_content_type" content-type-wire="cr_content_type" />
                        <flux:error name="cr_response_body" />
                    </flux:field>
                </div>

                <div class="flex justify-end gap-3">
                    <flux:button wire:click="resetConditionalForm" variant="ghost">Cancel</flux:button>
                    <flux:button wire:click="addConditionalResponse" variant="primary" x-bind:disabled="crResponseBodyError">Add</flux:button>
                </div>
            </flux:card>
        @endif
    </div>

</div>
