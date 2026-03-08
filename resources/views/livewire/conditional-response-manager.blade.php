<div class="space-y-3" x-data="{ crResponseBodyError: false }" @editor-error.window="if ($event.detail.field === 'response_body') crResponseBodyError = $event.detail.hasError">
    <div class="flex items-center justify-between">
        <flux:heading size="lg">Conditional Responses</flux:heading>
        @if (!$showForm)
            <flux:button wire:click="$set('showForm', true)" variant="ghost" icon="plus" size="sm">
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
                        <flux:badge size="sm" color="blue">{{ strtoupper($cr->condition_source->value) }}</flux:badge>
                        <code class="text-xs">{{ $cr->condition_field }}</code>
                        <span class="text-neutral-400">{{ str_replace('_', ' ', $cr->condition_operator->value) }}</span>
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
                    wire:click="delete({{ $cr->id }})"
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
        @if (!$showForm)
            <div class="rounded-xl border border-dashed border-neutral-200 px-4 py-8 text-center dark:border-neutral-700">
                <flux:subheading>No conditional responses yet.</flux:subheading>
            </div>
        @endif
    @endforelse

    {{-- Add conditional response form --}}
    @if ($showForm)
        <flux:card class="space-y-4">
            <flux:heading size="lg">New Conditional Response</flux:heading>

            <div class="space-y-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="sm" class="text-neutral-500">Condition</flux:heading>

                <div class="grid grid-cols-3 gap-3">
                    <flux:field>
                        <flux:label>Source</flux:label>
                        <flux:select wire:model.live="condition_source">
                            @if ($method !== 'GET')
                                <flux:select.option value="body">Body</flux:select.option>
                            @endif
                            <flux:select.option value="query">Query</flux:select.option>
                            <flux:select.option value="header">Header</flux:select.option>
                            <flux:select.option value="path">Path</flux:select.option>
                        </flux:select>
                        <flux:error name="condition_source" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Field</flux:label>
                        @if ($condition_source === 'path')
                            <flux:input wire:model="condition_field" type="number" min="0" placeholder="0" />
                            <flux:description>Segment index (0-based). {{ $endpoint->mock_url }}/foo/bar → 0=foo, 1=bar</flux:description>
                        @else
                            <flux:input wire:model="condition_field" placeholder="{{ $condition_source === 'header' ? 'e.g. X-Api-Key' : 'e.g. id' }}" />
                        @endif
                        <flux:error name="condition_field" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Operator</flux:label>
                        <flux:select wire:model="condition_operator">
                            <flux:select.option value="equals">Equals</flux:select.option>
                            <flux:select.option value="not_equals">Not equals</flux:select.option>
                            <flux:select.option value="contains">Contains</flux:select.option>
                        </flux:select>
                        <flux:error name="condition_operator" />
                    </flux:field>
                </div>

                {{-- Contextual help --}}
                <div class="rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                    @if ($condition_source === 'body')
                        <p class="font-medium">Matching on request body (JSON)</p>
                        <p class="mt-1 text-xs">Use the JSON field name as the field. Supports dot notation for nested values.</p>
                        <div class="mt-2 space-y-1 font-mono text-xs">
                            <p><span class="opacity-60">flat</span> &nbsp;&nbsp;&nbsp; {"id": 1} &rarr; field: <strong>id</strong></p>
                            <p><span class="opacity-60">nested</span> {"user": {"id": 1}} &rarr; field: <strong>user.id</strong></p>
                        </div>
                    @elseif ($condition_source === 'query')
                        <p class="font-medium">Matching on query parameter</p>
                        <p class="mt-1 text-xs">Use the query parameter name as the field.</p>
                        <div class="mt-2 font-mono text-xs">
                            <p>{{ $endpoint->mock_url }}?<strong>status</strong>=active &rarr; field: <strong>status</strong></p>
                        </div>
                    @elseif ($condition_source === 'header')
                        <p class="font-medium">Matching on request header</p>
                        <p class="mt-1 text-xs">Use the header name as the field. Header names are case-insensitive.</p>
                        <div class="mt-2 font-mono text-xs">
                            <p>X-Api-Key: secret &rarr; field: <strong>X-Api-Key</strong></p>
                        </div>
                    @elseif ($condition_source === 'path')
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
                    <flux:input wire:model="condition_value" placeholder="e.g. 1" />
                    <flux:error name="condition_value" />
                </flux:field>
            </div>

            <div class="space-y-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:heading size="sm" class="text-neutral-500">Response</flux:heading>

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
            </div>

            <div class="flex justify-end gap-3">
                <flux:button wire:click="resetForm" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="add" variant="primary" x-bind:disabled="crResponseBodyError">Add</flux:button>
            </div>
        </flux:card>
    @endif
</div>
