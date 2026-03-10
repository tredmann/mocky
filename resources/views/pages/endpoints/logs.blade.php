<?php

use App\Models\Endpoint;
use App\Models\EndpointCollection;
use App\Models\EndpointLog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Logs')] class extends Component {
    use WithPagination;

    public EndpointCollection $collection;
    public Endpoint $endpoint;
    public ?int $expandedLog = null;
    public string $endpointId = '';

    public function mount(): void
    {
        $this->authorize('view', $this->endpoint);
        $this->endpointId = $this->endpoint->id;
    }

    #[On('echo:endpoint.{endpointId},EndpointLogCreated')]
    public function handleNewLog(): void
    {
        $this->resetPage();
        unset($this->logs);
    }

    #[Computed]
    public function logs()
    {
        return $this->endpoint->logs()->paginate(25);
    }

    public function expand(int $id): void
    {
        $this->expandedLog = $this->expandedLog === $id ? null : $id;
    }

    public function clearLogs(): void
    {
        EndpointLog::where('endpoint_id', $this->endpoint->id)->delete();
        unset($this->logs);
    }
}; ?>

<div class="w-full space-y-6">

    {{-- Breadcrumbs + Actions --}}
    <div class="flex items-center justify-between">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>Dashboard</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('collections.show', $endpoint->collection)" wire:navigate>{{ $endpoint->collection->name }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('endpoints.show', [$endpoint->collection, $endpoint])" wire:navigate>{{ $endpoint->name }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Logs</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        @if ($this->logs->total() > 0)
            <flux:button
                wire:click="clearLogs"
                wire:confirm="Clear all logs for this endpoint?"
                variant="ghost"
                icon="trash"
                size="sm"
            >
                Clear Logs
            </flux:button>
        @endif
    </div>

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <flux:button href="{{ route('endpoints.show', [$endpoint->collection, $endpoint]) }}" variant="ghost" icon="arrow-left" size="sm" />
        <div>
            <div class="flex items-center gap-2">
                <flux:heading size="xl">Logs</flux:heading>
                <span
                    x-data="{ connected: false }"
                    x-init="
                        window.Echo?.connector?.pusher?.connection?.bind('connected', () => connected = true);
                        window.Echo?.connector?.pusher?.connection?.bind('disconnected', () => connected = false);
                        window.Echo?.connector?.pusher?.connection?.bind('unavailable', () => connected = false);
                        if (window.Echo?.connector?.pusher?.connection?.state === 'connected') connected = true;
                    "
                    x-show="connected"
                    class="flex items-center gap-1 text-xs text-green-600 dark:text-green-400"
                >
                    <span class="inline-block size-1.5 rounded-full bg-green-500"></span>
                    Live
                </span>
            </div>
            <flux:subheading>{{ $endpoint->name }}</flux:subheading>
        </div>
    </div>

    @if ($this->logs->isEmpty())
        <div class="flex flex-1 items-center justify-center rounded-xl border border-dashed border-neutral-200 py-16 dark:border-neutral-700">
            <div class="text-center">
                <flux:icon name="document-text" class="mx-auto mb-3 size-10 text-neutral-400" />
                <flux:heading>No requests yet</flux:heading>
                <flux:subheading class="mt-1">Requests to this endpoint will appear here.</flux:subheading>
            </div>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($this->logs as $log)
                <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">

                    {{-- Summary row --}}
                    <button
                        wire:click="expand({{ $log->id }})"
                        class="flex w-full items-center gap-4 px-4 py-3 text-left hover:bg-neutral-50 dark:hover:bg-neutral-800/50"
                    >
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

                        <span class="flex-1 truncate text-sm text-neutral-500">{{ $log->request_ip }}</span>

                        <span class="shrink-0 text-xs text-neutral-400">{{ $log->created_at->diffForHumans() }}</span>

                        <flux:icon
                            name="{{ $expandedLog === $log->id ? 'chevron-up' : 'chevron-down' }}"
                            class="size-4 shrink-0 text-neutral-400"
                        />
                    </button>

                    {{-- Expanded detail --}}
                    @if ($expandedLog === $log->id)
                        <div class="divide-y divide-neutral-200 border-t border-neutral-200 dark:divide-neutral-700 dark:border-neutral-700">

                            {{-- Request info --}}
                            <div class="grid grid-cols-2 gap-6 px-4 py-4 md:grid-cols-3">
                                <div>
                                    <p class="mb-1 text-xs font-medium text-neutral-400">IP Address</p>
                                    <p class="text-sm">{{ $log->request_ip ?? '—' }}</p>
                                </div>
                                <div class="col-span-2">
                                    <p class="mb-1 text-xs font-medium text-neutral-400">User Agent</p>
                                    <p class="truncate text-sm">{{ $log->request_user_agent ?? '—' }}</p>
                                </div>
                            </div>

                            {{-- Headers --}}
                            @if (!empty($log->request_headers))
                                <div class="px-4 py-4">
                                    <p class="mb-2 text-xs font-medium text-neutral-400">Request Headers</p>
                                    <div class="space-y-1">
                                        @foreach ($log->request_headers as $key => $values)
                                            <div class="flex gap-2 text-xs">
                                                <span class="w-48 shrink-0 font-mono text-neutral-500">{{ $key }}</span>
                                                <span class="font-mono">{{ implode(', ', (array) $values) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Query params --}}
                            @if (!empty($log->request_query))
                                <div class="px-4 py-4">
                                    <p class="mb-2 text-xs font-medium text-neutral-400">Query Parameters</p>
                                    <pre class="overflow-auto rounded bg-neutral-100 px-3 py-2 text-xs dark:bg-neutral-800">{{ json_encode($log->request_query, JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            @endif

                            {{-- Request body --}}
                            @if ($log->request_body)
                                <div class="px-4 py-4">
                                    <p class="mb-2 text-xs font-medium text-neutral-400">Request Body</p>
                                    <pre class="overflow-auto rounded bg-neutral-100 px-3 py-2 text-xs dark:bg-neutral-800">{{ $log->request_body }}</pre>
                                </div>
                            @endif

                            {{-- Response --}}
                            <div class="px-4 py-4">
                                <div class="mb-2 flex items-center gap-2">
                                    <p class="text-xs font-medium text-neutral-400">Response</p>
                                    @if ($log->matched_conditional_response_id)
                                        <flux:badge size="sm" color="blue">Conditional</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc">Default</flux:badge>
                                    @endif
                                </div>
                                @if ($log->response_body)
                                    <pre class="overflow-auto rounded bg-neutral-100 px-3 py-2 text-xs dark:bg-neutral-800">{{ $log->response_body }}</pre>
                                @else
                                    <p class="text-xs text-neutral-400">Empty response body</p>
                                @endif
                            </div>

                        </div>
                    @endif

                </div>
            @endforeach
        </div>

        <div>{{ $this->logs->links() }}</div>
    @endif

</div>
