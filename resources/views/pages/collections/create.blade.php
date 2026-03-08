<?php

use App\Actions\CreateEndpointCollection;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New Collection')] class extends Component {
    public string $name = '';
    public string $description = '';

    public function save(CreateEndpointCollection $action): void
    {
        $this->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $collection = $action->handle(auth()->user(), $this->name, $this->description ?: null);

        $this->redirectRoute('collections.show', $collection, navigate: true);
    }
}; ?>

<div class="mx-auto w-full max-w-2xl space-y-6">
    <div class="flex items-center gap-3">
        <flux:button href="{{ route('dashboard') }}" variant="ghost" icon="arrow-left" size="sm" />
        <flux:heading size="xl">New Collection</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-4">
        <flux:card class="space-y-4">
            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model="name" placeholder="e.g. Users API" autofocus />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:textarea wire:model="description" rows="2" placeholder="Optional description for this collection" />
                <flux:error name="description" />
            </flux:field>
        </flux:card>

        <div class="flex justify-end gap-3">
            <flux:button href="{{ route('dashboard') }}" variant="ghost">Cancel</flux:button>
            <flux:button type="submit" variant="primary">Create Collection</flux:button>
        </div>
    </form>
</div>
