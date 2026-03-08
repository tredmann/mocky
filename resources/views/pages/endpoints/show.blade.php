<?php

use App\Models\ConditionalResponse;
use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Services\CurlCommandBuilder;
use App\Services\EndpointExportService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Endpoint')] class extends Component {
    public EndpointCollection $collection;
    public Endpoint $endpoint;

    public string $curlCommand = '';

    public function mount(): void
    {
        $this->authorize('view', $this->endpoint);
        $this->endpoint->load('collection');
    }

    public function toggleActive(): void
    {
        $this->endpoint->update(['is_active' => ! $this->endpoint->is_active]);
    }

    public function delete(): void
    {
        $this->endpoint->delete();

        $this->redirectRoute('collections.show', $this->endpoint->collection, navigate: true);
    }

    public function export(EndpointExportService $service): mixed
    {
        return $service->export($this->endpoint);
    }

    public function showCurl(string $type = 'default', ?string $crId = null): void
    {
        $builder = app(CurlCommandBuilder::class);

        if ($type === 'conditional' && $crId !== null) {
            $cr = $this->endpoint->conditionalResponses()->find($crId);
            $this->curlCommand = $cr instanceof ConditionalResponse
                ? $builder->forConditional($this->endpoint, $cr)
                : $builder->forDefault($this->endpoint);
        } else {
            $this->curlCommand = $builder->forDefault($this->endpoint);
        }

        $this->dispatch('open-curl-modal');
    }

    #[Computed]
    public function conditionalResponses()
    {
        return $this->endpoint->conditionalResponses()->get();
    }

    #[Computed]
    public function recentLogs()
    {
        return $this->endpoint->logs()->limit(5)->get();
    }
}; ?>

<div class="w-full space-y-6" x-on:open-curl-modal.window="$flux.modal('curl').show()">

    {{-- Breadcrumbs + Actions --}}
    <div class="flex items-center justify-between">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>Dashboard</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('collections.show', $endpoint->collection)" wire:navigate>{{ $endpoint->collection->name }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $endpoint->name }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <div class="flex items-center gap-2">
            <flux:button wire:click="export" variant="ghost" icon="arrow-down-tray" />
            <flux:button wire:click="delete" wire:confirm="Delete this endpoint?" variant="ghost" icon="trash" />
            <flux:button href="{{ route('endpoints.edit', [$endpoint->collection, $endpoint]) }}" variant="primary" icon="pencil">
                Edit
            </flux:button>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <flux:button href="{{ route('collections.show', $endpoint->collection) }}" variant="ghost" icon="arrow-left" size="sm" />
        <flux:heading size="xl">{{ $endpoint->name }}</flux:heading>
        <flux:switch
            wire:click="toggleActive"
            :checked="$endpoint->is_active"
        />
    </div>

    {{-- Meta --}}
    <flux:card class="space-y-4">
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="mb-1 text-xs font-medium text-neutral-400">Method</p>
                <flux:badge color="{{ match($endpoint->method) {
                    'GET' => 'green',
                    'POST' => 'blue',
                    'PUT', 'PATCH' => 'yellow',
                    'DELETE' => 'red',
                    default => 'zinc',
                } }}">{{ $endpoint->method }}</flux:badge>
            </div>
            <div class="col-span-2">
                <p class="mb-1 text-xs font-medium text-neutral-400">Mock URL</p>
                <div class="flex items-center gap-2">
                    <code class="flex-1 text-sm">{{ $endpoint->mock_url }}</code>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="clipboard"
                        x-on:click="navigator.clipboard.writeText('{{ $endpoint->mock_url }}')"
                    />
                </div>
            </div>
        </div>
    </flux:card>

    {{-- Default Response --}}
    <flux:card class="space-y-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <flux:heading size="lg">Default Response</flux:heading>
                <flux:badge color="zinc" size="sm">Default</flux:badge>
            </div>
            <flux:button wire:click="showCurl('default')" variant="ghost" icon="command-line" size="sm" />
        </div>

        <div class="flex items-center gap-3 text-sm">
            <flux:badge color="{{ $endpoint->status_code < 300 ? 'green' : ($endpoint->status_code < 500 ? 'yellow' : 'red') }}">
                {{ $endpoint->status_code }}
            </flux:badge>
            <span class="text-neutral-400">{{ $endpoint->content_type }}</span>
        </div>

        @if ($endpoint->response_body)
            <pre class="overflow-auto rounded bg-neutral-100 px-3 py-2 text-xs dark:bg-neutral-800">{{ $endpoint->response_body }}</pre>
        @else
            <p class="text-sm text-neutral-400">Empty response body</p>
        @endif
    </flux:card>

    {{-- Conditional Responses --}}
    @if ($this->conditionalResponses->isNotEmpty())
        <div class="space-y-3">
            <flux:heading size="lg">Conditional Responses</flux:heading>

            @foreach ($this->conditionalResponses as $cr)
                <flux:card class="space-y-3">
                    <div class="flex items-start justify-between">
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
                        <flux:button wire:click="showCurl('conditional', '{{ $cr->id }}')" variant="ghost" icon="command-line" size="sm" />
                    </div>

                    @if ($cr->response_body)
                        <pre class="overflow-auto rounded bg-neutral-100 px-3 py-2 text-xs dark:bg-neutral-800">{{ $cr->response_body }}</pre>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif

    {{-- Recent Logs --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Recent Requests</flux:heading>
            <flux:button href="{{ route('endpoints.logs', [$endpoint->collection, $endpoint]) }}" variant="ghost" size="sm" icon="document-text">
                View all
            </flux:button>
        </div>

        @if ($this->recentLogs->isEmpty())
            <div class="rounded-xl border border-dashed border-neutral-200 px-4 py-8 text-center dark:border-neutral-700">
                <flux:subheading>No requests yet.</flux:subheading>
            </div>
        @else
            <flux:card class="divide-y divide-neutral-200 dark:divide-neutral-700 !p-0">
                @foreach ($this->recentLogs as $log)
                    <div class="flex items-center gap-4 px-4 py-3 text-sm">
                        <flux:badge size="sm" color="{{ match($log->request_method) {
                            'GET' => 'green',
                            'POST' => 'blue',
                            'PUT', 'PATCH' => 'yellow',
                            'DELETE' => 'red',
                            default => 'zinc',
                        } }}">{{ $log->request_method }}</flux:badge>

                        <flux:badge size="sm" color="{{ $log->response_status_code < 300 ? 'green' : ($log->response_status_code < 500 ? 'yellow' : 'red') }}">
                            {{ $log->response_status_code }}
                        </flux:badge>

                        @if ($log->matched_conditional_response_id)
                            <flux:icon name="adjustments-horizontal" class="size-4 text-blue-500" variant="mini" />
                        @endif

                        <span class="flex-1 text-neutral-500">{{ $log->request_ip }}</span>
                        <span class="text-xs text-neutral-400">{{ $log->created_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </flux:card>
        @endif
    </div>

    {{-- cURL Modal --}}
    <flux:modal name="curl" class="w-full max-w-2xl">
        <div class="space-y-4">
            <flux:heading size="lg">cURL Command</flux:heading>
            <pre class="overflow-auto rounded bg-neutral-100 px-4 py-3 text-xs dark:bg-neutral-800">{{ $curlCommand }}</pre>
            <div class="flex justify-end gap-2">
                <flux:button
                    variant="ghost"
                    icon="clipboard"
                    x-on:click="navigator.clipboard.writeText($wire.curlCommand)"
                >
                    Copy
                </flux:button>
                <flux:modal.close>
                    <flux:button variant="ghost">Close</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

</div>
