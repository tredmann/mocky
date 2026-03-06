<?php

use App\Models\Endpoint;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New Endpoint')] class extends Component {
    public string $name = '';
    public string $method = 'GET';
    public int $status_code = 200;
    public string $content_type = 'application/json';
    public string $response_body = '';

    public function save(): void
    {
        $validated = $this->validate([
            'name'          => ['required', 'string', 'max:255'],
            'method'        => ['required', 'in:GET,POST,PUT,PATCH,DELETE'],
            'status_code'   => ['required', 'integer', 'min:100', 'max:599'],
            'content_type'  => ['required', 'string', 'max:255'],
            'response_body' => ['nullable', 'string'],
        ]);

        $endpoint = auth()->user()->endpoints()->create($validated);

        $this->redirectRoute('endpoints.edit', $endpoint, navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-2xl space-y-6">
    <div class="flex items-center gap-3">
        <flux:button href="{{ route('dashboard') }}" variant="ghost" icon="arrow-left" size="sm" />
        <flux:heading size="xl">New Endpoint</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-4">

        {{-- Request --}}
        <flux:card class="space-y-4">
            <flux:heading size="lg">Request</flux:heading>

            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model="name" placeholder="e.g. Get user by ID" autofocus />
                <flux:error name="name" />
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
                    <flux:select wire:model="content_type">
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
                <flux:textarea wire:model="response_body" rows="8" placeholder='{"message": "ok"}' class="font-mono text-sm" />
                <flux:error name="response_body" />
            </flux:field>
        </flux:card>

        <div class="flex justify-end gap-3">
            <flux:button href="{{ route('dashboard') }}" variant="ghost">Cancel</flux:button>
            <flux:button type="submit" variant="primary">Create Endpoint</flux:button>
        </div>

    </form>
</div>
